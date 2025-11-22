<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class EcoconceptionController extends AbstractController
{
    #[Route('/ecoconception', name: 'app_ecoconception')]
    public function index(): Response
    {
        return $this->render('ecoconception/index.html.twig');
    }
}
