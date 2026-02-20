<?php

namespace App\Command;

use App\Repository\ClientSiteRepository;
use App\Service\ClientBingImportService;
use App\Service\ClientGscImportService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:client-seo-auto-sync',
    description: 'Synchronise automatiquement les données SEO clients (GSC API + Bing API)',
)]
class ClientSeoAutoSyncCommand extends Command
{
    public function __construct(
        private ClientGscImportService $gscImportService,
        private ClientBingImportService $bingImportService,
        private ClientSiteRepository $clientSiteRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('bing-only', null, InputOption::VALUE_NONE, 'Synchroniser uniquement Bing')
            ->addOption('gsc-only', null, InputOption::VALUE_NONE, 'Synchroniser uniquement GSC')
            ->addOption('site-id', null, InputOption::VALUE_REQUIRED, 'ID du site a synchroniser')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Forcer la synchronisation')
            ->addOption('full', null, InputOption::VALUE_NONE, 'Import complet (16 mois d\'historique GSC)')
            ->addOption('reset', null, InputOption::VALUE_NONE, 'Purger les donnees existantes avant import (necessite --full)')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $bingOnly = $input->getOption('bing-only');
        $gscOnly = $input->getOption('gsc-only');
        $full = $input->getOption('full');
        $reset = $input->getOption('reset');
        $siteId = $input->getOption('site-id');

        if ($reset && !$full) {
            $io->error('L\'option --reset necessite --full.');
            return Command::FAILURE;
        }

        $io->title('Synchronisation automatique SEO clients');
        $io->text(sprintf('[%s] Demarrage%s...', date('Y-m-d H:i:s'), $full ? ' (import complet 16 mois)' : ''));

        // Reset si demande
        if ($reset) {
            $sites = $siteId
                ? [$this->clientSiteRepository->find($siteId)]
                : $this->clientSiteRepository->findBy(['isActive' => true]);

            foreach (array_filter($sites) as $site) {
                $deleted = $this->gscImportService->resetSite($site);
                $io->text(sprintf(' [!] %s: %d enregistrement(s) purge(s)', $site->getName(), $deleted));
            }
            $io->newLine();
        }

        $hasErrors = false;

        // GSC API sync
        if (!$bingOnly) {
            $io->section('Google Search Console - Sites clients');
            $gscResults = $siteId
                ? [['site' => ($s = $this->clientSiteRepository->find($siteId)) ? $s->getName() : '?', 'result' => $s ? $this->gscImportService->importForSite($s, $full) : ['message' => 'Site introuvable']]]
                : $this->gscImportService->importForAllSites($full);

            if (empty($gscResults)) {
                $io->note('Aucun site client actif.');
            } else {
                foreach ($gscResults as $entry) {
                    $result = $entry['result'];
                    $icon = str_contains($result['message'], 'Erreur') ? '!' : 'v';
                    $io->text(sprintf(' [%s] %s: %s', $icon, $entry['site'], $result['message']));

                    if (str_contains($result['message'], 'Erreur')) {
                        $hasErrors = true;
                    }
                }
            }
        }

        // Bing API sync
        if (!$gscOnly) {
            $io->section('Bing Webmaster Tools - Sites clients');
            $bingResults = $this->bingImportService->importForAllSites();

            if (empty($bingResults)) {
                $io->note('Aucun site client avec Bing active.');
            } else {
                foreach ($bingResults as $entry) {
                    $result = $entry['result'];
                    $icon = str_contains($result['message'], 'Erreur') ? '!' : 'v';
                    $io->text(sprintf(' [%s] %s: %s', $icon, $entry['site'], $result['message']));

                    if (str_contains($result['message'], 'Erreur')) {
                        $hasErrors = true;
                    }
                }
            }
        }

        $io->newLine();
        $io->text(sprintf('[%s] Termine.', date('Y-m-d H:i:s')));

        return $hasErrors ? Command::FAILURE : Command::SUCCESS;
    }
}
