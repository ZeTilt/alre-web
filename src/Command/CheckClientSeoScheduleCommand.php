<?php

namespace App\Command;

use App\Repository\ClientSiteRepository;
use App\Service\WebPushService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[AsCommand(
    name: 'app:check-client-seo-schedule',
    description: 'Check client SEO schedule and send push notifications for due imports/reports',
)]
class CheckClientSeoScheduleCommand extends Command
{
    public function __construct(
        private ClientSiteRepository $clientSiteRepository,
        private WebPushService $webPushService,
        private UrlGeneratorInterface $urlGenerator,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $sites = $this->clientSiteRepository->findAllActive();

        $notificationsSent = 0;

        foreach ($sites as $site) {
            if ($site->isImportDue()) {
                $url = $this->urlGenerator->generate(
                    'admin_client_seo_import',
                    ['id' => $site->getId()],
                    UrlGeneratorInterface::ABSOLUTE_URL
                );

                $sent = $this->webPushService->sendNotification(
                    'SEO Client : action requise',
                    'Import du pour ' . $site->getName(),
                    $url,
                    'client-seo-import-' . $site->getId()
                );

                if ($sent) {
                    $notificationsSent++;
                    $io->info('Import notification sent for: ' . $site->getName());
                }
            }

            if ($site->isReportDue()) {
                $url = $this->urlGenerator->generate(
                    'admin_client_seo_dashboard',
                    ['id' => $site->getId()],
                    UrlGeneratorInterface::ABSOLUTE_URL
                );

                $sent = $this->webPushService->sendNotification(
                    'SEO Client : action requise',
                    'Rapport a preparer pour ' . $site->getName(),
                    $url,
                    'client-seo-report-' . $site->getId()
                );

                if ($sent) {
                    $notificationsSent++;
                    $io->info('Report notification sent for: ' . $site->getName());
                }
            }
        }

        $io->success(sprintf('%d notification(s) sent for %d active site(s).', $notificationsSent, count($sites)));

        return Command::SUCCESS;
    }
}
