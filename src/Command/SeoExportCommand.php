<?php

namespace App\Command;

use App\Repository\SeoKeywordRepository;
use App\Repository\SeoPositionRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:seo-export',
    description: 'Export SEO data (keywords and positions) to CSV for analysis',
)]
class SeoExportCommand extends Command
{
    public function __construct(
        private SeoKeywordRepository $keywordRepository,
        private SeoPositionRepository $positionRepository,
        private string $projectDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'output',
                'o',
                InputOption::VALUE_REQUIRED,
                'Output directory for CSV files',
                'var/export'
            )
            ->addOption(
                'history',
                null,
                InputOption::VALUE_NONE,
                'Include full position history (one file per keyword)'
            )
            ->addOption(
                'days',
                'd',
                InputOption::VALUE_REQUIRED,
                'Number of days of history to export',
                30
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $outputDir = $this->projectDir . '/' . $input->getOption('output');
        $includeHistory = $input->getOption('history');
        $days = (int) $input->getOption('days');

        // Create output directory if needed
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        $io->title('Export des données SEO');

        // 1. Export keywords summary with latest position
        $keywordsFile = $this->exportKeywordsSummary($outputDir, $io);

        // 2. Export all positions (daily data)
        $positionsFile = $this->exportAllPositions($outputDir, $days, $io);

        // 3. Optionally export detailed history per keyword
        if ($includeHistory) {
            $this->exportKeywordHistory($outputDir, $days, $io);
        }

        $io->success([
            'Export terminé !',
            "Fichiers créés dans: $outputDir",
            "- $keywordsFile (résumé des mots-clés)",
            "- $positionsFile (toutes les positions)",
        ]);

        return Command::SUCCESS;
    }

    private function exportKeywordsSummary(string $outputDir, SymfonyStyle $io): string
    {
        $io->section('Export des mots-clés avec dernière position');

        $keywords = $this->keywordRepository->findAllWithLatestPosition();
        $filename = 'seo_keywords_' . date('Y-m-d') . '.csv';
        $filepath = $outputDir . '/' . $filename;

        $fp = fopen($filepath, 'w');

        // BOM UTF-8 for Excel
        fwrite($fp, "\xEF\xBB\xBF");

        // Header
        fputcsv($fp, [
            'ID',
            'Mot-clé',
            'URL cible',
            'Source',
            'Pertinence',
            'Actif',
            'Dernière position',
            'Clics (dernier jour)',
            'Impressions (dernier jour)',
            'CTR (%)',
            'Date dernière donnée',
            'Dernière sync',
            'Dernière vue GSC',
            'Créé le',
        ], ';');

        foreach ($keywords as $keyword) {
            $latestPosition = $keyword->getLatestPosition();

            fputcsv($fp, [
                $keyword->getId(),
                $keyword->getKeyword(),
                $keyword->getTargetUrl() ?? '',
                $keyword->getSource(),
                $keyword->getRelevanceLevel(),
                $keyword->isActive() ? 'Oui' : 'Non',
                $latestPosition?->getPosition() ?? '',
                $latestPosition?->getClicks() ?? '',
                $latestPosition?->getImpressions() ?? '',
                $latestPosition ? number_format($latestPosition->getCtr(), 2, ',', '') : '',
                $latestPosition?->getDate()?->format('Y-m-d') ?? '',
                $keyword->getLastSyncAt()?->format('Y-m-d H:i') ?? '',
                $keyword->getLastSeenInGsc()?->format('Y-m-d H:i') ?? '',
                $keyword->getCreatedAt()?->format('Y-m-d H:i') ?? '',
            ], ';');
        }

        fclose($fp);

        $io->text(sprintf('  → %d mots-clés exportés', count($keywords)));

        return $filename;
    }

    private function exportAllPositions(string $outputDir, int $days, SymfonyStyle $io): string
    {
        $io->section("Export de toutes les positions (derniers $days jours)");

        $since = new \DateTimeImmutable("-{$days} days");
        $positions = $this->positionRepository->findAllSince($since);

        $filename = 'seo_positions_' . date('Y-m-d') . '.csv';
        $filepath = $outputDir . '/' . $filename;

        $fp = fopen($filepath, 'w');

        // BOM UTF-8 for Excel
        fwrite($fp, "\xEF\xBB\xBF");

        // Header (format similaire à GSC export)
        fputcsv($fp, [
            'Date',
            'Mot-clé',
            'Position',
            'Clics',
            'Impressions',
            'CTR (%)',
            'URL cible',
            'Source',
            'Pertinence',
        ], ';');

        foreach ($positions as $position) {
            $keyword = $position->getKeyword();

            fputcsv($fp, [
                $position->getDate()?->format('Y-m-d') ?? '',
                $keyword?->getKeyword() ?? '',
                number_format($position->getPosition(), 1, ',', ''),
                $position->getClicks(),
                $position->getImpressions(),
                number_format($position->getCtr(), 2, ',', ''),
                $keyword?->getTargetUrl() ?? '',
                $keyword?->getSource() ?? '',
                $keyword?->getRelevanceLevel() ?? '',
            ], ';');
        }

        fclose($fp);

        $io->text(sprintf('  → %d entrées de positions exportées', count($positions)));

        return $filename;
    }

    private function exportKeywordHistory(string $outputDir, int $days, SymfonyStyle $io): void
    {
        $io->section("Export de l'historique détaillé par mot-clé");

        $keywords = $this->keywordRepository->findActiveKeywords();
        $since = new \DateTimeImmutable("-{$days} days");

        $historyDir = $outputDir . '/history';
        if (!is_dir($historyDir)) {
            mkdir($historyDir, 0755, true);
        }

        $exported = 0;

        foreach ($keywords as $keyword) {
            $positions = $this->positionRepository->findByKeywordSince($keyword, $since);

            if (empty($positions)) {
                continue;
            }

            // Sanitize filename
            $slug = preg_replace('/[^a-z0-9]+/', '-', strtolower($keyword->getKeyword()));
            $slug = trim($slug, '-');
            $filename = sprintf('%s_%d.csv', substr($slug, 0, 50), $keyword->getId());
            $filepath = $historyDir . '/' . $filename;

            $fp = fopen($filepath, 'w');
            fwrite($fp, "\xEF\xBB\xBF");

            fputcsv($fp, ['Date', 'Position', 'Clics', 'Impressions', 'CTR (%)'], ';');

            foreach ($positions as $position) {
                fputcsv($fp, [
                    $position->getDate()?->format('Y-m-d') ?? '',
                    number_format($position->getPosition(), 1, ',', ''),
                    $position->getClicks(),
                    $position->getImpressions(),
                    number_format($position->getCtr(), 2, ',', ''),
                ], ';');
            }

            fclose($fp);
            $exported++;
        }

        $io->text(sprintf('  → %d fichiers d\'historique créés dans %s/', $exported, $historyDir));
    }
}
