<?php

namespace App\Controller;

use App\Service\LocalPageService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class LocalPageController extends AbstractController
{
    public function __construct(
        private LocalPageService $localPageService,
    ) {}

    #[Route('/{slug}', name: 'app_local_page', requirements: ['slug' => '[a-z]+-[a-z-]+'], priority: -100)]
    public function show(string $slug): Response
    {
        $parsed = $this->localPageService->parseSlug($slug);

        if ($parsed['service'] === null || $parsed['city'] === null) {
            throw $this->createNotFoundException('Page non trouvée');
        }

        $service = $this->localPageService->getService($parsed['service']);
        $city = $this->localPageService->getCity($parsed['city']);

        if (!$service || !$city) {
            throw $this->createNotFoundException('Page non trouvée');
        }

        return $this->render('local_page/show.html.twig', [
            'service' => $service,
            'serviceSlug' => $parsed['service'],
            'city' => $city,
            'citySlug' => $parsed['city'],
            'pageTitle' => $service['title'] . ' ' . $city['name'],
        ]);
    }

    #[Route('/nos-zones-intervention', name: 'app_local_pages_index')]
    public function index(): Response
    {
        return $this->render('local_page/index.html.twig', [
            'pages' => $this->localPageService->getAllPages(),
            'cities' => $this->localPageService->getCities(),
            'services' => $this->localPageService->getServices(),
        ]);
    }
}
