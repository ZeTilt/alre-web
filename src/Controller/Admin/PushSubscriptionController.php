<?php

namespace App\Controller\Admin;

use App\Entity\PushSubscription;
use App\Repository\PushSubscriptionRepository;
use App\Service\WebPushService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/saeiblauhjc/push')]
#[IsGranted('ROLE_USER')]
class PushSubscriptionController extends AbstractController
{
    #[Route('/vapid-public-key', name: 'admin_push_vapid_key', methods: ['GET'])]
    public function getVapidPublicKey(WebPushService $webPush): JsonResponse
    {
        return new JsonResponse([
            'publicKey' => $webPush->getPublicKey()
        ]);
    }

    #[Route('/subscribe', name: 'admin_push_subscribe', methods: ['POST'])]
    public function subscribe(
        Request $request,
        EntityManagerInterface $em,
        PushSubscriptionRepository $repo
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        if (!$data || !isset($data['endpoint'], $data['keys']['p256dh'], $data['keys']['auth'])) {
            return new JsonResponse(['error' => 'Invalid subscription data'], 400);
        }

        // Supprimer les anciennes subscriptions
        $repo->deleteAll();

        $subscription = new PushSubscription();
        $subscription->setEndpoint($data['endpoint']);
        $subscription->setPublicKey($data['keys']['p256dh']);
        $subscription->setAuthToken($data['keys']['auth']);

        $em->persist($subscription);
        $em->flush();

        return new JsonResponse(['success' => true]);
    }

    #[Route('/unsubscribe', name: 'admin_push_unsubscribe', methods: ['POST'])]
    public function unsubscribe(
        EntityManagerInterface $em,
        PushSubscriptionRepository $repo
    ): JsonResponse {
        $repo->deleteAll();
        $em->flush();

        return new JsonResponse(['success' => true]);
    }

    #[Route('/test', name: 'admin_push_test', methods: ['POST'])]
    public function testNotification(WebPushService $webPush): JsonResponse
    {
        $result = $webPush->sendNotification(
            'Test',
            'Les notifications fonctionnent !',
            '/saeiblauhjc/calendar'
        );

        return new JsonResponse(['success' => $result]);
    }
}
