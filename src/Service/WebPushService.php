<?php

namespace App\Service;

use App\Entity\PushSubscription;
use App\Repository\PushSubscriptionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

class WebPushService
{
    private ?WebPush $webPush = null;

    public function __construct(
        private PushSubscriptionRepository $subscriptionRepo,
        private EntityManagerInterface $entityManager,
        private string $vapidSubject,
        private string $vapidPublicKey,
        private string $vapidPrivateKey
    ) {
    }

    private function getWebPush(): WebPush
    {
        if ($this->webPush === null) {
            $this->webPush = new WebPush([
                'VAPID' => [
                    'subject' => $this->vapidSubject,
                    'publicKey' => $this->vapidPublicKey,
                    'privateKey' => $this->vapidPrivateKey,
                ],
            ]);
        }

        return $this->webPush;
    }

    public function getPublicKey(): string
    {
        return $this->vapidPublicKey;
    }

    public function sendNotification(
        string $title,
        string $body,
        ?string $url = null,
        ?string $tag = null
    ): bool {
        $subscription = $this->subscriptionRepo->findLatest();

        if (!$subscription) {
            return false;
        }

        $payload = json_encode([
            'title' => $title,
            'body' => $body,
            'url' => $url ?? '/saeiblauhjc/calendar',
            'tag' => $tag ?? 'event-reminder',
        ]);

        $sub = Subscription::create([
            'endpoint' => $subscription->getEndpoint(),
            'publicKey' => $subscription->getPublicKey(),
            'authToken' => $subscription->getAuthToken(),
        ]);

        try {
            $report = $this->getWebPush()->sendOneNotification($sub, $payload);

            if ($report->isSuccess()) {
                $subscription->setLastUsedAt(new \DateTimeImmutable());
                $this->entityManager->flush();
                return true;
            }

            return false;
        } catch (\Exception $e) {
            return false;
        }
    }
}
