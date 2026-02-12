<?php

namespace App\Command;

use App\Service\ReviewSyncService;
use App\Service\SeoDataImportService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:seo-sync',
    description: 'Synchronise les données SEO (GSC + Google Reviews) - Idéal pour cron quotidien',
)]
class SeoSyncCommand extends Command
{
    public function __construct(
        private SeoDataImportService $seoImportService,
        private ReviewSyncService $reviewSyncService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Force la synchronisation même si les données sont récentes')
            ->addOption('keywords-only', null, InputOption::VALUE_NONE, 'Synchronise uniquement les mots-clés GSC')
            ->addOption('reviews-only', null, InputOption::VALUE_NONE, 'Synchronise uniquement les avis Google')
            ->addOption('no-import', null, InputOption::VALUE_NONE, 'Ne pas importer les nouveaux mots-clés')
            ->addOption('no-cleanup', null, InputOption::VALUE_NONE, 'Ne pas désactiver les mots-clés absents')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $force = $input->getOption('force');
        $keywordsOnly = $input->getOption('keywords-only');
        $reviewsOnly = $input->getOption('reviews-only');
        $noImport = $input->getOption('no-import');
        $noCleanup = $input->getOption('no-cleanup');

        $io->title('Synchronisation SEO quotidienne');
        $io->text(sprintf('[%s] Démarrage...', date('Y-m-d H:i:s')));

        $hasErrors = false;

        // Sync GSC Keywords
        if (!$reviewsOnly) {
            // Import new keywords FIRST so they're included in the sync
            if (!$noImport) {
                $io->section('Import automatique des nouveaux mots-clés');
                $importResult = $this->seoImportService->importNewKeywords();
                $io->success($importResult['message']);

                if ($importResult['imported'] > 0 || $importResult['reactivated'] > 0) {
                    $io->text(sprintf(
                        'Seuil appliqué: %d impressions minimum (%d requêtes GSC analysées)',
                        $importResult['min_impressions'],
                        $importResult['total_gsc']
                    ));

                    if ($importResult['reactivated'] > 0) {
                        $io->text(sprintf(
                            '%d mot(s)-clé(s) réactivé(s) (réapparus dans GSC)',
                            $importResult['reactivated']
                        ));
                    }
                }
            }

            // Sync positions for ALL active keywords (including newly imported ones)
            $io->section('Google Search Console - Mots-clés');
            $gscResult = $this->seoImportService->syncAllKeywords($force);

            if ($gscResult['errors'] > 0) {
                $io->warning($gscResult['message']);
                $hasErrors = true;
            } else {
                $io->success($gscResult['message']);
            }

            $io->table(
                ['Synchronisés', 'Sans données', 'Erreurs'],
                [[$gscResult['synced'], $gscResult['skipped'], $gscResult['errors']]]
            );

            // Sync daily totals (real clicks/impressions without anonymization)
            $io->section('Google Search Console - Totaux journaliers');
            $dailyResult = $this->seoImportService->syncDailyTotals($force);
            $io->success($dailyResult['message']);

            // Cleanup missing keywords
            if (!$noCleanup) {
                $io->section('Nettoyage des mots-clés absents');
                $cleanupResult = $this->seoImportService->deactivateMissingKeywords();
                $io->success($cleanupResult['message']);
            }
        }

        // Sync Google Reviews
        if (!$keywordsOnly) {
            $io->section('Google Reviews - Avis clients');
            $reviewsResult = $this->reviewSyncService->syncReviews($force);

            if ($reviewsResult['errors'] > 0) {
                $io->warning($reviewsResult['message']);
                $hasErrors = true;
            } else {
                $io->success($reviewsResult['message']);
            }

            $io->table(
                ['Nouveaux', 'Mis à jour', 'Inchangés', 'Erreurs'],
                [[$reviewsResult['created'], $reviewsResult['updated'], $reviewsResult['unchanged'], $reviewsResult['errors']]]
            );
        }

        $io->newLine();
        $io->text(sprintf('[%s] Terminé.', date('Y-m-d H:i:s')));

        return $hasErrors ? Command::FAILURE : Command::SUCCESS;
    }
}
