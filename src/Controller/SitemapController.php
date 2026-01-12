<?php

namespace App\Controller;

use App\Repository\ProjectRepository;
use App\Service\LocalPageService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class SitemapController extends AbstractController
{
    public function __construct(
        private LocalPageService $localPageService,
    ) {}

    #[Route('/sitemap.xml', name: 'sitemap', defaults: ['_format' => 'xml'])]
    public function index(ProjectRepository $projectRepository): Response
    {
        // Liste des URLs statiques avec leurs priorités
        $urls = [];

        // Page d'accueil
        $urls[] = [
            'loc' => $this->generateUrl('app_home', [], UrlGeneratorInterface::ABSOLUTE_URL),
            'changefreq' => 'weekly',
            'priority' => '1.0',
        ];

        // Page Services
        $urls[] = [
            'loc' => $this->generateUrl('app_services', [], UrlGeneratorInterface::ABSOLUTE_URL),
            'changefreq' => 'monthly',
            'priority' => '0.9',
        ];

        // Page Portfolio
        $urls[] = [
            'loc' => $this->generateUrl('app_portfolio', [], UrlGeneratorInterface::ABSOLUTE_URL),
            'changefreq' => 'weekly',
            'priority' => '0.8',
        ];

        // Page À propos
        $urls[] = [
            'loc' => $this->generateUrl('app_about', [], UrlGeneratorInterface::ABSOLUTE_URL),
            'changefreq' => 'monthly',
            'priority' => '0.7',
        ];

        // Page Écoconception
        $urls[] = [
            'loc' => $this->generateUrl('app_ecoconception', [], UrlGeneratorInterface::ABSOLUTE_URL),
            'changefreq' => 'monthly',
            'priority' => '0.8',
        ];

        // Page Tarifs
        $urls[] = [
            'loc' => $this->generateUrl('app_pricing', [], UrlGeneratorInterface::ABSOLUTE_URL),
            'changefreq' => 'monthly',
            'priority' => '0.8',
        ];

        // Page Contact
        $urls[] = [
            'loc' => $this->generateUrl('app_contact', [], UrlGeneratorInterface::ABSOLUTE_URL),
            'changefreq' => 'monthly',
            'priority' => '0.9',
        ];

        // Pages légales
        $urls[] = [
            'loc' => $this->generateUrl('app_legal_mentions', [], UrlGeneratorInterface::ABSOLUTE_URL),
            'changefreq' => 'yearly',
            'priority' => '0.3',
        ];

        $urls[] = [
            'loc' => $this->generateUrl('app_legal_privacy', [], UrlGeneratorInterface::ABSOLUTE_URL),
            'changefreq' => 'yearly',
            'priority' => '0.3',
        ];

        $urls[] = [
            'loc' => $this->generateUrl('app_legal_cgv', [], UrlGeneratorInterface::ABSOLUTE_URL),
            'changefreq' => 'yearly',
            'priority' => '0.3',
        ];

        // Projets publiés
        $projects = $projectRepository->findPublished();
        foreach ($projects as $project) {
            $urls[] = [
                'loc' => $this->generateUrl('app_portfolio_show', ['slug' => $project->getSlug()], UrlGeneratorInterface::ABSOLUTE_URL),
                'changefreq' => 'monthly',
                'priority' => '0.7',
                'lastmod' => $project->getUpdatedAt()?->format('Y-m-d'),
            ];
        }

        // Pages locales SEO (générées automatiquement depuis les villes en BDD)
        $localPages = $this->localPageService->getAllPages();
        foreach ($localPages as $page) {
            $urls[] = [
                'loc' => $this->generateUrl('app_local_page', ['slug' => $page['url']], UrlGeneratorInterface::ABSOLUTE_URL),
                'changefreq' => 'monthly',
                'priority' => '0.6',
                'lastmod' => $page['cityEntity']->getUpdatedAt()?->format('Y-m-d') ?? $page['cityEntity']->getCreatedAt()->format('Y-m-d'),
            ];
        }

        // Page index des zones d'intervention
        if (count($localPages) > 0) {
            $urls[] = [
                'loc' => $this->generateUrl('app_local_pages_index', [], UrlGeneratorInterface::ABSOLUTE_URL),
                'changefreq' => 'monthly',
                'priority' => '0.7',
            ];
        }

        $response = new Response(
            $this->renderView('sitemap/index.xml.twig', [
                'urls' => $urls,
            ]),
            Response::HTTP_OK
        );

        $response->headers->set('Content-Type', 'text/xml');

        return $response;
    }
}
