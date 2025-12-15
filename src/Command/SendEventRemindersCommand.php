<?php

namespace App\Command;

use App\Entity\Event;
use App\Repository\EventRepository;
use App\Service\WebPushService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:send-event-reminders',
    description: 'Envoie des notifications push pour les événements à venir (H-1 et H-10min)',
)]
class SendEventRemindersCommand extends Command
{
    public function __construct(
        private EventRepository $eventRepo,
        private WebPushService $webPush
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $tz = new \DateTimeZone('Europe/Paris');
        $now = new \DateTimeImmutable('now', $tz);
        $sentCount = 0;

        // Rappels H-1 (fenêtre de 5 minutes autour de H-1)
        $in1Hour = $now->modify('+1 hour');
        $events1h = $this->eventRepo->findEventsStartingBetween(
            $in1Hour->modify('-2 minutes'),
            $in1Hour->modify('+3 minutes')
        );

        foreach ($events1h as $event) {
            if ($this->sendReminder($event, 'Dans 1 heure')) {
                $io->text("Rappel H-1 envoyé: {$event->getTitle()}");
                $sentCount++;
            }
        }

        // Rappels H-10min (fenêtre de 5 minutes autour de H-10min)
        $in10Min = $now->modify('+10 minutes');
        $events10m = $this->eventRepo->findEventsStartingBetween(
            $in10Min->modify('-2 minutes'),
            $in10Min->modify('+3 minutes')
        );

        foreach ($events10m as $event) {
            if ($this->sendReminder($event, 'Dans 10 minutes')) {
                $io->text("Rappel H-10min envoyé: {$event->getTitle()}");
                $sentCount++;
            }
        }

        if ($sentCount > 0) {
            $io->success("{$sentCount} notification(s) envoyée(s)");
        } else {
            $io->info('Aucun rappel à envoyer');
        }

        return Command::SUCCESS;
    }

    private function sendReminder(Event $event, string $timing): bool
    {
        $title = $event->getTitle();
        $body = $timing;

        if (!$event->isAllDay()) {
            $body .= ' à ' . $event->getStartAt()->format('H:i');
        }

        if ($event->getLocation()) {
            $body .= ' - ' . $event->getLocation();
        }

        return $this->webPush->sendNotification(
            $title,
            $body,
            '/saeiblauhjc?crudAction=detail&crudControllerFqcn=App%5CController%5CAdmin%5CEventCrudController&entityId=' . $event->getId(),
            'event-' . $event->getId()
        );
    }
}
