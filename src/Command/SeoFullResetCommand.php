<?php

namespace App\Command;

use App\Entity\SeoKeyword;
use App\Entity\SeoPosition;
use App\Service\GoogleSearchConsoleService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:seo-full-reset',
    description: 'Purge et réimporte toutes les données SEO depuis GSC (données exactes)',
)]
class SeoFullResetCommand extends Command
{
    public function __construct(
        private GoogleSearchConsoleService $gscService,
        private EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('days', 'd', InputOption::VALUE_REQUIRED, 'Nombre de jours à récupérer (défaut: 90, max GSC: ~480)', 90)
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Affiche ce qui serait fait sans modifier la base')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = $input->getOption('dry-run');
        $days = (int) $input->getOption('days');

        $io->title('Reset complet des données SEO');

        if ($dryRun) {
            $io->warning('Mode dry-run activé - aucune modification');
        }

        // Check GSC availability
        if (!$this->gscService->isAvailable()) {
            $io->error('Google Search Console non connecté');
            return Command::FAILURE;
        }

        // Calculate date range (GSC has 2-3 days delay)
        $endDate = new \DateTimeImmutable('-3 days');
        $startDate = $endDate->modify("-{$days} days");

        $io->text(sprintf('Période : %s → %s (%d jours)',
            $startDate->format('Y-m-d'),
            $endDate->format('Y-m-d'),
            $days
        ));

        // Step 1: Purge existing data
        $io->section('Étape 1/3 : Purge des données existantes');

        if (!$dryRun) {
            $conn = $this->entityManager->getConnection();
            $deletedPositions = $conn->executeStatement('DELETE FROM seo_position');
            $deletedKeywords = $conn->executeStatement('DELETE FROM seo_keyword');
            $io->text(sprintf('  → %d positions supprimées', $deletedPositions));
            $io->text(sprintf('  → %d mots-clés supprimés', $deletedKeywords));
        } else {
            $io->text('  → [dry-run] Suppression des positions et mots-clés');
        }

        // Step 2a: Fetch all keywords WITHOUT date dimension (to get ALL queries)
        $io->section('Étape 2/4 : Récupération des mots-clés GSC');
        $io->text('Requête API (tous les mots-clés)...');

        $allKeywordsData = $this->gscService->fetchAllKeywordsData($startDate, $endDate);

        if (empty($allKeywordsData)) {
            $io->error('Aucune donnée retournée par GSC');
            return Command::FAILURE;
        }

        $io->text(sprintf('  → %d requêtes uniques trouvées', count($allKeywordsData)));

        // Step 2b: Fetch daily data WITH date dimension (for positions)
        $io->section('Étape 3/4 : Récupération des positions journalières');
        $io->text('Requête API (données par jour)...');

        $dailyData = $this->gscService->fetchDailyKeywordsData($startDate, $endDate);

        if (empty($dailyData)) {
            $io->warning('Aucune donnée journalière - utilisation des données agrégées');
            $dailyData = [];
        }

        $io->text(sprintf('  → %d jours de données récupérés', count($dailyData)));

        // Collect all unique keywords from BOTH sources
        $allKeywords = $allKeywordsData; // Start with aggregated data (has all keywords)
        $totalPositions = 0;

        foreach ($dailyData as $dateStr => $queries) {
            foreach ($queries as $query => $data) {
                // Add any missing keywords from daily data
                if (!isset($allKeywords[$query])) {
                    $allKeywords[$query] = $data;
                }
                $totalPositions++;
            }
        }

        $io->text(sprintf('  → %d requêtes uniques (combinées)', count($allKeywords)));
        $io->text(sprintf('  → %d positions journalières', $totalPositions));

        // Step 4: Create keywords and positions
        $io->section('Étape 4/4 : Import des données');

        if ($dryRun) {
            $io->text('[dry-run] Création des mots-clés et positions...');
            $io->success(sprintf(
                'Dry-run terminé : %d mots-clés et %d positions seraient créés',
                count($allKeywords),
                $totalPositions
            ));
            return Command::SUCCESS;
        }

        // Create all keywords first
        $io->text('Création des mots-clés...');
        $keywordEntities = [];
        $now = new \DateTimeImmutable();

        foreach ($allKeywords as $query => $aggregatedData) {
            $keyword = new SeoKeyword();
            $keyword->setKeyword($query);
            $keyword->setSource(SeoKeyword::SOURCE_AUTO_GSC);
            // High relevance if the keyword has clicks
            $hasClicks = isset($aggregatedData['clicks']) && $aggregatedData['clicks'] > 0;
            $keyword->setRelevanceLevel($hasClicks ? SeoKeyword::RELEVANCE_HIGH : $this->guessRelevance($query));
            $keyword->setLastSeenInGsc($now);
            $keyword->setLastSyncAt($now);

            $this->entityManager->persist($keyword);
            $keywordEntities[$query] = $keyword;
        }

        $this->entityManager->flush();
        $io->text(sprintf('  → %d mots-clés créés', count($keywordEntities)));

        // Create all positions
        $io->text('Création des positions...');
        $positionsCreated = 0;
        $batchSize = 500;

        foreach ($dailyData as $dateStr => $queries) {
            $date = new \DateTimeImmutable($dateStr);

            foreach ($queries as $query => $data) {
                $keyword = $keywordEntities[$query] ?? null;
                if (!$keyword) {
                    continue;
                }

                $position = new SeoPosition();
                $position->setKeyword($keyword);
                $position->setDate($date);
                $position->setPosition($data['position']);
                $position->setClicks($data['clicks']);
                $position->setImpressions($data['impressions']);

                $this->entityManager->persist($position);
                $positionsCreated++;

                // Flush in batches for performance
                if ($positionsCreated % $batchSize === 0) {
                    $this->entityManager->flush();
                    $this->entityManager->clear(SeoPosition::class);
                    $io->text(sprintf('  → %d positions créées...', $positionsCreated));
                }
            }
        }

        $this->entityManager->flush();
        $io->text(sprintf('  → %d positions créées (total)', $positionsCreated));

        // Summary
        $io->newLine();
        $io->success(sprintf(
            'Reset terminé : %d mots-clés, %d positions sur %d jours',
            count($keywordEntities),
            $positionsCreated,
            count($dailyData)
        ));

        // Show daily totals for verification
        $io->section('Vérification des totaux journaliers (derniers 7 jours)');

        $recentDays = array_slice(array_keys($dailyData), -7, 7, true);
        $rows = [];

        foreach ($recentDays as $dateStr) {
            $queries = $dailyData[$dateStr];
            $totalClicks = 0;
            $totalImpressions = 0;

            foreach ($queries as $data) {
                $totalClicks += $data['clicks'];
                $totalImpressions += $data['impressions'];
            }

            $rows[] = [$dateStr, $totalClicks, $totalImpressions, count($queries)];
        }

        $io->table(['Date', 'Clics', 'Impressions', 'Requêtes'], $rows);

        return Command::SUCCESS;
    }

    /**
     * Devine la pertinence d'un mot-clé basé sur son contenu.
     */
    private function guessRelevance(string $query): string
    {
        $query = strtolower($query);

        // Mots-clés très pertinents (contiennent des termes business)
        $highTerms = ['auray', 'morbihan', 'vannes', 'lorient', 'bretagne', 'site internet', 'création site', 'développeur', 'agence web'];
        foreach ($highTerms as $term) {
            if (str_contains($query, $term)) {
                return SeoKeyword::RELEVANCE_HIGH;
            }
        }

        // Mots-clés peu pertinents (génériques, hors sujet)
        $lowTerms = ['spryker', 'crac\'h'];
        foreach ($lowTerms as $term) {
            if (str_contains($query, $term)) {
                return SeoKeyword::RELEVANCE_LOW;
            }
        }

        return SeoKeyword::RELEVANCE_MEDIUM;
    }
}
