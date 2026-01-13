<?php

namespace App\Tests\Unit\Command;

use App\Command\SeoSyncCommand;
use App\Service\ReviewSyncService;
use App\Service\SeoDataImportService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Tests unitaires pour SeoSyncCommand.
 *
 * Couvre:
 * - Synchronisation complète (keywords + reviews)
 * - Option --keywords-only
 * - Option --reviews-only
 * - Option --force
 * - Gestion des erreurs
 */
class SeoSyncCommandTest extends TestCase
{
    private MockObject&SeoDataImportService $seoImportService;
    private MockObject&ReviewSyncService $reviewSyncService;

    protected function setUp(): void
    {
        $this->seoImportService = $this->createMock(SeoDataImportService::class);
        $this->reviewSyncService = $this->createMock(ReviewSyncService::class);
    }

    private function createCommandTester(): CommandTester
    {
        $command = new SeoSyncCommand(
            $this->seoImportService,
            $this->reviewSyncService
        );

        $application = new Application();
        $application->add($command);

        return new CommandTester($application->find('app:seo-sync'));
    }

    private function mockSuccessfulGscSync(int $synced = 5, int $skipped = 1, int $errors = 0): void
    {
        $this->seoImportService->method('syncAllKeywords')->willReturn([
            'synced' => $synced,
            'skipped' => $skipped,
            'errors' => $errors,
            'message' => $errors > 0 ? 'Erreur lors de la sync' : 'Synchronisation réussie',
        ]);

        // Mock the new import and cleanup methods
        $this->seoImportService->method('importNewKeywords')->willReturn([
            'imported' => 0,
            'total_gsc' => 10,
            'min_impressions' => 0,
            'message' => 'Aucun nouveau mot-clé importé',
        ]);

        $this->seoImportService->method('deactivateMissingKeywords')->willReturn([
            'deactivated' => 0,
            'message' => '0 mot(s)-clé(s) désactivé(s) (absents depuis 30 jours)',
        ]);
    }

    private function mockSuccessfulReviewSync(int $created = 2, int $updated = 1, int $unchanged = 5, int $errors = 0): void
    {
        $this->reviewSyncService->method('syncReviews')->willReturn([
            'created' => $created,
            'updated' => $updated,
            'unchanged' => $unchanged,
            'errors' => $errors,
            'message' => $errors > 0 ? 'Erreur lors de la sync' : 'Synchronisation réussie',
        ]);
    }

    // ===== FULL SYNC TESTS =====

    public function testExecuteSyncsBothServicesSuccessfully(): void
    {
        $this->mockSuccessfulGscSync();
        $this->mockSuccessfulReviewSync();

        $this->seoImportService->expects($this->once())->method('syncAllKeywords');
        $this->reviewSyncService->expects($this->once())->method('syncReviews');

        $commandTester = $this->createCommandTester();
        $commandTester->execute([]);

        $this->assertEquals(Command::SUCCESS, $commandTester->getStatusCode());
        $this->assertStringContainsString('Synchronisation SEO quotidienne', $commandTester->getDisplay());
    }

    public function testExecuteReturnsFailureWhenGscHasErrors(): void
    {
        $this->mockSuccessfulGscSync(synced: 3, errors: 1);
        $this->mockSuccessfulReviewSync();

        $commandTester = $this->createCommandTester();
        $commandTester->execute([]);

        $this->assertEquals(Command::FAILURE, $commandTester->getStatusCode());
    }

    public function testExecuteReturnsFailureWhenReviewsHasErrors(): void
    {
        $this->mockSuccessfulGscSync();
        $this->mockSuccessfulReviewSync(errors: 1);

        $commandTester = $this->createCommandTester();
        $commandTester->execute([]);

        $this->assertEquals(Command::FAILURE, $commandTester->getStatusCode());
    }

    // ===== KEYWORDS-ONLY TESTS =====

