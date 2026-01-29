<?php

namespace App\EventSubscriber;

use App\Service\IpSecurityService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Logs all 4xx HTTP errors for security monitoring.
 * Priority -10 ensures this runs after the response is generated.
 */
class SecurityLogSubscriber implements EventSubscriberInterface
{
    // URLs to exclude from logging (legitimate 404s)
    private const EXCLUDED_PATTERNS = [
        '/favicon.ico',
        '/robots.txt',
        '/sitemap.xml',
        '/.well-known/',
        '/apple-touch-icon',
    ];

    public function __construct(
        private IpSecurityService $ipSecurityService,
        private string $environment,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => ['onKernelResponse', -10],
        ];
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $response = $event->getResponse();
        $statusCode = $response->getStatusCode();

        // Only log 4xx errors
        if ($statusCode < 400 || $statusCode >= 500) {
            return;
        }

        $request = $event->getRequest();
        $url = $request->getRequestUri();

        // Skip excluded patterns
        foreach (self::EXCLUDED_PATTERNS as $pattern) {
            if (str_starts_with($url, $pattern)) {
                return;
            }
        }

        // Don't log in dev environment for performance
        if ($this->environment === 'dev') {
            return;
        }

        $clientIp = IpSecurityService::getClientIp($request);
        $userAgent = $request->headers->get('User-Agent');
        $referer = $request->headers->get('Referer');

        // Collect extra data for suspicious requests
        $extraData = null;
        if ($this->isSuspiciousRequest($url, $userAgent)) {
            $extraData = [
                'accept' => $request->headers->get('Accept'),
                'accept_language' => $request->headers->get('Accept-Language'),
                'host' => $request->getHost(),
            ];
        }

        $this->ipSecurityService->logSecurityEvent(
            $clientIp,
            $url,
            $request->getMethod(),
            $statusCode,
            $userAgent,
            $referer,
            $extraData
        );
    }

    /**
     * Detect suspicious request patterns
     */
    private function isSuspiciousRequest(string $url, ?string $userAgent): bool
    {
        // Common attack patterns
        $suspiciousPatterns = [
            '/wp-',           // WordPress scanner
            '/admin',         // Admin probing
            '/.env',          // Environment file
            '/.git',          // Git repository
            '/phpinfo',       // PHP info
            '/config',        // Config files
            '.php',           // Random PHP files
            '/backup',        // Backup files
            '/db',            // Database files
            '/sql',           // SQL files
            '/shell',         // Shell access attempts
            '/cgi-bin',       // CGI scripts
            '/xmlrpc',        // XML-RPC attacks
            '/eval',          // Code injection
            '/passwd',        // Password files
            '/etc/',          // System files
        ];

        $urlLower = strtolower($url);
        foreach ($suspiciousPatterns as $pattern) {
            if (str_contains($urlLower, $pattern)) {
                return true;
            }
        }

        // Suspicious user agents
        if ($userAgent) {
            $suspiciousAgents = ['sqlmap', 'nikto', 'nmap', 'masscan', 'zgrab', 'python-requests', 'curl/', 'wget/'];
            $agentLower = strtolower($userAgent);
            foreach ($suspiciousAgents as $agent) {
                if (str_contains($agentLower, $agent)) {
                    return true;
                }
            }
        }

        return false;
    }
}
