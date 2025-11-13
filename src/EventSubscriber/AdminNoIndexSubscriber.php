<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class AdminNoIndexSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => 'onKernelResponse',
        ];
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $pathInfo = $request->getPathInfo();

        // Bloquer l'indexation de toutes les pages admin sans révéler l'URL dans robots.txt
        if (str_starts_with($pathInfo, '/saeiblauhjc')) {
            $response = $event->getResponse();
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow, noarchive');
        }
    }
}
