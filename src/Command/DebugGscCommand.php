<?php

namespace App\Command;

use App\Service\GoogleSearchConsoleService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
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

        // Fetch all keywords data
        $io->section('Fetching data from GSC API...');

        $data = $this->gscService->fetchAllKeywordsData();

        if (empty($data)) {
            $io->warning('No data returned from GSC API');
            $io->note('This could mean:');
            $io->listing([
                'GOOGLE_SITE_URL format is incorrect',
                'No search data exists for the last 7 days',
                'OAuth token might be invalid',
            ]);
            return Command::FAILURE;
        }

        $io->success(sprintf('Found %d queries from GSC', count($data)));

        // Show top 20 queries
        $io->section('Top 20 queries:');
        $rows = [];
        $count = 0;
        foreach ($data as $query => $metrics) {
            if ($count >= 20) break;
            $rows[] = [
                $query,
                $metrics['position'],
                $metrics['clicks'],
                $metrics['impressions'],
            ];
            $count++;
        }

        $io->table(['Query', 'Position', 'Clicks', 'Impressions'], $rows);

        return Command::SUCCESS;
    }
}
