<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class GoogleSearchConsoleService
{
    private const API_URL = 'https://www.googleapis.com/webmasters/v3/sites';
    private const MAX_RETRIES = 3;
    private const RETRY_DELAYS = [1, 2, 4]; // seconds

    public function __construct(
        private HttpClientInterface $httpClient,
        private GoogleOAuthService $googleOAuthService,
        private LoggerInterface $logger,
        private string $googleSiteUrl,
    ) {}

    /**
     * Vérifie si le service est configuré et connecté.
     */
    public function isAvailable(): bool
    {
        return !empty($this->googleSiteUrl)
            && $this->googleOAuthService->isConfigured()
            && $this->googleOAuthService->isConnected();
    }

    /**
     * Récupère les données de position pour un mot-clé spécifique.
     *
     * @return array{position: float, clicks: int, impressions: int}|null
     */
    public function fetchDataForKeyword(string $keyword, ?\DateTimeImmutable $startDate = null, ?\DateTimeImmutable $endDate = null): ?array
    {
        $token = $this->googleOAuthService->getValidTokenWithRefresh();
        if (!$token) {
            $this->logger->error('No valid OAuth token available for GSC API call');
            return null;
        }

        // Par défaut, récupérer les données des 7 derniers jours
        $endDate = $endDate ?? new \DateTimeImmutable('-1 day');
        $startDate = $startDate ?? new \DateTimeImmutable('-7 days');

        $encodedSiteUrl = urlencode($this->googleSiteUrl);
        $url = self::API_URL . "/{$encodedSiteUrl}/searchAnalytics/query";

        $body = [
            'startDate' => $startDate->format('Y-m-d'),
            'endDate' => $endDate->format('Y-m-d'),
            'dimensions' => ['query'],
            'dimensionFilterGroups' => [
                [
                    'filters' => [
                        [
                            'dimension' => 'query',
                            'operator' => 'equals',
                            'expression' => $keyword,
                        ],
                    ],
                ],
            ],
            'rowLimit' => 1,
        ];

        $result = $this->executeWithRetry($url, $body, $token->getAccessToken());

        if ($result === null) {
            return null;
        }

        // Parser la réponse
        if (!isset($result['rows']) || empty($result['rows'])) {
            $this->logger->info('No data found for keyword in GSC', ['keyword' => $keyword]);
            return null;
        }

        $row = $result['rows'][0];
        return [
            'position' => round($row['position'] ?? 0, 1),
            'clicks' => (int) ($row['clicks'] ?? 0),
            'impressions' => (int) ($row['impressions'] ?? 0),
        ];
    }

    /**
     * Récupère les données pour tous les mots-clés actifs en une seule requête.
     *
     * @return array<string, array{position: float, clicks: int, impressions: int}>
     */
    public function fetchAllKeywordsData(?\DateTimeImmutable $startDate = null, ?\DateTimeImmutable $endDate = null): array
    {
        $token = $this->googleOAuthService->getValidTokenWithRefresh();
        if (!$token) {
            $this->logger->error('No valid OAuth token available for GSC API call');
            return [];
        }

        $endDate = $endDate ?? new \DateTimeImmutable('-1 day');
        $startDate = $startDate ?? new \DateTimeImmutable('-7 days');

        $encodedSiteUrl = urlencode($this->googleSiteUrl);
        $url = self::API_URL . "/{$encodedSiteUrl}/searchAnalytics/query";

        $body = [
            'startDate' => $startDate->format('Y-m-d'),
            'endDate' => $endDate->format('Y-m-d'),
            'dimensions' => ['query'],
            'rowLimit' => 1000,
        ];

        $result = $this->executeWithRetry($url, $body, $token->getAccessToken());

        if ($result === null || !isset($result['rows'])) {
            return [];
        }

        $data = [];
        foreach ($result['rows'] as $row) {
            $keyword = $row['keys'][0] ?? null;
            if ($keyword) {
                $data[strtolower($keyword)] = [
                    'position' => round($row['position'] ?? 0, 1),
                    'clicks' => (int) ($row['clicks'] ?? 0),
                    'impressions' => (int) ($row['impressions'] ?? 0),
                ];
            }
        }

        return $data;
    }

    /**
     * Exécute une requête avec retry et backoff exponentiel.
     */
    private function executeWithRetry(string $url, array $body, string $accessToken): ?array
    {
        $lastException = null;

        for ($attempt = 0; $attempt < self::MAX_RETRIES; $attempt++) {
            try {
                $response = $this->httpClient->request('POST', $url, [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $accessToken,
                        'Content-Type' => 'application/json',
                    ],
                    'json' => $body,
                ]);

                $statusCode = $response->getStatusCode();

                if ($statusCode === 200) {
                    return $response->toArray();
                }

                // Erreur non-retryable (4xx sauf 429)
                if ($statusCode >= 400 && $statusCode < 500 && $statusCode !== 429) {
                    $this->logger->error('GSC API client error', [
                        'status_code' => $statusCode,
                        'response' => $response->getContent(false),
                        'attempt' => $attempt + 1,
                    ]);
                    return null;
                }

                // Erreur retryable (5xx ou 429)
                $this->logger->warning('GSC API error, will retry', [
                    'status_code' => $statusCode,
                    'attempt' => $attempt + 1,
                    'max_retries' => self::MAX_RETRIES,
                ]);

            } catch (\Exception $e) {
                $lastException = $e;
                $this->logger->warning('GSC API exception, will retry', [
                    'error' => $e->getMessage(),
                    'attempt' => $attempt + 1,
                    'max_retries' => self::MAX_RETRIES,
                ]);
            }

            // Attendre avant le prochain essai (sauf si c'était le dernier)
            if ($attempt < self::MAX_RETRIES - 1) {
                sleep(self::RETRY_DELAYS[$attempt]);
            }
        }

        $this->logger->error('GSC API failed after all retries', [
            'url' => $url,
            'last_error' => $lastException?->getMessage(),
        ]);

        return null;
    }
}
