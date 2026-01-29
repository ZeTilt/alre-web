<?php

namespace App\Command;

use App\Entity\SeoPosition;
use App\Repository\SeoKeywordRepository;
use App\Repository\SeoPositionRepository;
use App\Service\GoogleSearchConsoleService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:seo-backfill',
    description: 'Récupère les données SEO historiques pour des dates manquantes',
)]
class SeoBackfillCommand extends Command
{
    public function __construct(
        private GoogleSearchConsoleService $gscService,
        private SeoKeywordRepository $keywordRepository,
        private SeoPositionRepository $positionRepository,
        private EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('start-date', InputArgument::REQUIRED, 'Date de début (format: Y-m-d)')
            ->addArgument('end-date', InputArgument::OPTIONAL, 'Date de fin (format: Y-m-d, défaut = start-date)')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Affiche ce qui serait fait sans écrire en base')
            ->addOption('overwrite', null, InputOption::VALUE_NONE, 'Écrase les données existantes pour ces dates')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Parse dates
        try {
            $startDate = new \DateTimeImmutable($input->getArgument('start-date'));
            $endDateArg = $input->getArgument('end-date');
            $endDate = $endDateArg ? new \DateTimeImmutable($endDateArg) : $startDate;
        } catch (\Exception $e) {
            $io->error('Format de date invalide. Utilisez le format Y-m-d (ex: 2026-01-18)');
            return Command::FAILURE;
        }

        if ($endDate < $startDate) {
            $io->error('La date de fin doit être après la date de début');
            return Command::FAILURE;
        }

        // Check GSC availability
        if (!$this->gscService->isAvailable()) {
            $io->error('Google Search Console non connecté. Reconnectez-vous via /saeiblauhjc/google/connect');
            return Command::FAILURE;
        }

        $dryRun = $input->getOption('dry-run');
        $overwrite = $input->getOption('overwrite');

        $io->title('Backfill des données SEO');
        $io->text(sprintf('Période : %s → %s', $startDate->format('Y-m-d'), $endDate->format('Y-m-d')));

        if ($dryRun) {
            $io->warning('Mode dry-run activé - aucune écriture en base');
        }

        // Get active keywords
        $keywords = $this->keywordRepository->findActiveKeywords();
        if (empty($keywords)) {
            $io->warning('Aucun mot-clé actif en base');
            return Command::SUCCESS;
        }

        $io->text(sprintf('%d mot(s)-clé(s) actif(s) à traiter', count($keywords)));
        $io->newLine();

        // Fetch all GSC data for the period in one call (with date dimension for daily clicks)
        $io->text('Récupération des données GSC...');
        $dailyGscData = $this->gscService->fetchDailyKeywordsData($startDate, $endDate);

        if (empty($dailyGscData)) {
            $io->warning('Aucune donnée GSC pour cette période');
            return Command::SUCCESS;
        }

        $io->text(sprintf('%d jour(s) de données récupérés', count($dailyGscData)));
        $io->newLine();

        $totalCreated = 0;
        $totalSkipped = 0;
        $totalOverwritten = 0;

        // Iterate over each day in the GSC response
        foreach ($dailyGscData as $dateStr => $gscData) {
            $currentDate = new \DateTimeImmutable($dateStr);
            $io->section(sprintf('Date : %s (%d requêtes)', $currentDate->format('Y-m-d'), count($gscData)));

            $created = 0;
            $skipped = 0;
            $overwritten = 0;

            foreach ($keywords as $keyword) {
                $keywordLower = strtolower($keyword->getKeyword());
                $data = $this->findBestMatchingData($keywordLower, $gscData);

                if ($data === null) {
                    continue;
                }

                // Check if position already exists for this date
                $existing = $this->positionRepository->findOneBy([
                    'keyword' => $keyword,
                    'date' => $currentDate,
                ]);

                if ($existing && !$overwrite) {
                    $skipped++;
                    continue;
                }

                if (!$dryRun) {
                    if ($existing && $overwrite) {
                        $existing->setPosition($data['position']);
                        $existing->setClicks($data['clicks']);
                        $existing->setImpressions($data['impressions']);
                        $overwritten++;
                    } else {
                        $position = new SeoPosition();
                        $position->setKeyword($keyword);
                        $position->setPosition($data['position']);
                        $position->setClicks($data['clicks']);
                        $position->setImpressions($data['impressions']);
                        $position->setDate($currentDate);
                        $this->entityManager->persist($position);
                        $created++;
                    }
                } else {
                    $created++;
                }
            }

            if (!$dryRun) {
                $this->entityManager->flush();
            }

            $io->text(sprintf(
                '  → %d créé(s), %d ignoré(s) (existants), %d écrasé(s)',
                $created,
                $skipped,
                $overwritten
            ));

            $totalCreated += $created;
            $totalSkipped += $skipped;
            $totalOverwritten += $overwritten;
        }

        $io->newLine();
        $io->success(sprintf(
            'Backfill terminé : %d position(s) créée(s), %d ignorée(s), %d écrasée(s)',
            $totalCreated,
            $totalSkipped,
            $totalOverwritten
        ));

        return Command::SUCCESS;
    }

    /**
     * Trouve la meilleure correspondance pour un mot-clé dans les données GSC.
     */
    private function findBestMatchingData(string $keyword, array $gscData): ?array
    {
        $keywordNormalized = $this->normalizeString($keyword);

        // Correspondance exacte
        if (isset($gscData[$keyword])) {
            return $gscData[$keyword];
        }

        // Correspondance exacte normalisée
        foreach ($gscData as $query => $data) {
            if ($this->normalizeString($query) === $keywordNormalized) {
                return $data;
            }
        }

        // La requête GSC contient le mot-clé (ou l'inverse)
        $bestMatch = null;
        $bestImpressions = 0;

        foreach ($gscData as $query => $data) {
            $queryNormalized = $this->normalizeString($query);

            if (str_contains($queryNormalized, $keywordNormalized) ||
                str_contains($keywordNormalized, $queryNormalized)) {
                if ($data['impressions'] > $bestImpressions) {
                    $bestMatch = $data;
                    $bestImpressions = $data['impressions'];
                }
            }
        }

        return $bestMatch;
    }

    private function normalizeString(string $str): string
    {
        $str = strtolower($str);
        $accents = ['é', 'è', 'ê', 'ë', 'à', 'â', 'ä', 'ù', 'û', 'ü', 'ô', 'ö', 'î', 'ï', 'ç'];
        $noAccents = ['e', 'e', 'e', 'e', 'a', 'a', 'a', 'u', 'u', 'u', 'o', 'o', 'i', 'i', 'c'];
        return str_replace($accents, $noAccents, $str);
    }
}