    public function testKeywordsOnlySkipsReviewSync(): void
    {
        $this->mockSuccessfulGscSync();

        $this->seoImportService->expects($this->once())->method('syncAllKeywords');
        $this->reviewSyncService->expects($this->never())->method('syncReviews');

        $commandTester = $this->createCommandTester();
        $commandTester->execute(['--keywords-only' => true]);

        $this->assertEquals(Command::SUCCESS, $commandTester->getStatusCode());
    }

    // ===== REVIEWS-ONLY TESTS =====

    public function testReviewsOnlySkipsKeywordSync(): void
    {
        $this->mockSuccessfulReviewSync();

        $this->seoImportService->expects($this->never())->method('syncAllKeywords');
        $this->reviewSyncService->expects($this->once())->method('syncReviews');

        $commandTester = $this->createCommandTester();
        $commandTester->execute(['--reviews-only' => true]);

        $this->assertEquals(Command::SUCCESS, $commandTester->getStatusCode());
    }

    // ===== FORCE OPTION TESTS =====

    public function testForceOptionPassedToServices(): void
    {
        $this->seoImportService
            ->expects($this->once())
            ->method('syncAllKeywords')
            ->with(true) // force=true
            ->willReturn(['synced' => 5, 'skipped' => 0, 'errors' => 0, 'message' => 'OK']);

        $this->seoImportService->method('importNewKeywords')->willReturn([
            'imported' => 0, 'total_gsc' => 0, 'min_impressions' => 0, 'message' => 'OK',
        ]);
        $this->seoImportService->method('deactivateMissingKeywords')->willReturn([
            'deactivated' => 0, 'message' => 'OK',
        ]);

        $this->reviewSyncService
            ->expects($this->once())
            ->method('syncReviews')
            ->with(true) // force=true
            ->willReturn(['created' => 0, 'updated' => 0, 'unchanged' => 5, 'errors' => 0, 'message' => 'OK']);

        $commandTester = $this->createCommandTester();
        $commandTester->execute(['--force' => true]);

        $this->assertEquals(Command::SUCCESS, $commandTester->getStatusCode());
    }

    public function testWithoutForceOptionDefaultsToFalse(): void
    {
        $this->seoImportService
            ->expects($this->once())
            ->method('syncAllKeywords')
            ->with(false) // force=false (default)
            ->willReturn(['synced' => 5, 'skipped' => 0, 'errors' => 0, 'message' => 'OK']);

        $this->seoImportService->method('importNewKeywords')->willReturn([
            'imported' => 0, 'total_gsc' => 0, 'min_impressions' => 0, 'message' => 'OK',
        ]);
        $this->seoImportService->method('deactivateMissingKeywords')->willReturn([
            'deactivated' => 0, 'message' => 'OK',
        ]);

        $this->reviewSyncService
            ->expects($this->once())
            ->method('syncReviews')
            ->with(false) // force=false (default)
            ->willReturn(['created' => 0, 'updated' => 0, 'unchanged' => 5, 'errors' => 0, 'message' => 'OK']);

        $commandTester = $this->createCommandTester();
        $commandTester->execute([]);

        $this->assertEquals(Command::SUCCESS, $commandTester->getStatusCode());
    }

    // ===== OUTPUT TESTS =====

    public function testDisplaysGscStatistics(): void
    {
        $this->mockSuccessfulGscSync(synced: 10, skipped: 2, errors: 0);
        $this->mockSuccessfulReviewSync();

        $commandTester = $this->createCommandTester();
        $commandTester->execute([]);

        $display = $commandTester->getDisplay();
        $this->assertStringContainsString('Google Search Console', $display);
        $this->assertStringContainsString('10', $display); // synced
        $this->assertStringContainsString('2', $display);  // skipped
    }

    public function testDisplaysReviewStatistics(): void
    {
        $this->mockSuccessfulGscSync();
        $this->mockSuccessfulReviewSync(created: 3, updated: 2, unchanged: 10, errors: 0);

        $commandTester = $this->createCommandTester();
        $commandTester->execute([]);

        $display = $commandTester->getDisplay();
        $this->assertStringContainsString('Google Reviews', $display);
        $this->assertStringContainsString('3', $display);  // created
        $this->assertStringContainsString('2', $display);  // updated
        $this->assertStringContainsString('10', $display); // unchanged
    }

