<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class TestErrorController extends AbstractController
{
    #[Route('/test-error-500-monitoring', name: 'app_test_error_500')]
    public function testError500(): Response
    {
        // Cette route est temporaire pour tester le monitoring des erreurs
        throw new \RuntimeException(
            'Ceci est une erreur de test pour vérifier le monitoring email. ' .
            'Timestamp: ' . date('Y-m-d H:i:s') . '. ' .
            'Cette exception devrait déclencher un email à contact@alre-web.bzh'
        );
    }
}
