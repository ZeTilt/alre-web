<?php

namespace App\Command;

use App\Repository\DevisRepository;
use App\Repository\FactureRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:update-status',
    description: 'Met à jour automatiquement les statuts des devis et factures en fonction des échéances',
)]
class UpdateDevisFactureStatusCommand extends Command
{
    public function __construct(
        private DevisRepository $devisRepository,
        private FactureRepository $factureRepository,
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $devisUpdated = 0;
        $facturesUpdated = 0;

        // Récupérer tous les devis envoyés ou relancés
        $devisList = $this->devisRepository->createQueryBuilder('d')
            ->where('d.status IN (:statuses)')
            ->setParameter('statuses', ['envoye', 'relance'])
            ->getQuery()
            ->getResult();

        foreach ($devisList as $devis) {
            if ($devis->updateStatusBasedOnDeadline()) {
                $devisUpdated++;
            }
        }

        // Récupérer toutes les factures envoyées ou relancées
        $facturesList = $this->factureRepository->createQueryBuilder('f')
            ->where('f.status IN (:statuses)')
            ->setParameter('statuses', ['envoye', 'relance'])
            ->getQuery()
            ->getResult();

        foreach ($facturesList as $facture) {
            if ($facture->updateStatusBasedOnDeadline()) {
                $facturesUpdated++;
            }
        }

        // Persister les changements
        $this->entityManager->flush();

        $io->success(sprintf(
            'Statuts mis à jour avec succès : %d devis et %d factures modifiés.',
            $devisUpdated,
            $facturesUpdated
        ));

        return Command::SUCCESS;
    }
}
