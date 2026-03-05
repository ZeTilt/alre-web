<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class MatomoTrackingService
{
    private const BOT_PATTERN = '/Googlebot|bingbot|Slurp|DuckDuckBot|Baiduspider|YandexBot|Sogou|facebookexternalhit|Twitterbot|LinkedInBot|AhrefsBot|SemrushBot|MJ12bot|Bytespider|GPTBot|ClaudeBot/i';

    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
        private string $matomoUrl,
        private string $matomoSiteId,
        private string $matomoTokenAuth,
    ) {
    }

    public function isBotRequest(Request $request): bool
    {
        $userAgent = $request->headers->get('User-Agent', '');

        return (bool) preg_match(self::BOT_PATTERN, $userAgent);
    }

    public function trackBotVisit(Request $request): void
    {
        if (empty($this->matomoTokenAuth)) {
            return;
        }

        try {
            $this->httpClient->request('POST', $this->matomoUrl . '/matomo.php', [
                'body' => [
                    'rec' => 1,
                    'idsite' => $this->matomoSiteId,
                    'token_auth' => $this->matomoTokenAuth,
                    'url' => $request->getUri(),
                    'urlref' => $request->headers->get('Referer', ''),
                    'ua' => $request->headers->get('User-Agent', ''),
                    'cip' => $request->getClientIp(),
                    'action_name' => $request->getPathInfo(),
                    'recMode' => 1,
                    'send_image' => 0,
                ],
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Matomo bot tracking failed: {message}', [
                'message' => $e->getMessage(),
                'url' => $request->getUri(),
            ]);
        }
    }
}
