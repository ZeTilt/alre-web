<?php

namespace App\Controller;

use App\Service\LocalPageService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ServicesController extends AbstractController
{
    public function __construct(
        private LocalPageService $localPageService,
    ) {}

    #[Route('/services', name: 'app_services')]
    public function index(): Response
    {
        return $this->render('services/index.html.twig');
    }

    #[Route('/creation-site-internet', name: 'app_service_creation')]
    public function creationSiteInternet(): Response
    {
        return $this->render('services/creation-site-internet.html.twig', [
            'citiesByRegion' => $this->localPageService->getCitiesByRegion(),
        ]);
    }

    #[Route('/developpeur-web', name: 'app_service_developpeur')]
    public function developpeurWeb(): Response
    {
        return $this->render('services/developpeur-web.html.twig', [
            'citiesByRegion' => $this->localPageService->getCitiesByRegion(),
        ]);
    }

    #[Route('/agence-web', name: 'app_service_agence')]
    public function agenceWeb(): Response
    {
        return $this->render('services/agence-web.html.twig', [
            'citiesByRegion' => $this->localPageService->getCitiesByRegion(),
        ]);
    }
}
