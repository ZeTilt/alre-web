<?php

namespace App\Controller;

use App\Repository\CompanyRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class AboutController extends AbstractController
{
    #[Route('/a-propos', name: 'app_about')]
    public function index(CompanyRepository $companyRepository): Response
    {
        $company = $companyRepository->findOneBy([]);

        return $this->render('about/index.html.twig', [
            'company' => $company,
        ]);
    }
}
