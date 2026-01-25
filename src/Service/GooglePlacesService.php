<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class GooglePlacesService
{
    private const API_URL = 'https://places.googleapis.com/v1/places';
    private const MAX_RETRIES = 3;
    private const RETRY_DELAYS = [1, 2, 4]; // seconds

    private ?string $lastError = null;

    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
        private string $googlePlacesApiKey,
        private string $googlePlaceId,
    ) {}

    /**
     * Retourne la dernière erreur survenue.
     */
    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    /**
     * Efface la dernière erreur.
     */
    public function clearLastError(): void
    {
        $this->lastError = null;
    }

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
        $this->clearLastError();

        if (!$this->isConfigured()) {
            $this->lastError = 'API non configurée. Ajoutez GOOGLE_PLACES_API_KEY et GOOGLE_PLACE_ID dans .env.local';
            $this->logger->error('Google Places API not configured');
            return null;
        }

        $url = self::API_URL . '/' . $this->googlePlaceId . '?languageCode=fr';
        $fieldMask = 'reviews.name,reviews.authorAttribution.displayName,reviews.rating,reviews.originalText.text,reviews.text.text,reviews.publishTime';

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
            // Préférer le texte original (non traduit) si disponible
            $comment = $review['originalText']['text']
                ?? $review['text']['text']
                ?? null;

            $reviews[] = [
                'name' => $review['name'] ?? '',
                'authorName' => $review['authorAttribution']['displayName'] ?? 'Anonyme',
                'rating' => (int) ($review['rating'] ?? 0),
                'comment' => $comment,
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
                    $responseContent = $response->getContent(false);
                    $this->lastError = $this->parseApiError($statusCode, $responseContent);
                    $this->logger->error('Google Places API client error', [
                        'status_code' => $statusCode,
                        'response' => $responseContent,
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

        $errorMessage = $lastException?->getMessage() ?? 'Erreur inconnue après plusieurs tentatives';
        $this->lastError = "Erreur API après {$attempt} tentatives : {$errorMessage}";
        $this->logger->error('Google Places API failed after all retries', [
            'url' => $url,
            'last_error' => $lastException?->getMessage(),
        ]);

        return null;
    }

    /**
     * Parse le message d'erreur de l'API Google Places.
     */
    private function parseApiError(int $statusCode, string $responseContent): string
    {
        try {
            $data = json_decode($responseContent, true);
            if (isset($data['error']['message'])) {
                return sprintf('[%d] %s', $statusCode, $data['error']['message']);
            }
        } catch (\Exception) {
            // Ignore JSON parsing errors
        }

        return match ($statusCode) {
            400 => "Requête invalide (400) - Vérifiez le Place ID",
            401 => "Non autorisé (401) - Vérifiez la clé API",
            403 => "Accès refusé (403) - Vérifiez les permissions de la clé API",
            404 => "Place ID non trouvé (404)",
            default => "Erreur API ({$statusCode})",
        };
    }
}
