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
}
