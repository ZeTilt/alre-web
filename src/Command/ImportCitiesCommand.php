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
use Symfony\Component\Yaml\Yaml;

#[AsCommand(
    name: 'app:import-cities',
    description: 'Importe les villes depuis le fichier YAML vers la base de données',
)]
class ImportCitiesCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private string $projectDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Écrase les villes existantes avec le même slug');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $force = $input->getOption('force');

        $configPath = $this->projectDir . '/config/local_pages.yaml';

        if (!file_exists($configPath)) {
            $io->error('Fichier config/local_pages.yaml non trouvé');
            return Command::FAILURE;
        }

        $config = Yaml::parseFile($configPath);
        $cities = $config['cities'] ?? [];

        if (empty($cities)) {
            $io->warning('Aucune ville trouvée dans le fichier YAML');
            return Command::SUCCESS;
        }

        $io->title('Import des villes');
        $io->text(sprintf('Villes trouvées dans le YAML: %d', count($cities)));

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $sortOrder = 0;

        $cityRepository = $this->entityManager->getRepository(City::class);

        foreach ($cities as $slug => $data) {
            $existingCity = $cityRepository->findOneBy(['slug' => $slug]);

            if ($existingCity && !$force) {
                $io->text(sprintf('  - %s: ignorée (existe déjà)', $data['name']));
                $skipped++;
                continue;
            }

            $city = $existingCity ?? new City();
            $city->setSlug($slug);
            $city->setName($data['name']);
            $city->setRegion($data['region']);
            $city->setDescription($data['description']);
            $city->setNearby($data['nearby'] ?? []);
            $city->setKeywords($data['keywords'] ?? []);
            $city->setSortOrder($sortOrder++);
            $city->setIsActive(true);

            if ($existingCity) {
                $city->setUpdatedAt(new \DateTimeImmutable());
                $io->text(sprintf('  - %s: mise à jour', $data['name']));
                $updated++;
            } else {
                $this->entityManager->persist($city);
                $io->text(sprintf('  - %s: créée', $data['name']));
                $created++;
            }
        }

        $this->entityManager->flush();

        $io->newLine();
        $io->success(sprintf(
            'Import terminé: %d créée(s), %d mise(s) à jour, %d ignorée(s)',
            $created,
            $updated,
            $skipped
        ));

        return Command::SUCCESS;
    }
}
