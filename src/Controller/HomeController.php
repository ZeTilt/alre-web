<?php

namespace App\Controller;

use App\Repository\ProjectRepository;
use App\Repository\TestimonialRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(ProjectRepository $projectRepository, TestimonialRepository $testimonialRepository): Response
    {
        $featuredProjects = $projectRepository->findFeatured(3);
        $testimonials = $testimonialRepository->findFeatured(3);

        return $this->render('home/index.html.twig', [
            'featuredProjects' => $featuredProjects,
            'testimonials' => $testimonials,
        ]);
    }
}
