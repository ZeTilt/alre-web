<?php

namespace App\Service;

use App\Repository\BingConfigRepository;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class BingWebmasterService
{
    private const API_BASE_URL = 'https://ssl.bing.com/webmaster/api.svc/json';
    private const MAX_RETRIES = 3;
    private const RETRY_DELAYS = [1, 2, 4];

    public function __construct(
        private HttpClientInterface $httpClient,
        private BingConfigRepository $bingConfigRepository,
        private LoggerInterface $logger,
    ) {}

    public function isAvailable(): bool
    {
        $config = $this->bingConfigRepository->findOneBy([]);
        return $config !== null && !empty($config->getApiKey());
    }

    /**
     * Récupère les stats de requêtes pour un site.
     * Retourne [date][keyword] => {clicks, impressions, position}
     *
     * @return array<string, array<string, array{clicks: int, impressions: int, position: float}>>
     */
    public function fetchQueryStats(string $siteUrl): array
    {
        $apiKey = $this->getApiKey();
        if ($apiKey === null) {
            return [];
        }

        $url = self::API_BASE_URL . '/GetQueryStats?' . http_build_query([
            'apikey' => $apiKey,
            'siteUrl' => $siteUrl,
        ]);

        $result = $this->executeWithRetry('GET', $url);
        if ($result === null || !isset($result['d'])) {
            return [];
        }

        $data = [];
        foreach ($result['d'] as $row) {
            $date = $this->parseBingDate($row['Date'] ?? '');
            $query = strtolower($row['Query'] ?? '');
            if ($date === null || $query === '') {
                continue;
            }

            $dateStr = $date->format('Y-m-d');
            // AvgImpressionPosition = position moyenne d'affichage (équivalent GSC)
            // AvgClickPosition = position moyenne au clic (vaut -1 si aucun clic)
            $rawPosition = $row['AvgImpressionPosition'] ?? $row['AvgClickPosition'] ?? 0;
            $position = max(0, round($rawPosition, 1));

            if (!isset($data[$dateStr])) {
                $data[$dateStr] = [];
            }

            $data[$dateStr][$query] = [
                'clicks' => (int) ($row['Clicks'] ?? 0),
                'impressions' => (int) ($row['Impressions'] ?? 0),
                'position' => $position,
            ];
        }

        return $data;
    }

    /**
     * Récupère les stats de trafic journalières pour un site.
     * Retourne [date] => {clicks, impressions}
     *
     * @return array<string, array{clicks: int, impressions: int}>
     */
    public function fetchDailyTrafficStats(string $siteUrl): array
    {
        $apiKey = $this->getApiKey();
        if ($apiKey === null) {
            return [];
        }

        $url = self::API_BASE_URL . '/GetRankAndTrafficStats?' . http_build_query([
            'apikey' => $apiKey,
            'siteUrl' => $siteUrl,
        ]);

        $result = $this->executeWithRetry('GET', $url);
        if ($result === null || !isset($result['d'])) {
            return [];
        }

        $data = [];
        foreach ($result['d'] as $row) {
            $date = $this->parseBingDate($row['Date'] ?? '');
            if ($date === null) {
                continue;
            }

            $dateStr = $date->format('Y-m-d');
            $data[$dateStr] = [
                'clicks' => (int) ($row['Clicks'] ?? 0),
                'impressions' => (int) ($row['Impressions'] ?? 0),
            ];
        }

        return $data;
    }

    private function getApiKey(): ?string
    {
        $config = $this->bingConfigRepository->findOneBy([]);
        return $config?->getApiKey();
    }

    /**
     * Parse une date Bing au format /Date(timestamp)/.
     */
    private function parseBingDate(string $dateStr): ?\DateTimeImmutable
    {
        if (preg_match('/\/Date\((\d+)([+-]\d+)?\)\//', $dateStr, $matches)) {
            $timestamp = (int) ($matches[1] / 1000);
            return (new \DateTimeImmutable())->setTimestamp($timestamp)->setTime(0, 0, 0);
        }

        // Fallback: essayer un format standard
        try {
            return new \DateTimeImmutable($dateStr);
        } catch (\Exception) {
            return null;
        }
    }

    private function executeWithRetry(string $method, string $url): ?array
    {
        $lastException = null;

        for ($attempt = 0; $attempt < self::MAX_RETRIES; $attempt++) {
            try {
                $response = $this->httpClient->request($method, $url, [
                    'headers' => [
                        'Content-Type' => 'application/json',
                    ],
                ]);

                $statusCode = $response->getStatusCode();

                if ($statusCode === 200) {
                    return $response->toArray();
                }

                if ($statusCode >= 400 && $statusCode < 500 && $statusCode !== 429) {
                    $this->logger->error('Bing API client error', [
                        'status_code' => $statusCode,
                        'response' => $response->getContent(false),
                        'attempt' => $attempt + 1,
                    ]);
                    return null;
                }

                $this->logger->warning('Bing API error, will retry', [
                    'status_code' => $statusCode,
                    'attempt' => $attempt + 1,
                    'max_retries' => self::MAX_RETRIES,
                ]);

            } catch (\Exception $e) {
                $lastException = $e;
                $this->logger->warning('Bing API exception, will retry', [
                    'error' => $e->getMessage(),
                    'attempt' => $attempt + 1,
                    'max_retries' => self::MAX_RETRIES,
                ]);
            }

            if ($attempt < self::MAX_RETRIES - 1) {
                sleep(self::RETRY_DELAYS[$attempt]);
            }
        }

        $this->logger->error('Bing API failed after all retries', [
            'url' => preg_replace('/apikey=[^&]+/', 'apikey=***', $url),
            'last_error' => $lastException?->getMessage(),
        ]);

        return null;
    }
}
