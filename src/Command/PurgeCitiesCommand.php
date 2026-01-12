<?php

namespace App\Command;

use App\Entity\City;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:purge-cities',
    description: 'Supprime toutes les villes de la base de données',
)]
class PurgeCitiesCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Confirme la suppression sans demander');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $force = $input->getOption('force');

        $cityRepository = $this->entityManager->getRepository(City::class);
        $cities = $cityRepository->findAll();
        $count = count($cities);

        if ($count === 0) {
            $io->info('Aucune ville à supprimer.');
            return Command::SUCCESS;
        }

        $io->warning(sprintf('Cette commande va supprimer %d ville(s) et toutes les pages SEO associées.', $count));

        if (!$force) {
            $confirm = $io->confirm('Êtes-vous sûr de vouloir continuer ?', false);
            if (!$confirm) {
                $io->info('Opération annulée.');
                return Command::SUCCESS;
            }
        }

        // Supprimer toutes les villes
        foreach ($cities as $city) {
            $this->entityManager->remove($city);
        }

        $this->entityManager->flush();

        $io->success(sprintf('%d ville(s) supprimée(s).', $count));

        return Command::SUCCESS;
    }
}
