<?php

namespace App\Controller\Admin;

use App\Service\IndexNowService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Route publique uniquement (IndexNow key file).
 * La page de config Bing admin est dans DashboardController.
 */
class BingConfigController extends AbstractController
{
    /**
     * Serve la cle IndexNow au format {key}.txt (route publique).
     */
    #[Route('/{key}.txt', name: 'indexnow_key_file', requirements: ['key' => '[a-f0-9]{32,}'], priority: -100)]
    public function indexNowKeyFile(string $key, IndexNowService $indexNowService): Response
    {
        $configuredKey = $indexNowService->getKey();

        if ($configuredKey === null || $configuredKey !== $key) {
            throw $this->createNotFoundException();
        }

        return new Response($configuredKey, 200, [
            'Content-Type' => 'text/plain; charset=utf-8',
        ]);
    }
}
