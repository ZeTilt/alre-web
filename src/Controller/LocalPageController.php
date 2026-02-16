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

        if ($parsed['service'] === null) {
            throw $this->createNotFoundException('Page non trouvée');
        }

        $service = $this->localPageService->getService($parsed['service']);
        if (!$service) {
            throw $this->createNotFoundException('Page non trouvée');
        }

        // Department page
        if ($parsed['department'] !== null) {
            $department = $this->localPageService->getDepartment($parsed['department']);
            if (!$department) {
                throw $this->createNotFoundException('Page non trouvée');
            }

            // Get cities for this department
            $citiesByRegion = $this->localPageService->getCitiesByRegion();
            $deptCities = $citiesByRegion[$department->getName()] ?? [];

            return $this->render('local_page/department.html.twig', [
                'service' => $service,
                'serviceSlug' => $parsed['service'],
                'department' => $department,
                'cities' => $deptCities,
            ]);
        }

        // City page
        if ($parsed['city'] === null) {
            throw $this->createNotFoundException('Page non trouvée');
        }

        $city = $this->localPageService->getCity($parsed['city']);
        if (!$city) {
            throw $this->createNotFoundException('Page non trouvée');
        }

        $cityArray = [
            'name' => $city->getName(),
            'region' => $city->getRegion(),
            'title' => $city->getTitleForService($parsed['service']),
            'description' => $city->getDescriptionForService($parsed['service']),
            'descriptionLong' => $city->getLongDescriptionForService($parsed['service']),
            'nearby' => $city->getNearby(),
            'keywords' => $city->getKeywords(),
        ];

        return $this->render('local_page/show.html.twig', [
            'service' => $service,
            'serviceSlug' => $parsed['service'],
            'city' => $cityArray,
            'citySlug' => $parsed['city'],
            'pageTitle' => $service['title'] . ' ' . $city->getName(),
        ]);
    }

    #[Route('/nos-zones-intervention', name: 'app_local_pages_index')]
    public function index(): Response
    {
        $cities = $this->localPageService->getCities();

        // Convertir les entités en tableaux pour le template
        $citiesArray = [];
        foreach ($cities as $city) {
            $citiesArray[$city->getSlug()] = [
                'name' => $city->getName(),
                'region' => $city->getRegion(),
                'description' => $city->getDescription(),
                'nearby' => $city->getNearby(),
                'keywords' => $city->getKeywords(),
            ];
        }

        return $this->render('local_page/index.html.twig', [
            'pages' => $this->localPageService->getAllPages(),
            'cities' => $citiesArray,
            'services' => $this->localPageService->getServices(),
        ]);
    }
}
