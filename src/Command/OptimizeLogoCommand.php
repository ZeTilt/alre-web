<?php

namespace App\Command;

use App\Service\ImageOptimizerService;
use App\Service\ImageVariantGenerator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:optimize-logo',
    description: 'Optimise le logo principal et génère toutes les variantes (navbar, footer, OG, etc.)',
)]
class OptimizeLogoCommand extends Command
{
    private ImageOptimizerService $imageOptimizer;
    private ImageVariantGenerator $variantGenerator;
    private string $publicDir;

    public function __construct(
        ImageOptimizerService $imageOptimizer,
        ImageVariantGenerator $variantGenerator,
        string $projectDir
    ) {
        parent::__construct();
        $this->imageOptimizer = $imageOptimizer;
        $this->variantGenerator = $variantGenerator;
        $this->publicDir = $projectDir . '/public';
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Optimisation du logo principal');

        $logoPath = $this->publicDir . '/images/logo.png';

        if (!file_exists($logoPath)) {
            $io->error("Le fichier logo n'existe pas : $logoPath");
            return Command::FAILURE;
        }

        // Afficher la taille avant optimisation
        $sizeBefore = filesize($logoPath);
        $io->info(sprintf('Taille du logo avant optimisation : %s', $this->formatBytes($sizeBefore)));

        try {
            // 1. Générer toutes les variantes du logo
            $io->section('Génération des variantes du logo');
            $variants = $this->variantGenerator->generateLogoVariants($logoPath);

            $io->success(sprintf('✓ %d variantes générées', count($variants)));

            // Afficher les détails des variantes générées
            $io->table(
                ['Type', 'Fichier', 'Taille'],
                $this->formatVariantsTable($variants)
            );

            // Afficher la taille après optimisation
            $sizeAfter = filesize($logoPath);
            $reduction = (($sizeBefore - $sizeAfter) / $sizeBefore) * 100;

            $io->newLine();
            $io->info(sprintf('Taille du logo après optimisation : %s', $this->formatBytes($sizeAfter)));
            $io->success(sprintf('Réduction de taille : %.1f%%', $reduction));

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $io->error('Erreur lors de l\'optimisation : ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    /**
     * Formate les variantes pour l'affichage en table
     */
    private function formatVariantsTable(array $variants): array
    {
        $rows = [];

        foreach ($variants as $type => $path) {
            if ($path && file_exists($path)) {
                $filename = basename($path);
                $size = $this->formatBytes(filesize($path));
                $rows[] = [$type, $filename, $size];
            }
        }

        return $rows;
    }

    /**
     * Formate les octets en format lisible
     */
    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return sprintf('%.2f MB', $bytes / 1048576);
        } elseif ($bytes >= 1024) {
            return sprintf('%.2f KB', $bytes / 1024);
        }
        return $bytes . ' B';
    }
}
