<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class SitemapController extends AbstractController
{
    #[Route('/sitemap.xml', name: 'sitemap', defaults: ['_format' => 'xml'])]
    public function index(): Response
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
