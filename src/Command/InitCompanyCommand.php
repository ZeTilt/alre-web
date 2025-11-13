<?php

namespace App\Command;

use App\Entity\Company;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:init-company',
    description: 'Initialize default company information',
)]
class InitCompanyCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Check if company already exists
        $existingCompany = $this->entityManager->getRepository(Company::class)->findOneBy([]);
        if ($existingCompany) {
            $io->warning('Company information already exists.');
            return Command::SUCCESS;
        }

        // Create default company
        $company = new Company();
        $company->setName('ZeTilt');
        $company->setOwnerName('DHUICQUE Fabrice');
        $company->setTitle('Développeur Web Full-Stack');
        $company->setAddress('1, impasse de la Forge');
        $company->setPostalCode('56400');
        $company->setCity('Sainte-Anne d\'Auray');
        $company->setPhone('06 95 78 69 84');
        $company->setEmail('contact@zetilt.fr');
        $company->setSiret('90308676700014');
        $company->setLegalStatus('Auto-entrepreneur');
        $company->setLegalMentions('Dispensé d\'immatriculation au registre du commerce et des sociétés (RCS) et au répertoire des métiers (RM)');

        $this->entityManager->persist($company);
        $this->entityManager->flush();

        $io->success('Default company information has been created successfully!');
        $io->note('You can now edit your company information in the admin panel under "Mon Entreprise".');

        return Command::SUCCESS;
    }
}