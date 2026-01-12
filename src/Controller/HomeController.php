<?php

namespace App\Controller;

use App\Repository\CompanyRepository;
use App\Repository\GoogleReviewRepository;
use App\Repository\ProjectRepository;
use App\Repository\TestimonialRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(
        ProjectRepository $projectRepository,
        TestimonialRepository $testimonialRepository,
        CompanyRepository $companyRepository,
        GoogleReviewRepository $googleReviewRepository
    ): Response {
        $featuredProjects = $projectRepository->findFeatured(3);
        $testimonials = $testimonialRepository->findFeatured(3);
        $company = $companyRepository->findOneBy([]);
        $googleReviews = $googleReviewRepository->findApproved(5);
        $googleReviewStats = $googleReviewRepository->getStats();

        return $this->render('home/index.html.twig', [
            'featuredProjects' => $featuredProjects,
            'testimonials' => $testimonials,
            'company' => $company,
            'googleReviews' => $googleReviews,
            'googleReviewStats' => $googleReviewStats,
        ]);
    }
}
