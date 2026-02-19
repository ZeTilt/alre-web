<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ServicesController extends AbstractController
{
    #[Route('/services', name: 'app_services')]
    public function index(): Response
    {
        return $this->render('services/index.html.twig');
    }

    #[Route('/creation-site-internet', name: 'app_service_creation')]
    public function creationSiteInternet(): Response
    {
        return $this->render('services/creation-site-internet.html.twig');
    }

    #[Route('/developpeur-web', name: 'app_service_developpeur')]
    public function developpeurWeb(): Response
    {
        return $this->render('services/developpeur-web.html.twig');
    }

    #[Route('/agence-web', name: 'app_service_agence')]
    public function agenceWeb(): Response
    {
        return $this->render('services/agence-web.html.twig');
    }

    #[Route('/referencement-local', name: 'app_service_referencement')]
    public function referencementLocal(): Response
    {
        return $this->render('services/referencement-local.html.twig');
    }

    #[Route('/optimisation-ia', name: 'app_service_optimisation_ia')]
    public function optimisationIa(): Response
    {
        return $this->render('services/optimisation-ia.html.twig');
    }
}
