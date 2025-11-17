<?php

namespace App\Controller;

use App\Entity\Project;
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

    #[Route('/portfolio/{slug}', name: 'app_portfolio_show')]
    public function show(Project $project): Response
    {
        // Vérifier que le projet est publié
        if (!$project->isPublished()) {
            throw $this->createNotFoundException('Ce projet n\'est pas disponible.');
        }

        return $this->render('portfolio/show.html.twig', [
            'project' => $project,
        ]);
    }
}