    public function testDisplaysTimestamps(): void
    {
        $this->mockSuccessfulGscSync();
        $this->mockSuccessfulReviewSync();

        $commandTester = $this->createCommandTester();
        $commandTester->execute([]);

        $display = $commandTester->getDisplay();
        $this->assertStringContainsString('Démarrage...', $display);
        $this->assertStringContainsString('Terminé.', $display);
    }

    // ===== NO-IMPORT OPTION TESTS =====

    public function testNoImportOptionSkipsImport(): void
    {
        $this->seoImportService->method('syncAllKeywords')->willReturn([
            'synced' => 5, 'skipped' => 0, 'errors' => 0, 'message' => 'OK',
        ]);
        $this->seoImportService->method('deactivateMissingKeywords')->willReturn([
            'deactivated' => 0, 'message' => 'OK',
        ]);
        $this->mockSuccessfulReviewSync();

        $this->seoImportService->expects($this->never())->method('importNewKeywords');

        $commandTester = $this->createCommandTester();
        $commandTester->execute(['--no-import' => true]);

        $this->assertEquals(Command::SUCCESS, $commandTester->getStatusCode());
    }

    // ===== NO-CLEANUP OPTION TESTS =====

    public function testNoCleanupOptionSkipsDeactivation(): void
    {
        $this->seoImportService->method('syncAllKeywords')->willReturn([
            'synced' => 5, 'skipped' => 0, 'errors' => 0, 'message' => 'OK',
        ]);
        $this->seoImportService->method('importNewKeywords')->willReturn([
            'imported' => 0, 'total_gsc' => 0, 'min_impressions' => 0, 'message' => 'OK',
        ]);
        $this->mockSuccessfulReviewSync();

        $this->seoImportService->expects($this->never())->method('deactivateMissingKeywords');

        $commandTester = $this->createCommandTester();
        $commandTester->execute(['--no-cleanup' => true]);

        $this->assertEquals(Command::SUCCESS, $commandTester->getStatusCode());
    }

    // ===== IMPORT/CLEANUP OUTPUT TESTS =====

    public function testDisplaysImportSection(): void
    {
        $this->seoImportService->method('syncAllKeywords')->willReturn([
            'synced' => 5, 'skipped' => 0, 'errors' => 0, 'message' => 'OK',
        ]);
        $this->seoImportService->method('importNewKeywords')->willReturn([
            'imported' => 3,
            'total_gsc' => 50,
            'min_impressions' => 0,
            'message' => '3 nouveau(x) mot(s)-clé(s) importé(s)',
        ]);
        $this->seoImportService->method('deactivateMissingKeywords')->willReturn([
            'deactivated' => 0, 'message' => 'OK',
        ]);
        $this->mockSuccessfulReviewSync();

        $commandTester = $this->createCommandTester();
        $commandTester->execute([]);

        $display = $commandTester->getDisplay();
        $this->assertStringContainsString('Import automatique', $display);
        $this->assertStringContainsString('3 nouveau(x) mot(s)-clé(s) importé(s)', $display);
    }

    public function testDisplaysCleanupSection(): void
    {
        $this->seoImportService->method('syncAllKeywords')->willReturn([
            'synced' => 5, 'skipped' => 0, 'errors' => 0, 'message' => 'OK',
        ]);
        $this->seoImportService->method('importNewKeywords')->willReturn([
            'imported' => 0, 'total_gsc' => 0, 'min_impressions' => 0, 'message' => 'OK',
        ]);
        $this->seoImportService->method('deactivateMissingKeywords')->willReturn([
            'deactivated' => 2,
            'message' => '2 mot(s)-clé(s) désactivé(s) (absents depuis 30 jours)',
        ]);
        $this->mockSuccessfulReviewSync();

        $commandTester = $this->createCommandTester();
        $commandTester->execute([]);

        $display = $commandTester->getDisplay();
        $this->assertStringContainsString('Nettoyage des mots-clés absents', $display);
        $this->assertStringContainsString('2 mot(s)-clé(s) désactivé(s)', $display);
    }
}
