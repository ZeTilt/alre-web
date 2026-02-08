<?php

namespace App\Command;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:seo-fix-last-seen',
    description: 'Recalcule lastSeenInGsc depuis les positions existantes en base',
)]
class SeoFixLastSeenCommand extends Command
{
    public function __construct(
        private Connection $connection,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $updated = $this->connection->executeStatement('
            UPDATE seo_keyword k
            SET last_seen_in_gsc = (
                SELECT MAX(p.date) FROM seo_position p WHERE p.keyword_id = k.id
            )
            WHERE EXISTS (SELECT 1 FROM seo_position p WHERE p.keyword_id = k.id)
        ');

        $io->success(sprintf('%d mot(s)-clé(s) mis à jour avec la vraie date de dernière impression.', $updated));

        return Command::SUCCESS;
    }
}
