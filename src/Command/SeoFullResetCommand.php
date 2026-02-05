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

        // Step 2: Fetch data for EACH day individually (without date dimension = complete data)
        $io->section('Étape 2/3 : Récupération des données GSC jour par jour');
        $io->text('Cette méthode récupère TOUTES les requêtes pour chaque jour...');
        $io->newLine();

        $dailyData = [];
        $allKeywords = [];
        $totalPositions = 0;
        $currentDate = clone $startDate;

        $progressBar = $io->createProgressBar($days);
        $progressBar->start();

        while ($currentDate <= $endDate) {
            $dateStr = $currentDate->format('Y-m-d');

            // Fetch ALL keywords for this specific day (no date dimension = complete data)
            $dayData = $this->gscService->fetchAllKeywordsData($currentDate, $currentDate);

            if (!empty($dayData)) {
                $dailyData[$dateStr] = $dayData;

                foreach ($dayData as $query => $data) {
                    // Track all unique keywords with their best data
                    if (!isset($allKeywords[$query]) || $data['clicks'] > ($allKeywords[$query]['clicks'] ?? 0)) {
                        $allKeywords[$query] = $data;
                    }
                    $totalPositions++;
                }
            }

            $progressBar->advance();
            $currentDate = $currentDate->modify('+1 day');

            // Small delay to avoid rate limiting
            usleep(100000); // 100ms
        }

        $progressBar->finish();
        $io->newLine(2);

        if (empty($dailyData)) {
            $io->error('Aucune donnée retournée par GSC');
            return Command::FAILURE;
        }

        $io->text(sprintf('  → %d jours avec données', count($dailyData)));
        $io->text(sprintf('  → %d requêtes uniques trouvées', count($allKeywords)));
        $io->text(sprintf('  → %d positions à créer', $totalPositions));

        // Step 3: Create keywords and positions
        $io->section('Étape 3/3 : Import des données');

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

        // Store keyword IDs for reference after clear
        $keywordIds = [];
        foreach ($keywordEntities as $query => $keyword) {
            $keywordIds[$query] = $keyword->getId();
        }

        foreach ($dailyData as $dateStr => $queries) {
            $date = new \DateTimeImmutable($dateStr);

            foreach ($queries as $query => $data) {
                $keywordId = $keywordIds[$query] ?? null;
                if (!$keywordId) {
                    continue;
                }

                // Use reference to avoid issues after clear()
                $keywordRef = $this->entityManager->getReference(SeoKeyword::class, $keywordId);

                $position = new SeoPosition();
                $position->setKeyword($keywordRef);
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
