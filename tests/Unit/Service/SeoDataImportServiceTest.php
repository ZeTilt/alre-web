<?php

namespace App\Tests\Unit\Service;

use App\Entity\SeoKeyword;
use App\Repository\SeoKeywordRepository;
use App\Service\GoogleSearchConsoleService;
use App\Service\SeoDataImportService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Tests unitaires pour SeoDataImportService.
 *
 * Couvre:
 * - syncAllKeywords() - synchronise les données GSC
 * - isDataUpToDate() - vérifie si les données sont à jour
 * - getLastSyncDate() - retourne la date de dernière sync
 */
class SeoDataImportServiceTest extends TestCase
{
    private MockObject&GoogleSearchConsoleService $gscService;
    private MockObject&SeoKeywordRepository $keywordRepository;
    private MockObject&EntityManagerInterface $entityManager;
    private MockObject&LoggerInterface $logger;

    protected function setUp(): void
    {
        $this->gscService = $this->createMock(GoogleSearchConsoleService::class);
        $this->keywordRepository = $this->createMock(SeoKeywordRepository::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
    }

    private function createService(): SeoDataImportService
    {
        return new SeoDataImportService(
            $this->gscService,
            $this->keywordRepository,
            $this->entityManager,
            $this->logger
        );
    }

    private function createKeyword(string $keyword, ?\DateTimeImmutable $lastSyncAt = null): SeoKeyword
    {
        $entity = new SeoKeyword();
        $entity->setKeyword($keyword);
        $entity->setIsActive(true);
        if ($lastSyncAt) {
            $entity->setLastSyncAt($lastSyncAt);
        }
        return $entity;
    }

    // ===== syncAllKeywords() TESTS =====

    public function testSyncAllKeywordsReturnsErrorWhenGscNotAvailable(): void
    {
        $service = $this->createService();

        $this->gscService->method('isAvailable')->willReturn(false);

        $result = $service->syncAllKeywords();

        $this->assertEquals(0, $result['synced']);
        $this->assertEquals('Google Search Console non connecté', $result['message']);
    }

    public function testSyncAllKeywordsReturnsUpToDateWhenNoKeywordsNeedSync(): void
    {
        $service = $this->createService();

        $this->gscService->method('isAvailable')->willReturn(true);
        $this->keywordRepository->method('findKeywordsNeedingSync')->willReturn([]);

        $result = $service->syncAllKeywords();

        $this->assertEquals(0, $result['synced']);
        $this->assertStringContainsString('à jour', $result['message']);
    }

    public function testSyncAllKeywordsSyncsKeywordsSuccessfully(): void
    {
        $service = $this->createService();

        $keyword1 = $this->createKeyword('création site web');
        $keyword2 = $this->createKeyword('développeur symfony');

        $this->gscService->method('isAvailable')->willReturn(true);
        $this->keywordRepository->method('findKeywordsNeedingSync')->willReturn([$keyword1, $keyword2]);
        $this->gscService->method('fetchAllKeywordsData')->willReturn([
            'création site web' => ['position' => 5.0, 'clicks' => 20, 'impressions' => 400],
            'développeur symfony' => ['position' => 8.0, 'clicks' => 10, 'impressions' => 200],
        ]);

        $this->entityManager->expects($this->exactly(2))->method('persist');
        $this->entityManager->expects($this->once())->method('flush');

        $result = $service->syncAllKeywords();

        $this->assertEquals(2, $result['synced']);
        $this->assertEquals(0, $result['skipped']);
        $this->assertEquals(0, $result['errors']);
    }

    public function testSyncAllKeywordsSkipsKeywordsNotFoundInGsc(): void
    {
        $service = $this->createService();

        $keyword = $this->createKeyword('mot clé inconnu');

        $this->gscService->method('isAvailable')->willReturn(true);
        $this->keywordRepository->method('findKeywordsNeedingSync')->willReturn([$keyword]);
        $this->gscService->method('fetchAllKeywordsData')->willReturn([]); // Pas de données

        $result = $service->syncAllKeywords();

        $this->assertEquals(0, $result['synced']);
        $this->assertEquals(1, $result['skipped']);
    }

    public function testSyncAllKeywordsForcesResyncWithForceFlag(): void
    {
        $service = $this->createService();

        $keyword = $this->createKeyword('test keyword', new \DateTimeImmutable()); // Déjà synced récemment

        // Avec force=true, on utilise findActiveKeywords au lieu de findKeywordsNeedingSync
        $this->gscService->method('isAvailable')->willReturn(true);
        $this->keywordRepository->method('findActiveKeywords')->willReturn([$keyword]);
        $this->gscService->method('fetchAllKeywordsData')->willReturn([
            'test keyword' => ['position' => 3.0, 'clicks' => 50, 'impressions' => 1000],
        ]);

        $result = $service->syncAllKeywords(force: true);

        $this->assertEquals(1, $result['synced']);
    }

    public function testSyncAllKeywordsMatchesKeywordCaseInsensitive(): void
    {
        $service = $this->createService();

        $keyword = $this->createKeyword('Création Site Web'); // Avec majuscules

        $this->gscService->method('isAvailable')->willReturn(true);
        $this->keywordRepository->method('findKeywordsNeedingSync')->willReturn([$keyword]);
        $this->gscService->method('fetchAllKeywordsData')->willReturn([
            'création site web' => ['position' => 5.0, 'clicks' => 20, 'impressions' => 400], // lowercase
        ]);

        $this->entityManager->expects($this->once())->method('persist');

        $result = $service->syncAllKeywords();

        $this->assertEquals(1, $result['synced']);
    }

    public function testSyncAllKeywordsMatchesPartialKeyword(): void
    {
        $service = $this->createService();

        $keyword = $this->createKeyword('création site'); // Partial

        $this->gscService->method('isAvailable')->willReturn(true);
        $this->keywordRepository->method('findKeywordsNeedingSync')->willReturn([$keyword]);
        $this->gscService->method('fetchAllKeywordsData')->willReturn([
            'création site web vannes' => ['position' => 5.0, 'clicks' => 20, 'impressions' => 400],
        ]);

        $this->entityManager->expects($this->once())->method('persist');

        $result = $service->syncAllKeywords();

        $this->assertEquals(1, $result['synced']);
    }

    public function testSyncAllKeywordsMatchesWithAccents(): void
    {
        $service = $this->createService();

        $keyword = $this->createKeyword('creation site web'); // Sans accent

        $this->gscService->method('isAvailable')->willReturn(true);
        $this->keywordRepository->method('findKeywordsNeedingSync')->willReturn([$keyword]);
        $this->gscService->method('fetchAllKeywordsData')->willReturn([
            'création site web' => ['position' => 5.0, 'clicks' => 20, 'impressions' => 400], // Avec accent
        ]);

        $this->entityManager->expects($this->once())->method('persist');

        $result = $service->syncAllKeywords();

        $this->assertEquals(1, $result['synced']);
    }

    // ===== isDataUpToDate() TESTS =====

    public function testIsDataUpToDateReturnsTrueWhenNoKeywordsNeedSync(): void
    {
        $service = $this->createService();

        $this->keywordRepository->method('findKeywordsNeedingSync')->willReturn([]);

        $this->assertTrue($service->isDataUpToDate());
    }

    public function testIsDataUpToDateReturnsFalseWhenKeywordsNeedSync(): void
    {
        $service = $this->createService();

        $keyword = $this->createKeyword('test');
        $this->keywordRepository->method('findKeywordsNeedingSync')->willReturn([$keyword]);

        $this->assertFalse($service->isDataUpToDate());
    }

    // ===== getLastSyncDate() TESTS =====

    public function testGetLastSyncDateReturnsNullWhenNoKeywords(): void
    {
        $service = $this->createService();

        $this->keywordRepository->method('findActiveKeywords')->willReturn([]);

        $this->assertNull($service->getLastSyncDate());
    }

    public function testGetLastSyncDateReturnsLatestDate(): void
    {
        $service = $this->createService();

        $older = new \DateTimeImmutable('2026-01-10');
        $newer = new \DateTimeImmutable('2026-01-12');

        $keyword1 = $this->createKeyword('keyword1', $older);
        $keyword2 = $this->createKeyword('keyword2', $newer);

        $this->keywordRepository->method('findActiveKeywords')->willReturn([$keyword1, $keyword2]);

        $lastSync = $service->getLastSyncDate();

        $this->assertEquals($newer, $lastSync);
    }

    public function testGetLastSyncDateReturnsNullWhenNoKeywordsSynced(): void
    {
        $service = $this->createService();

        $keyword = $this->createKeyword('test'); // No lastSyncAt

        $this->keywordRepository->method('findActiveKeywords')->willReturn([$keyword]);

        $this->assertNull($service->getLastSyncDate());
    }

    // ===== importNewKeywords() TESTS =====

    public function testImportNewKeywordsReturnsErrorWhenGscNotAvailable(): void
    {
        $service = $this->createService();

        $this->gscService->method('isAvailable')->willReturn(false);

        $result = $service->importNewKeywords();

        $this->assertEquals(0, $result['imported']);
        $this->assertEquals('Google Search Console non connecté', $result['message']);
    }

    public function testImportNewKeywordsReturnsEmptyWhenNoGscData(): void
    {
        $service = $this->createService();

        $this->gscService->method('isAvailable')->willReturn(true);
        $this->gscService->method('fetchAllKeywordsData')->willReturn([]);

        $result = $service->importNewKeywords();

        $this->assertEquals(0, $result['imported']);
        $this->assertEquals(0, $result['total_gsc']);
        $this->assertStringContainsString('Aucune donnée', $result['message']);
    }

    public function testImportNewKeywordsImportsAllWhenLessThan100Keywords(): void
    {
        $service = $this->createService();

        $this->gscService->method('isAvailable')->willReturn(true);
        $this->gscService->method('fetchAllKeywordsData')->willReturn([
            'nouveau mot clé' => ['position' => 10.0, 'clicks' => 5, 'impressions' => 50],
            'autre mot clé' => ['position' => 20.0, 'clicks' => 1, 'impressions' => 5], // Peu d'impressions
        ]);
        $this->keywordRepository->method('findAllKeywordStrings')->willReturn([]);

        $this->entityManager->expects($this->exactly(2))->method('persist');
        $this->entityManager->expects($this->once())->method('flush');

        $result = $service->importNewKeywords();

        $this->assertEquals(2, $result['imported']);
        $this->assertEquals(2, $result['total_gsc']);
        $this->assertEquals(0, $result['min_impressions']); // No filtering below 100
    }

    public function testImportNewKeywordsFiltersAt10ImpressionsWhenBetween100And1000(): void
    {
        $service = $this->createService();

        // Simulate 100-1000 keywords
        $gscData = [];
        for ($i = 0; $i < 150; $i++) {
            $gscData["keyword $i"] = [
                'position' => 10.0,
                'clicks' => 1,
                'impressions' => $i < 100 ? 5 : 15, // First 100 have low impressions, rest have higher
            ];
        }

        $this->gscService->method('isAvailable')->willReturn(true);
        $this->gscService->method('fetchAllKeywordsData')->willReturn($gscData);
        $this->keywordRepository->method('findAllKeywordStrings')->willReturn([]);

        // Only 50 keywords with >= 10 impressions should be imported
        $this->entityManager->expects($this->exactly(50))->method('persist');
        $this->entityManager->expects($this->once())->method('flush');

        $result = $service->importNewKeywords();

        $this->assertEquals(50, $result['imported']);
        $this->assertEquals(150, $result['total_gsc']);
        $this->assertEquals(10, $result['min_impressions']);
    }

    public function testImportNewKeywordsFiltersAt100ImpressionsWhenOver1000(): void
    {
        $service = $this->createService();

        // Simulate > 1000 keywords
        $gscData = [];
        for ($i = 0; $i < 1100; $i++) {
            $gscData["keyword $i"] = [
                'position' => 10.0,
                'clicks' => 1,
                'impressions' => $i < 1000 ? 50 : 150, // First 1000 have 50 impressions, rest have 150
            ];
        }

        $this->gscService->method('isAvailable')->willReturn(true);
        $this->gscService->method('fetchAllKeywordsData')->willReturn($gscData);
        $this->keywordRepository->method('findAllKeywordStrings')->willReturn([]);

        // Only 100 keywords with >= 100 impressions should be imported
        $this->entityManager->expects($this->exactly(100))->method('persist');
        $this->entityManager->expects($this->once())->method('flush');

        $result = $service->importNewKeywords();

        $this->assertEquals(100, $result['imported']);
        $this->assertEquals(1100, $result['total_gsc']);
        $this->assertEquals(100, $result['min_impressions']);
    }

    public function testImportNewKeywordsSkipsExistingKeywords(): void
    {
        $service = $this->createService();

        $this->gscService->method('isAvailable')->willReturn(true);
        $this->gscService->method('fetchAllKeywordsData')->willReturn([
            'existing keyword' => ['position' => 10.0, 'clicks' => 5, 'impressions' => 50],
            'new keyword' => ['position' => 20.0, 'clicks' => 10, 'impressions' => 100],
        ]);
        $this->keywordRepository->method('findAllKeywordStrings')->willReturn(['existing keyword']);

        // Only 1 keyword should be imported (new keyword)
        $this->entityManager->expects($this->once())->method('persist');
        $this->entityManager->expects($this->once())->method('flush');

        $result = $service->importNewKeywords();

        $this->assertEquals(1, $result['imported']);
    }

    public function testImportNewKeywordsDoesNotFlushWhenNoImports(): void
    {
        $service = $this->createService();

        $this->gscService->method('isAvailable')->willReturn(true);
        $this->gscService->method('fetchAllKeywordsData')->willReturn([
            'existing keyword' => ['position' => 10.0, 'clicks' => 5, 'impressions' => 50],
        ]);
        $this->keywordRepository->method('findAllKeywordStrings')->willReturn(['existing keyword']);

        $this->entityManager->expects($this->never())->method('persist');
        $this->entityManager->expects($this->never())->method('flush');

        $result = $service->importNewKeywords();

        $this->assertEquals(0, $result['imported']);
    }

    // ===== deactivateMissingKeywords() TESTS =====

    public function testDeactivateMissingKeywordsReturnsDeactivatedCount(): void
    {
        $service = $this->createService();

        $this->keywordRepository->method('deactivateAutoKeywordsNotSeenSince')->willReturn(5);

        $result = $service->deactivateMissingKeywords();

        $this->assertEquals(5, $result['deactivated']);
        $this->assertStringContainsString('5 mot(s)-clé(s) désactivé(s)', $result['message']);
    }

    public function testDeactivateMissingKeywordsWithCustomThreshold(): void
    {
        $service = $this->createService();

        $this->keywordRepository->method('deactivateAutoKeywordsNotSeenSince')->willReturn(3);

        $result = $service->deactivateMissingKeywords(daysThreshold: 60);

        $this->assertEquals(3, $result['deactivated']);
        $this->assertStringContainsString('60 jours', $result['message']);
    }

    public function testDeactivateMissingKeywordsLogsWhenDeactivating(): void
    {
        $service = $this->createService();

        $this->keywordRepository->method('deactivateAutoKeywordsNotSeenSince')->willReturn(2);

        $this->logger->expects($this->once())
            ->method('info')
            ->with('Deactivated missing keywords', $this->anything());

        $service->deactivateMissingKeywords();
    }

    public function testDeactivateMissingKeywordsDoesNotLogWhenNothingDeactivated(): void
    {
        $service = $this->createService();

        $this->keywordRepository->method('deactivateAutoKeywordsNotSeenSince')->willReturn(0);

        $this->logger->expects($this->never())->method('info');

        $result = $service->deactivateMissingKeywords();

        $this->assertEquals(0, $result['deactivated']);
    }
}
