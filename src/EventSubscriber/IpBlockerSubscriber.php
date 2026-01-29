<?php

namespace App\EventSubscriber;

use App\Service\IpSecurityService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Blocks requests from blacklisted IPs very early in the request lifecycle.
 * Priority 1000 ensures this runs before most other listeners.
 */
class IpBlockerSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private IpSecurityService $ipSecurityService,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 1000],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $clientIp = IpSecurityService::getClientIp($request);

        // Check if IP is blocked
        if ($this->ipSecurityService->isIpBlocked($clientIp)) {
            // Record the hit
            $blockedIp = $this->ipSecurityService->getBlockedIp($clientIp);
            if ($blockedIp) {
                $this->ipSecurityService->recordBlockedHit($blockedIp);
            }

            // Return 403 Forbidden
            $response = new Response(
                'Forbidden - Your IP has been blocked due to suspicious activity.',
                Response::HTTP_FORBIDDEN,
                ['Content-Type' => 'text/plain']
            );

            $event->setResponse($response);
        }
    }
}
