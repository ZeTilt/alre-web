<?php

namespace App\Command;

use App\Repository\BlockedIpRepository;
use App\Repository\SecurityLogRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:purge-security-logs',
    description: 'Purge old security logs (RGPD: 7 days retention) and deactivate expired IP blocks',
)]
class PurgeSecurityLogsCommand extends Command
{
    private const DEFAULT_RETENTION_DAYS = 7;

    public function __construct(
        private SecurityLogRepository $securityLogRepository,
        private BlockedIpRepository $blockedIpRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'days',
                'd',
                InputOption::VALUE_REQUIRED,
                'Number of days to retain logs',
                self::DEFAULT_RETENTION_DAYS
            )
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Show what would be deleted without actually deleting'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $days = (int) $input->getOption('days');
        $dryRun = $input->getOption('dry-run');

        $cutoffDate = new \DateTimeImmutable("-{$days} days");

        $io->title('Security Logs Purge');
        $io->text(sprintf('Retention period: %d days (before %s)', $days, $cutoffDate->format('Y-m-d H:i:s')));

        if ($dryRun) {
            $io->warning('DRY RUN - No changes will be made');
        }

        // 1. Purge old security logs
        $io->section('Security Logs');

        if (!$dryRun) {
            $deletedLogs = $this->securityLogRepository->deleteOlderThan($cutoffDate);
            $io->success(sprintf('Deleted %d security log entries', $deletedLogs));
        } else {
            $io->info('Would delete logs older than ' . $cutoffDate->format('Y-m-d H:i:s'));
        }

        // 2. Deactivate expired blocks
        $io->section('Expired IP Blocks');

        if (!$dryRun) {
            $deactivated = $this->blockedIpRepository->deactivateExpired();
            $io->success(sprintf('Deactivated %d expired IP blocks', $deactivated));
        } else {
            $io->info('Would deactivate expired IP blocks');
        }

        $io->success('Purge completed');

        return Command::SUCCESS;
    }
}
