<?php

namespace App\Command;

use App\Service\ImageVariantGenerator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:generate-favicons',
    description: 'Génère tous les favicons (16x16, 32x32, Apple Touch Icon, etc.) à partir d\'une image source',
)]
class GenerateFaviconsCommand extends Command
{
    private ImageVariantGenerator $variantGenerator;
    private string $publicDir;

    public function __construct(
        ImageVariantGenerator $variantGenerator,
        string $projectDir
    ) {
        parent::__construct();
        $this->variantGenerator = $variantGenerator;
        $this->publicDir = $projectDir . '/public';
    }

    protected function configure(): void
    {
        $this
            ->addArgument('source', InputArgument::OPTIONAL, 'Chemin vers l\'image source (relatif à public/)', 'images/favicon.png')
            ->addOption('site-name', null, InputOption::VALUE_REQUIRED, 'Nom du site pour manifest.json', 'Alré Web')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Génération des favicons');

        $sourceRelative = $input->getArgument('source');
        $sourcePath = $this->publicDir . '/' . $sourceRelative;

        if (!file_exists($sourcePath)) {
            $io->error("L'image source n'existe pas : $sourcePath");
            $io->note("Astuce : Le chemin doit être relatif au dossier public/");
            return Command::FAILURE;
        }

        $io->info(sprintf('Image source : %s (%.2f KB)', $sourceRelative, filesize($sourcePath) / 1024));

        try {
            // 1. Générer tous les favicons
            $io->section('Génération des favicons');
            $variants = $this->variantGenerator->generateFavicons($sourcePath);

            $io->success(sprintf('✓ %d favicons générés', count($variants)));

            // Afficher les détails des favicons générés
            $io->table(
                ['Type', 'Fichier', 'Taille', 'Dimensions'],
                $this->formatVariantsTable($variants)
            );

            // 2. Générer manifest.json
            $io->section('Génération du manifest.json');
            $siteName = $input->getOption('site-name');
            $manifestPath = $this->variantGenerator->generateManifest($siteName, $variants);

            $io->success('✓ Manifest.json généré : ' . basename($manifestPath));

            $io->newLine();
            $io->success('Tous les favicons ont été générés avec succès !');

            // Afficher les instructions d'intégration
            $this->displayIntegrationInstructions($io);

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $io->error('Erreur lors de la génération : ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    /**
     * Formate les variantes pour l'affichage en table
     */
    private function formatVariantsTable(array $variants): array
    {
        $rows = [];

        $dimensions = [
            'favicon_16' => '16x16',
            'favicon_32' => '32x32',
            'favicon_48' => '48x48',
            'apple_touch_icon' => '180x180',
            'android_chrome_192' => '192x192',
            'android_chrome_512' => '512x512',
            'favicon_ico' => 'multi',
            'favicon_svg' => 'scalable',
        ];

        foreach ($variants as $type => $path) {
            if ($path && file_exists($path)) {
                $filename = basename($path);
                $size = $this->formatBytes(filesize($path));
                $dim = $dimensions[$type] ?? 'N/A';
                $rows[] = [$type, $filename, $size, $dim];
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

    /**
     * Affiche les instructions d'intégration dans le HTML
     */
    private function displayIntegrationInstructions(SymfonyStyle $io): void
    {
        $io->section('Instructions d\'intégration');

        $io->text('Ajoutez ces lignes dans le <head> de votre base.html.twig :');

        $io->block(<<<'HTML'
<!-- Favicons -->
<link rel="icon" type="image/png" sizes="16x16" href="{{ asset('images/favicon-16x16.png') }}">
<link rel="icon" type="image/png" sizes="32x32" href="{{ asset('images/favicon-32x32.png') }}">
<link rel="icon" type="image/png" sizes="48x48" href="{{ asset('images/favicon-48x48.png') }}">
<link rel="apple-touch-icon" sizes="180x180" href="{{ asset('images/apple-touch-icon.png') }}">
<link rel="icon" type="image/svg+xml" href="{{ asset('images/favicon.svg') }}">
<link rel="manifest" href="{{ asset('manifest.json') }}">
<meta name="theme-color" content="#3A4556">
HTML
        , null, 'fg=cyan');
    }
}
