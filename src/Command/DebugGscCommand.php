<?php

namespace App\Command;

use App\Service\GoogleSearchConsoleService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:debug-gsc',
    description: 'Debug Google Search Console API connection',
)]
class DebugGscCommand extends Command
{
    public function __construct(
        private GoogleSearchConsoleService $gscService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('date', InputArgument::OPTIONAL, 'Date de début (Y-m-d)', null)
            ->addOption('to', 't', InputOption::VALUE_REQUIRED, 'Date de fin (Y-m-d) pour une plage')
            ->addOption('search', 's', InputOption::VALUE_REQUIRED, 'Chercher un mot-clé spécifique')
            ->addOption('raw', 'r', InputOption::VALUE_NONE, 'Afficher la requête et réponse brute API')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Google Search Console Debug');

        // Check if service is available
        if (!$this->gscService->isAvailable()) {
            $io->error('GSC Service is not available. Check OAuth connection and GOOGLE_SITE_URL.');
            return Command::FAILURE;
        }

        $io->success('GSC Service is available');

        // Parse date argument
        $dateArg = $input->getArgument('date');
        $toDateArg = $input->getOption('to');
        $searchTerm = $input->getOption('search');

        if ($dateArg) {
            $startDate = new \DateTimeImmutable($dateArg);
            $endDate = $toDateArg ? new \DateTimeImmutable($toDateArg) : $startDate;
            if ($startDate == $endDate) {
                $io->text(sprintf('Date : %s', $startDate->format('Y-m-d')));
            } else {
                $io->text(sprintf('Période : %s → %s', $startDate->format('Y-m-d'), $endDate->format('Y-m-d')));
            }
        } else {
            $startDate = new \DateTimeImmutable('-7 days');
            $endDate = new \DateTimeImmutable('-1 day');
            $io->text(sprintf('Période : %s → %s', $startDate->format('Y-m-d'), $endDate->format('Y-m-d')));
        }

        // Fetch data
        $io->section('Fetching data from GSC API...');

        $data = $this->gscService->fetchAllKeywordsData($startDate, $endDate);

        if (empty($data)) {
            $io->warning('No data returned from GSC API');
            return Command::FAILURE;
        }

        // Calculate totals
        $totalClicks = 0;
        $totalImpressions = 0;
        foreach ($data as $metrics) {
            $totalClicks += $metrics['clicks'];
            $totalImpressions += $metrics['impressions'];
        }

        $io->success(sprintf(
            '%d requêtes | Total: %d clics, %d impressions',
            count($data),
            $totalClicks,
            $totalImpressions
        ));

        // Filter by search term if provided
        if ($searchTerm) {
            $filtered = [];
            foreach ($data as $query => $metrics) {
                if (stripos($query, $searchTerm) !== false) {
                    $filtered[$query] = $metrics;
                }
            }
            $data = $filtered;
            $io->text(sprintf('Filtré par "%s": %d résultats', $searchTerm, count($data)));
        }

        // Show queries with clicks first, then by impressions
        uasort($data, function($a, $b) {
            if ($a['clicks'] !== $b['clicks']) {
                return $b['clicks'] - $a['clicks'];
            }
            return $b['impressions'] - $a['impressions'];
        });

        // Show top 30 queries
        $io->section('Requêtes (triées par clics puis impressions):');
        $rows = [];
        $count = 0;
        foreach ($data as $query => $metrics) {
            if ($count >= 30) break;
            $rows[] = [
                substr($query, 0, 50),
                $metrics['position'],
                $metrics['clicks'],
                $metrics['impressions'],
            ];
            $count++;
        }

        $io->table(['Query', 'Pos', 'Clics', 'Impr'], $rows);

        // Also test with date dimension
        $io->section('Test avec dimension DATE...');
        $dailyData = $this->gscService->fetchDailyKeywordsData($startDate, $endDate);

        if (empty($dailyData)) {
            $io->warning('Aucune donnée avec dimension date');
        } else {
            $dailyTotalClicks = 0;
            $dailyTotalImpressions = 0;
            $dailyTotalQueries = 0;

            foreach ($dailyData as $dateStr => $queries) {
                foreach ($queries as $metrics) {
                    $dailyTotalClicks += $metrics['clicks'];
                    $dailyTotalImpressions += $metrics['impressions'];
                    $dailyTotalQueries++;
                }
            }

            $io->text(sprintf(
                'Avec dimension date: %d jours, %d positions, %d clics, %d impressions',
                count($dailyData),
                $dailyTotalQueries,
                $dailyTotalClicks,
                $dailyTotalImpressions
            ));

            // Compare
            $io->newLine();
            $io->text('Comparaison:');
            $io->table(
                ['Mode', 'Clics', 'Impressions'],
                [
                    ['Sans dimension date', $totalClicks, $totalImpressions],
                    ['Avec dimension date', $dailyTotalClicks, $dailyTotalImpressions],
                    ['Différence', $totalClicks - $dailyTotalClicks, $totalImpressions - $dailyTotalImpressions],
                ]
            );
        }

        return Command::SUCCESS;
    }
}
