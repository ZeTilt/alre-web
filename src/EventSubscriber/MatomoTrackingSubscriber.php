<?php

namespace App\EventSubscriber;

use App\Service\MatomoTrackingService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class MatomoTrackingSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private MatomoTrackingService $matomoTrackingService,
        private string $environment,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::TERMINATE => 'onKernelTerminate',
        ];
    }

    public function onKernelTerminate(TerminateEvent $event): void
    {
        if ($this->environment !== 'prod') {
            return;
        }

        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $path = $request->getPathInfo();

        if (str_starts_with($path, '/admin') || str_starts_with($path, '/_')) {
            return;
        }

        if ($this->matomoTrackingService->isBotRequest($request)) {
            $this->matomoTrackingService->trackBotVisit($request);
        }
    }
}
