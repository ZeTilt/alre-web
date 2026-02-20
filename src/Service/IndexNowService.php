<?php

namespace App\Service;

use App\Repository\BingConfigRepository;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class IndexNowService
{
    private const INDEXNOW_URL = 'https://api.indexnow.org/IndexNow';

    public function __construct(
        private HttpClientInterface $httpClient,
        private BingConfigRepository $bingConfigRepository,
        private LoggerInterface $logger,
        private string $siteHost = 'www.alre-web.bzh',
    ) {}

    public function isAvailable(): bool
    {
        $config = $this->bingConfigRepository->findOneBy([]);
        return $config !== null && !empty($config->getIndexNowKey());
    }

    /**
     * Soumet une liste d'URLs a IndexNow.
     */
    public function submitUrls(array $urls): bool
    {
        $config = $this->bingConfigRepository->findOneBy([]);
        if ($config === null || empty($config->getIndexNowKey())) {
            $this->logger->info('IndexNow not configured, skipping URL submission');
            return false;
        }

        $key = $config->getIndexNowKey();

        if (empty($urls)) {
            return true;
        }

        try {
            $payload = [
                'host' => $this->siteHost,
                'key' => $key,
                'keyLocation' => 'https://' . $this->siteHost . '/' . $key . '.txt',
                'urlList' => $urls,
            ];

            $response = $this->httpClient->request('POST', self::INDEXNOW_URL, [
                'json' => $payload,
                'headers' => [
                    'Content-Type' => 'application/json; charset=utf-8',
                ],
            ]);

            $statusCode = $response->getStatusCode();

            // 200 = OK, 202 = Accepted
            if ($statusCode === 200 || $statusCode === 202) {
                $this->logger->info('IndexNow: URLs submitted successfully', [
                    'count' => count($urls),
                    'status' => $statusCode,
                ]);
                return true;
            }

            $this->logger->warning('IndexNow: unexpected status code', [
                'status' => $statusCode,
                'count' => count($urls),
                'response' => $response->getContent(false),
            ]);
            return false;

        } catch (\Exception $e) {
            $this->logger->error('IndexNow: submission failed', [
                'error' => $e->getMessage(),
                'count' => count($urls),
            ]);
            return false;
        }
    }

    /**
     * Soumet une seule URL a IndexNow.
     */
    public function submitUrl(string $url): bool
    {
        return $this->submitUrls([$url]);
    }

    /**
     * Retourne la cle IndexNow (pour servir le fichier {key}.txt).
     */
    public function getKey(): ?string
    {
        $config = $this->bingConfigRepository->findOneBy([]);
        return $config?->getIndexNowKey();
    }
}
