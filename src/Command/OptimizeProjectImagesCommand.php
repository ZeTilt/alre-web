<?php

namespace App\Command;

use App\Entity\Project;
use App\Service\ImageOptimizerService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:optimize-project-images',
    description: 'Optimise toutes les images des projets existants et génère les variantes',
)]
class OptimizeProjectImagesCommand extends Command
{
    private EntityManagerInterface $entityManager;
    private ImageOptimizerService $imageOptimizer;
    private string $projectDir;

    public function __construct(
        EntityManagerInterface $entityManager,
        ImageOptimizerService $imageOptimizer,
        string $projectDir
    ) {
        parent::__construct();
        $this->entityManager = $entityManager;
        $this->imageOptimizer = $imageOptimizer;
        $this->projectDir = $projectDir;
    }

    protected function configure(): void
    {
        $this
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Force l\'optimisation même si les variantes existent déjà')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $force = $input->getOption('force');

        $io->title('Optimisation des images de projets');

        // Récupérer tous les projets avec une image
        $projects = $this->entityManager->getRepository(Project::class)->findAll();
        $projectsWithImages = array_filter($projects, fn($p) => $p->getImageFilename() !== null);

        if (empty($projectsWithImages)) {
            $io->warning('Aucun projet avec image trouvé.');
            return Command::SUCCESS;
        }

        $io->info(sprintf('Trouvé %d projet(s) avec image(s)', count($projectsWithImages)));

        $successCount = 0;
        $errorCount = 0;
        $skippedCount = 0;

        $io->progressStart(count($projectsWithImages));

        foreach ($projectsWithImages as $project) {
            $imageFilename = $project->getImageFilename();
            $imagePath = $this->projectDir . '/public/uploads/projects/' . $imageFilename;

            // Vérifier que le fichier existe
            if (!file_exists($imagePath)) {
                $io->warning("Image non trouvée : {$imageFilename} (Projet: {$project->getTitle()})");
                $errorCount++;
                $io->progressAdvance();
                continue;
            }

            // Vérifier si les variantes existent déjà (sauf si --force)
            if (!$force && $this->variantsExist($imagePath)) {
                $skippedCount++;
                $io->progressAdvance();
                continue;
            }

            try {
                // Optimiser l'image et générer les variantes
                $results = $this->imageOptimizer->optimize($imagePath, [
                    'quality' => 85,
                    'formats' => ['webp'],
                    'sizes' => ['thumbnail', 'medium', 'large']
                ]);

                $successCount++;
            } catch (\Exception $e) {
                $io->error("Erreur pour {$imageFilename}: " . $e->getMessage());
                $errorCount++;
            }

            $io->progressAdvance();
        }

        $io->progressFinish();

        // Afficher le résumé
        $io->newLine(2);
        $io->section('Résumé');

        $io->table(
            ['Statut', 'Nombre'],
            [
                ['✓ Optimisées', $successCount],
                ['⊘ Ignorées (déjà optimisées)', $skippedCount],
                ['✗ Erreurs', $errorCount],
                ['Total traité', count($projectsWithImages)],
            ]
        );

        if ($errorCount > 0) {
            $io->warning(sprintf('%d image(s) n\'ont pas pu être optimisée(s)', $errorCount));
            return Command::FAILURE;
        }

        $io->success('Optimisation terminée avec succès !');

        if ($skippedCount > 0) {
            $io->note(sprintf('%d image(s) déjà optimisée(s) ont été ignorée(s). Utilisez --force pour forcer la ré-optimisation.', $skippedCount));
        }

        return Command::SUCCESS;
    }

    /**
     * Vérifie si les variantes d'une image existent déjà
     */
    private function variantsExist(string $imagePath): bool
    {
        $pathInfo = pathinfo($imagePath);
        $dir = $pathInfo['dirname'];
        $filename = $pathInfo['filename'];
        $ext = $pathInfo['extension'];

        // Vérifier l'existence de quelques variantes clés
        $checkFiles = [
            $dir . '/' . $filename . '-thumbnail.' . $ext,
            $dir . '/' . $filename . '-medium.' . $ext,
            $dir . '/' . $filename . '.webp',
        ];

        foreach ($checkFiles as $file) {
            if (file_exists($file)) {
                return true; // Au moins une variante existe
            }
        }

        return false;
    }
}
