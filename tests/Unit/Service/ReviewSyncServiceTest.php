<?php

namespace App\Tests\Unit\Service;

use App\Entity\GoogleReview;
use App\Repository\GoogleReviewRepository;
use App\Service\GooglePlacesService;
use App\Service\ReviewSyncService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Tests unitaires pour ReviewSyncService.
 *
 * Couvre:
 * - syncReviews() - synchronise les avis depuis Google Places
 * - getStats() - retourne les statistiques des avis
 * - isDataFresh() - vérifie si les données sont à jour
 */
class ReviewSyncServiceTest extends TestCase
{
    private MockObject&GooglePlacesService $placesService;
    private MockObject&GoogleReviewRepository $reviewRepository;
    private MockObject&EntityManagerInterface $entityManager;
    private MockObject&LoggerInterface $logger;

    protected function setUp(): void
    {
        $this->placesService = $this->createMock(GooglePlacesService::class);
        $this->reviewRepository = $this->createMock(GoogleReviewRepository::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
    }

    private function createService(): ReviewSyncService
    {
        return new ReviewSyncService(
            $this->placesService,
            $this->reviewRepository,
            $this->entityManager,
            $this->logger
        );
    }

    private function createReviewData(string $reviewId = 'abc123', string $author = 'Jean Dupont', int $rating = 5, ?string $comment = 'Excellent !'): array
    {
        return [
            'name' => 'places/123/reviews/' . $reviewId,
            'authorName' => $author,
            'rating' => $rating,
            'comment' => $comment,
            'publishTime' => '2026-01-10T14:30:00Z',
        ];
    }

    // ===== syncReviews() TESTS =====

    public function testSyncReviewsReturnsErrorWhenNotConfigured(): void
    {
        $service = $this->createService();

        $this->placesService->method('isConfigured')->willReturn(false);

        $result = $service->syncReviews();

        $this->assertEquals(0, $result['created']);
        $this->assertStringContainsString('non configurée', $result['message']);
    }

    public function testSyncReviewsReturnsUpToDateWhenDataFresh(): void
    {
        $service = $this->createService();

        $this->placesService->method('isConfigured')->willReturn(true);
        $this->reviewRepository->method('isDataFresh')->willReturn(true);

        $result = $service->syncReviews();

        $this->assertEquals(0, $result['created']);
        $this->assertStringContainsString('à jour', $result['message']);
    }

    public function testSyncReviewsCreatesNewReviews(): void
    {
        $service = $this->createService();

        $this->placesService->method('isConfigured')->willReturn(true);
        $this->reviewRepository->method('isDataFresh')->willReturn(false);
        $this->placesService->method('fetchReviews')->willReturn([
            $this->createReviewData('abc123', 'Jean Dupont', 5),
            $this->createReviewData('def456', 'Marie Martin', 4),
        ]);
        $this->reviewRepository->method('findByGoogleReviewId')->willReturn(null);

        $this->entityManager->expects($this->exactly(2))->method('persist');
        $this->entityManager->expects($this->once())->method('flush');

        $result = $service->syncReviews();

        $this->assertEquals(2, $result['created']);
        $this->assertEquals(0, $result['updated']);
        $this->assertEquals(0, $result['unchanged']);
    }

    public function testSyncReviewsUpdatesExistingReviews(): void
    {
        $service = $this->createService();

        $existingReview = new GoogleReview();
        $existingReview->setGoogleReviewId('abc123')
                       ->setAuthorName('Jean Dupont')
                       ->setRating(4) // Different from new data
                       ->setComment('Bien')
                       ->setReviewDate(new \DateTimeImmutable('2026-01-10T14:30:00Z'));

        $this->placesService->method('isConfigured')->willReturn(true);
        $this->reviewRepository->method('isDataFresh')->willReturn(false);
        $this->placesService->method('fetchReviews')->willReturn([
            $this->createReviewData('abc123', 'Jean Dupont', 5, 'Excellent !'), // Rating changed
        ]);
        $this->reviewRepository->method('findByGoogleReviewId')->willReturn($existingReview);

        $result = $service->syncReviews();

        $this->assertEquals(0, $result['created']);
        $this->assertEquals(1, $result['updated']);
        $this->assertEquals(5, $existingReview->getRating()); // Updated
    }

    public function testSyncReviewsDoesNotUpdateUnchangedReviews(): void
    {
        $service = $this->createService();

        $existingReview = new GoogleReview();
        $existingReview->setGoogleReviewId('abc123')
                       ->setAuthorName('Jean Dupont')
                       ->setRating(5)
                       ->setComment('Excellent !')
                       ->setReviewDate(new \DateTimeImmutable('2026-01-10T14:30:00Z'));

        $this->placesService->method('isConfigured')->willReturn(true);
        $this->reviewRepository->method('isDataFresh')->willReturn(false);
        $this->placesService->method('fetchReviews')->willReturn([
            $this->createReviewData('abc123', 'Jean Dupont', 5, 'Excellent !'), // Same data
        ]);
        $this->reviewRepository->method('findByGoogleReviewId')->willReturn($existingReview);

        $result = $service->syncReviews();

        $this->assertEquals(0, $result['created']);
        $this->assertEquals(0, $result['updated']);
        $this->assertEquals(1, $result['unchanged']);
    }

    public function testSyncReviewsForcesResyncWithForceFlag(): void
    {
        $service = $this->createService();

        $this->placesService->method('isConfigured')->willReturn(true);
        $this->reviewRepository->method('isDataFresh')->willReturn(true); // Fresh but force=true
        $this->placesService->method('fetchReviews')->willReturn([
            $this->createReviewData(),
        ]);
        $this->reviewRepository->method('findByGoogleReviewId')->willReturn(null);

        $result = $service->syncReviews(force: true);

        $this->assertEquals(1, $result['created']);
    }

    public function testSyncReviewsReturnsErrorWhenApiFails(): void
    {
        $service = $this->createService();

        $this->placesService->method('isConfigured')->willReturn(true);
        $this->reviewRepository->method('isDataFresh')->willReturn(false);
        $this->placesService->method('fetchReviews')->willReturn(null); // API error

        $result = $service->syncReviews();

        $this->assertEquals(1, $result['errors']);
        $this->assertStringContainsString('Erreur', $result['message']);
    }

    public function testSyncReviewsReturnsEmptyWhenNoReviews(): void
    {
        $service = $this->createService();

        $this->placesService->method('isConfigured')->willReturn(true);
        $this->reviewRepository->method('isDataFresh')->willReturn(false);
        $this->placesService->method('fetchReviews')->willReturn([]); // No reviews

        $result = $service->syncReviews();

        $this->assertEquals(0, $result['created']);
        $this->assertStringContainsString('Aucun avis', $result['message']);
    }

    public function testSyncReviewsDoesNotResetIsApproved(): void
    {
        $service = $this->createService();

        $existingReview = new GoogleReview();
        $existingReview->setGoogleReviewId('abc123')
                       ->setAuthorName('Jean Dupont')
                       ->setRating(4) // Will be updated
                       ->setComment('Excellent !')
                       ->setReviewDate(new \DateTimeImmutable('2026-01-10T14:30:00Z'))
                       ->setIsApproved(true); // Already approved

        $this->placesService->method('isConfigured')->willReturn(true);
        $this->reviewRepository->method('isDataFresh')->willReturn(false);
        $this->placesService->method('fetchReviews')->willReturn([
            $this->createReviewData('abc123', 'Jean Dupont', 5, 'Excellent !'),
        ]);
        $this->reviewRepository->method('findByGoogleReviewId')->willReturn($existingReview);

        $service->syncReviews();

        // isApproved should NOT be reset
        $this->assertTrue($existingReview->isApproved());
    }

    public function testSyncReviewsExtractsReviewIdFromName(): void
    {
        $service = $this->createService();

        $this->placesService->method('isConfigured')->willReturn(true);
        $this->reviewRepository->method('isDataFresh')->willReturn(false);
        $this->placesService->method('fetchReviews')->willReturn([
            [
                'name' => 'places/ChIJxyz123/reviews/AbCdEf789',
                'authorName' => 'Test',
                'rating' => 5,
                'comment' => 'Test',
                'publishTime' => '2026-01-01T00:00:00Z',
            ],
        ]);

        $this->reviewRepository
            ->expects($this->once())
            ->method('findByGoogleReviewId')
            ->with('AbCdEf789') // Extracted from name
            ->willReturn(null);

        $this->entityManager->expects($this->once())->method('persist');

        $service->syncReviews();
    }

    // ===== getStats() TESTS =====

    public function testGetStatsReturnsRepositoryStats(): void
    {
        $service = $this->createService();

        $expectedStats = [
            'averageRating' => 4.5,
            'totalApproved' => 10,
            'totalPending' => 3,
            'totalRejected' => 1,
        ];

        $this->reviewRepository->method('getStats')->willReturn($expectedStats);

        $this->assertEquals($expectedStats, $service->getStats());
    }

    // ===== isDataFresh() TESTS =====

    public function testIsDataFreshDelegatesToRepository(): void
    {
        $service = $this->createService();

        $this->reviewRepository->method('isDataFresh')->willReturn(true);

        $this->assertTrue($service->isDataFresh());
    }

    public function testIsDataFreshReturnsFalseWhenStale(): void
    {
        $service = $this->createService();

        $this->reviewRepository->method('isDataFresh')->willReturn(false);

        $this->assertFalse($service->isDataFresh());
    }

    // ===== MESSAGE BUILDING TESTS =====

    public function testSyncReviewsBuildsCorrectMessageForMultipleNew(): void
    {
        $service = $this->createService();

        $this->placesService->method('isConfigured')->willReturn(true);
        $this->reviewRepository->method('isDataFresh')->willReturn(false);
        $this->placesService->method('fetchReviews')->willReturn([
            $this->createReviewData('abc123'),
            $this->createReviewData('def456'),
            $this->createReviewData('ghi789'),
        ]);
        $this->reviewRepository->method('findByGoogleReviewId')->willReturn(null);

        $result = $service->syncReviews();

        $this->assertStringContainsString('3 nouveaux', $result['message']);
    }

    public function testSyncReviewsBuildsCorrectMessageForSingleNew(): void
    {
        $service = $this->createService();

        $this->placesService->method('isConfigured')->willReturn(true);
        $this->reviewRepository->method('isDataFresh')->willReturn(false);
        $this->placesService->method('fetchReviews')->willReturn([
            $this->createReviewData('abc123'),
        ]);
        $this->reviewRepository->method('findByGoogleReviewId')->willReturn(null);

        $result = $service->syncReviews();

        $this->assertStringContainsString('1 nouveau', $result['message']);
        $this->assertStringNotContainsString('nouveaux', $result['message']);
    }
}
