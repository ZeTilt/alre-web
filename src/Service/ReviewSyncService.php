<?php

namespace App\Service;

use App\Entity\GoogleReview;
use App\Repository\GoogleReviewRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class ReviewSyncService
{
    private const CACHE_TTL_HOURS = 12;

    public function __construct(
        private GooglePlacesService $placesService,
        private GoogleReviewRepository $reviewRepository,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {}

    /**
     * Synchronise les avis depuis Google Places.
     *
     * @return array{created: int, updated: int, unchanged: int, errors: int, message: string}
     */
    public function syncReviews(bool $force = false): array
    {
        if (!$this->placesService->isConfigured()) {
            return [
                'created' => 0,
                'updated' => 0,
                'unchanged' => 0,
                'errors' => 0,
                'message' => 'Google Places API non configurée',
            ];
        }

        // Vérifier le cache TTL
        if (!$force && $this->reviewRepository->isDataFresh(self::CACHE_TTL_HOURS)) {
            return [
                'created' => 0,
                'updated' => 0,
                'unchanged' => 0,
                'errors' => 0,
                'message' => 'Les avis sont à jour (dernière sync il y a moins de 12h)',
            ];
        }

        // Récupérer les avis depuis l'API
        $reviewsData = $this->placesService->fetchReviews();

        if ($reviewsData === null) {
            $apiError = $this->placesService->getLastError();
            return [
                'created' => 0,
                'updated' => 0,
                'unchanged' => 0,
                'errors' => 1,
                'message' => $apiError ?? 'Erreur lors de la récupération des avis',
            ];
        }

        if (empty($reviewsData)) {
            return [
                'created' => 0,
                'updated' => 0,
                'unchanged' => 0,
                'errors' => 0,
                'message' => 'Aucun avis trouvé sur Google Places',
            ];
        }

        $created = 0;
        $updated = 0;
        $unchanged = 0;
        $errors = 0;

        foreach ($reviewsData as $data) {
            try {
                $result = $this->processReview($data);
                match ($result) {
                    'created' => $created++,
                    'updated' => $updated++,
                    'unchanged' => $unchanged++,
                    default => null,
                };
            } catch (\Exception $e) {
                $this->logger->error('Error processing review', [
                    'data' => $data,
                    'error' => $e->getMessage(),
                ]);
                $errors++;
            }
        }

        $this->entityManager->flush();

        $message = $this->buildResultMessage($created, $updated, $unchanged, $errors);

        $this->logger->info('Reviews sync completed', [
            'created' => $created,
            'updated' => $updated,
            'unchanged' => $unchanged,
            'errors' => $errors,
        ]);

        return [
            'created' => $created,
            'updated' => $updated,
            'unchanged' => $unchanged,
            'errors' => $errors,
            'message' => $message,
        ];
    }

    /**
     * Traite un avis individuel.
     *
     * @return string 'created'|'updated'|'unchanged'
     */
    private function processReview(array $data): string
    {
        $googleReviewId = $this->extractReviewId($data['name']);

        if (!$googleReviewId) {
            throw new \RuntimeException('Unable to extract review ID from: ' . $data['name']);
        }

        // Chercher un avis existant
        $existingReview = $this->reviewRepository->findByGoogleReviewId($googleReviewId);

        if ($existingReview) {
            return $this->updateExistingReview($existingReview, $data);
        }

        return $this->createNewReview($googleReviewId, $data);
    }

    /**
     * Crée un nouvel avis.
     */
    private function createNewReview(string $googleReviewId, array $data): string
    {
        $review = new GoogleReview();
        $review->setGoogleReviewId($googleReviewId);
        $review->setAuthorName($data['authorName']);
        $review->setRating($data['rating']);
        $review->setComment($data['comment']);
        $review->setReviewDate($this->parseDate($data['publishTime']));
        // isApproved reste false par défaut

        $this->entityManager->persist($review);

        $this->logger->info('Created new review', [
            'googleReviewId' => $googleReviewId,
            'authorName' => $data['authorName'],
            'rating' => $data['rating'],
        ]);

        return 'created';
    }

    /**
     * Met à jour un avis existant si nécessaire.
     */
    private function updateExistingReview(GoogleReview $review, array $data): string
    {
        $hasChanges = false;

        // Comparer et mettre à jour les champs (sauf isApproved)
        if ($review->getAuthorName() !== $data['authorName']) {
            $review->setAuthorName($data['authorName']);
            $hasChanges = true;
        }

        if ($review->getRating() !== $data['rating']) {
            $review->setRating($data['rating']);
            $hasChanges = true;
        }

        if ($review->getComment() !== $data['comment']) {
            $review->setComment($data['comment']);
            $hasChanges = true;
        }

        $newDate = $this->parseDate($data['publishTime']);
        if ($review->getReviewDate()->format('Y-m-d H:i:s') !== $newDate->format('Y-m-d H:i:s')) {
            $review->setReviewDate($newDate);
            $hasChanges = true;
        }

        if ($hasChanges) {
            $this->logger->info('Updated existing review', [
                'googleReviewId' => $review->getGoogleReviewId(),
            ]);
            return 'updated';
        }

        return 'unchanged';
    }

    /**
     * Extrait l'ID de l'avis depuis le nom complet Google.
     * Format: places/{placeId}/reviews/{reviewId}
     */
    private function extractReviewId(string $name): ?string
    {
        if (preg_match('/reviews\/([^\/]+)$/', $name, $matches)) {
            return $matches[1];
        }

        // Si le format ne correspond pas, utiliser le nom complet comme ID
        return $name ?: null;
    }

    /**
     * Parse une date ISO 8601.
     */
    private function parseDate(string $dateString): \DateTimeImmutable
    {
        try {
            return new \DateTimeImmutable($dateString);
        } catch (\Exception) {
            return new \DateTimeImmutable();
        }
    }

    /**
     * Construit le message de résultat.
     */
    private function buildResultMessage(int $created, int $updated, int $unchanged, int $errors): string
    {
        $parts = [];

        if ($created > 0) {
            $parts[] = $created . ' nouveau' . ($created > 1 ? 'x' : '');
        }

        if ($updated > 0) {
            $parts[] = $updated . ' mis à jour';
        }

        if ($unchanged > 0 && $created === 0 && $updated === 0) {
            $parts[] = 'Tous les avis sont à jour';
        }

        if ($errors > 0) {
            $parts[] = $errors . ' erreur' . ($errors > 1 ? 's' : '');
        }

        if (empty($parts)) {
            return 'Synchronisation terminée';
        }

        return implode(', ', $parts);
    }

    /**
     * Retourne les statistiques des avis.
     */
    public function getStats(): array
    {
        return $this->reviewRepository->getStats();
    }

    /**
     * Vérifie si les données sont à jour.
     */
    public function isDataFresh(): bool
    {
        return $this->reviewRepository->isDataFresh(self::CACHE_TTL_HOURS);
    }

    /**
     * Retourne la dernière erreur de l'API Google Places.
     */
    public function getLastApiError(): ?string
    {
        return $this->placesService->getLastError();
    }
}
