<?php

namespace App\Command;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:seo-cleanup-duplicates',
    description: 'Supprime les positions SEO en double (garde la plus récente par keyword/date)',
)]
class SeoCleanupDuplicatesCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Affiche ce qui serait supprimé sans rien faire');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = $input->getOption('dry-run');

        $io->title('Nettoyage des positions SEO en double');

        if ($dryRun) {
            $io->warning('Mode dry-run activé');
        }

        $conn = $this->entityManager->getConnection();

        // Trouver les doublons
        $duplicates = $conn->fetchAllAssociative("
            SELECT keyword_id, date, COUNT(*) as cnt, GROUP_CONCAT(id ORDER BY id DESC) as ids
            FROM seo_position
            GROUP BY keyword_id, date
            HAVING cnt > 1
        ");

        if (empty($duplicates)) {
            $io->success('Aucun doublon trouvé !');
            return Command::SUCCESS;
        }

        $io->text(sprintf('%d groupe(s) de doublons trouvé(s)', count($duplicates)));

        $totalDeleted = 0;

        foreach ($duplicates as $dup) {
            $ids = explode(',', $dup['ids']);
            $keepId = array_shift($ids); // Garde le plus récent (premier car ORDER BY id DESC)
            $deleteIds = $ids;

            $io->text(sprintf(
                '  keyword_id=%d, date=%s : garde #%s, supprime #%s',
                $dup['keyword_id'],
                $dup['date'],
                $keepId,
                implode(', #', $deleteIds)
            ));

            if (!$dryRun && !empty($deleteIds)) {
                $placeholders = implode(',', array_fill(0, count($deleteIds), '?'));
                $conn->executeStatement(
                    "DELETE FROM seo_position WHERE id IN ($placeholders)",
                    $deleteIds
                );
                $totalDeleted += count($deleteIds);
            } else {
                $totalDeleted += count($deleteIds);
            }
        }

        $io->newLine();

        if ($dryRun) {
            $io->success(sprintf('%d position(s) seraient supprimée(s)', $totalDeleted));
        } else {
            $io->success(sprintf('%d position(s) supprimée(s)', $totalDeleted));
        }

        return Command::SUCCESS;
    }
}
