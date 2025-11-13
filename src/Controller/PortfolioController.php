<?php

namespace App\Controller;

use App\Repository\ProjectRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class PortfolioController extends AbstractController
{
    #[Route('/portfolio', name: 'app_portfolio')]
    public function index(ProjectRepository $projectRepository): Response
    {
        // Récupérer tous les projets publiés, triés par date de réalisation
        $projects = $projectRepository->findPublished();

        return $this->render('portfolio/index.html.twig', [
            'projects' => $projects,
        ]);
    }
}
