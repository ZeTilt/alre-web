<?php

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

#[AsEventListener(event: KernelEvents::RESPONSE)]
class SecurityHeadersListener
{
    public function __invoke(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $response = $event->getResponse();

        // Force HTTPS avec HSTS (HTTP Strict Transport Security)
        // max-age=31536000 : 1 an
        // includeSubDomains : applique aussi aux sous-domaines
        $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');

        // Empêche le chargement de contenu mixte (HTTP sur page HTTPS)
        $response->headers->set('Content-Security-Policy', "upgrade-insecure-requests");

        // Protection XSS
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-XSS-Protection', '1; mode=block');

        // Référer policy
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
    }
}
