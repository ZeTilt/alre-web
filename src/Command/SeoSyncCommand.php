<?php

namespace App\Command;

use App\Entity\SeoSyncLog;
use App\Service\BingDataImportService;
use App\Service\ReviewSyncService;
use App\Service\SeoDataImportService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:seo-sync',
    description: 'Synchronise les données SEO (GSC + Bing + Google Reviews) - Idéal pour cron quotidien',
)]
class SeoSyncCommand extends Command
{
    public function __construct(
        private SeoDataImportService $seoImportService,
        private BingDataImportService $bingImportService,
        private ReviewSyncService $reviewSyncService,
        private EntityManagerInterface $entityManager,
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
            ->addOption('no-bing', null, InputOption::VALUE_NONE, 'Ne pas synchroniser les données Bing')
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
        $noBing = $input->getOption('no-bing');

        $io->title('Synchronisation SEO quotidienne');
        $io->text(sprintf('[%s] Démarrage...', date('Y-m-d H:i:s')));

        $log = new SeoSyncLog('seo-sync');
        $this->entityManager->persist($log);
        $this->entityManager->flush();

        $hasErrors = false;
        $details = [];
        $errorMessages = [];

        try {
            // Sync GSC Keywords
            if (!$reviewsOnly) {
                // Import new keywords FIRST so they're included in the sync
                if (!$noImport) {
                    $io->section('Import automatique des nouveaux mots-clés');
                    $importResult = $this->seoImportService->importNewKeywords();
                    $io->success($importResult['message']);

                    $details['gsc_keywords'] = [
                        'imported' => $importResult['imported'],
                        'reactivated' => $importResult['reactivated'],
                        'total_gsc' => $importResult['total_gsc'],
                    ];

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
                    $errorMessages[] = 'GSC positions: ' . $gscResult['message'];
                } else {
                    $io->success($gscResult['message']);
                }

                $details['gsc_positions'] = [
                    'synced' => $gscResult['synced'],
                    'skipped' => $gscResult['skipped'],
                    'errors' => $gscResult['errors'],
                ];

                $io->table(
                    ['Synchronisés', 'Sans données', 'Erreurs'],
                    [[$gscResult['synced'], $gscResult['skipped'], $gscResult['errors']]]
                );

                // Sync daily totals (real clicks/impressions without anonymization)
                $io->section('Google Search Console - Totaux journaliers');
                $dailyResult = $this->seoImportService->syncDailyTotals($force);
                $io->success($dailyResult['message']);

                $details['gsc_daily_totals'] = [
                    'synced' => $dailyResult['synced'] ?? 0,
                    'skipped' => $dailyResult['skipped'] ?? 0,
                ];

                // Bing Webmaster Tools
                if (!$noBing) {
                    $io->section('Bing Webmaster Tools - Mots-clés');
                    $bingKeywordsResult = $this->bingImportService->syncBingKeywords();

                    if ($bingKeywordsResult['errors'] > 0) {
                        $io->warning($bingKeywordsResult['message']);
                        $hasErrors = true;
                        $errorMessages[] = 'Bing keywords: ' . $bingKeywordsResult['message'];
                    } else {
                        $io->success($bingKeywordsResult['message']);
                    }

                    $details['bing_keywords'] = [
                        'synced' => $bingKeywordsResult['synced'] ?? 0,
                        'created' => $bingKeywordsResult['created'] ?? 0,
                        'errors' => $bingKeywordsResult['errors'] ?? 0,
                    ];

                    $io->section('Bing Webmaster Tools - Totaux journaliers');
                    $bingDailyResult = $this->bingImportService->syncBingDailyTotals();
                    $io->success($bingDailyResult['message']);

                    $details['bing_daily_totals'] = [
                        'synced' => $bingDailyResult['synced'] ?? 0,
                        'skipped' => $bingDailyResult['skipped'] ?? 0,
                    ];
                } else {
                    $io->section('Bing Webmaster Tools');
                    $io->note('Bing skippé (--no-bing)');
                }

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
                    $errorMessages[] = 'Reviews: ' . $reviewsResult['message'];
                } else {
                    $io->success($reviewsResult['message']);
                }

                $details['reviews'] = [
                    'created' => $reviewsResult['created'],
                    'updated' => $reviewsResult['updated'],
                    'errors' => $reviewsResult['errors'],
                ];

                $io->table(
                    ['Nouveaux', 'Mis à jour', 'Inchangés', 'Erreurs'],
                    [[$reviewsResult['created'], $reviewsResult['updated'], $reviewsResult['unchanged'], $reviewsResult['errors']]]
                );
            }

            $status = $hasErrors ? SeoSyncLog::STATUS_PARTIAL : SeoSyncLog::STATUS_SUCCESS;
            $log->finish($details, $status, $hasErrors ? implode(' | ', $errorMessages) : null);
        } catch (\Throwable $e) {
            $log->finish($details, SeoSyncLog::STATUS_ERROR, $e->getMessage());
            $this->entityManager->flush();
            throw $e;
        }

        $this->entityManager->flush();

        $io->newLine();
        $io->text(sprintf('[%s] Terminé.', date('Y-m-d H:i:s')));

        return $hasErrors ? Command::FAILURE : Command::SUCCESS;
    }
}
