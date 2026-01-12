<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class GooglePlacesService
{
    private const API_URL = 'https://places.googleapis.com/v1/places';
    private const MAX_RETRIES = 3;
    private const RETRY_DELAYS = [1, 2, 4]; // seconds

    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
        private string $googlePlacesApiKey,
        private string $googlePlaceId,
    ) {}

    /**
     * Vérifie si le service est configuré.
     */
    public function isConfigured(): bool
    {
        return !empty($this->googlePlacesApiKey) && !empty($this->googlePlaceId);
    }

    /**
     * Récupère les avis depuis Google Places API.
     *
     * @return array<array{
     *     name: string,
     *     authorName: string,
     *     rating: int,
     *     comment: ?string,
     *     publishTime: string
     * }>|null
     */
    public function fetchReviews(): ?array
    {
        if (!$this->isConfigured()) {
            $this->logger->error('Google Places API not configured');
            return null;
        }

        $url = self::API_URL . '/' . $this->googlePlaceId;
        $fieldMask = 'reviews.name,reviews.authorAttribution.displayName,reviews.rating,reviews.text.text,reviews.publishTime';

        $result = $this->executeWithRetry($url, $fieldMask);

        if ($result === null) {
            return null;
        }

        // Parser la réponse
        if (!isset($result['reviews']) || empty($result['reviews'])) {
            $this->logger->info('No reviews found for place', ['placeId' => $this->googlePlaceId]);
            return [];
        }

        $reviews = [];
        foreach ($result['reviews'] as $review) {
            $reviews[] = [
                'name' => $review['name'] ?? '',
                'authorName' => $review['authorAttribution']['displayName'] ?? 'Anonyme',
                'rating' => (int) ($review['rating'] ?? 0),
                'comment' => $review['text']['text'] ?? null,
                'publishTime' => $review['publishTime'] ?? '',
            ];
        }

        $this->logger->info('Fetched reviews from Google Places', [
            'placeId' => $this->googlePlaceId,
            'count' => count($reviews),
        ]);

        return $reviews;
    }

    /**
     * Exécute une requête avec retry et backoff exponentiel.
     */
    private function executeWithRetry(string $url, string $fieldMask): ?array
    {
        $lastException = null;

        for ($attempt = 0; $attempt < self::MAX_RETRIES; $attempt++) {
            try {
                $response = $this->httpClient->request('GET', $url, [
                    'headers' => [
                        'X-Goog-Api-Key' => $this->googlePlacesApiKey,
                        'X-Goog-FieldMask' => $fieldMask,
                    ],
                ]);

                $statusCode = $response->getStatusCode();

                if ($statusCode === 200) {
                    return $response->toArray();
                }

                // Erreur non-retryable (4xx sauf 429)
                if ($statusCode >= 400 && $statusCode < 500 && $statusCode !== 429) {
                    $this->logger->error('Google Places API client error', [
                        'status_code' => $statusCode,
                        'response' => $response->getContent(false),
                        'attempt' => $attempt + 1,
                    ]);
                    return null;
                }

                // Erreur retryable (5xx ou 429)
                $this->logger->warning('Google Places API error, will retry', [
                    'status_code' => $statusCode,
                    'attempt' => $attempt + 1,
                    'max_retries' => self::MAX_RETRIES,
                ]);

            } catch (\Exception $e) {
                $lastException = $e;
                $this->logger->warning('Google Places API exception, will retry', [
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

        $this->logger->error('Google Places API failed after all retries', [
            'url' => $url,
            'last_error' => $lastException?->getMessage(),
        ]);

        return null;
    }
}
