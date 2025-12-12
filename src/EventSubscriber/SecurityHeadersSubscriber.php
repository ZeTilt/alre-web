<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Adds security headers to all HTTP responses
 * Protects against XSS, clickjacking, MIME sniffing, and other attacks
 */
class SecurityHeadersSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly string $environment)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => 'onKernelResponse',
        ];
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $response = $event->getResponse();
        $request = $event->getRequest();
        $headers = $response->headers;

        // Add cache headers for static assets
        $path = $request->getPathInfo();
        if ($this->isStaticAsset($path)) {
            // Cache static assets for 1 year (immutable assets should have versioned filenames)
            $headers->set('Cache-Control', 'public, max-age=31536000, immutable');
        }

        // Only apply strict headers in production
        if ($this->environment === 'prod') {
            // Prevent MIME type sniffing
            $headers->set('X-Content-Type-Options', 'nosniff');

            // Prevent clickjacking attacks
            $headers->set('X-Frame-Options', 'DENY');

            // Control referrer information
            $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

            // XSS Protection (legacy, but still useful for older browsers)
            $headers->set('X-XSS-Protection', '1; mode=block');

            // Disable some browser features that could be abused
            $headers->set('Permissions-Policy', 'geolocation=(), microphone=(), camera=()');

            // Content Security Policy
            $headers->set('Content-Security-Policy', $this->buildCsp());
        }

        // Apply these headers in all environments for better security defaults
        // Remove server version information
        $headers->remove('X-Powered-By');
        $headers->set('X-Frame-Options', 'SAMEORIGIN');
    }

    private function isStaticAsset(string $path): bool
    {
        return (bool) preg_match('/\.(css|js|jpg|jpeg|png|gif|webp|avif|svg|woff|woff2|ttf|eot|ico)$/i', $path);
    }

    private function buildCsp(): string
    {
        $directives = [
            // Default: only allow resources from same origin
            "default-src 'self'",

            // Scripts: self + CDNs + inline (needed for EasyAdmin and analytics)
            "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net https://matomo.alre-web.bzh",

            // Styles: self + CDNs + inline (needed for EasyAdmin)
            "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://fonts.googleapis.com",

            // Fonts: self + Google Fonts (FontAwesome now hosted locally)
            "font-src 'self' https://fonts.gstatic.com data:",

            // Images: self + data URIs (for inline images)
            "img-src 'self' data: https://matomo.alre-web.bzh",

            // Connections (AJAX, WebSocket)
            "connect-src 'self' https://matomo.alre-web.bzh",

            // Forms can only submit to same origin
            "form-action 'self'",

            // Frame ancestors (prevent clickjacking)
            "frame-ancestors 'self'",

            // Base URI restriction
            "base-uri 'self'",

            // Block all object/embed/applet
            "object-src 'none'",
        ];

        return implode('; ', $directives);
    }
}
