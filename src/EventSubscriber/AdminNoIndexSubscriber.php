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
            KernelEvents::RESPONSE => ['onKernelResponse', -100], // Priorité basse pour s'exécuter après d'autres middlewares
        ];
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $response = $event->getResponse();
        $pathInfo = $request->getPathInfo();

        if (str_starts_with($pathInfo, '/saeiblauhjc')) {
            // Bloquer l'indexation des pages admin
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow, noarchive');
        } else {
            // Forcer l'indexation des pages publiques
            // (pour contrer une éventuelle config serveur qui ajoute noindex)
            $response->headers->set('X-Robots-Tag', 'index, follow');
        }
    }
}
