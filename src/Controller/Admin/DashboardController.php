<?php

namespace App\Controller\Admin;

use App\Entity\Client;
use App\Entity\Company;
use App\Entity\ContactMessage;
use App\Entity\Devis;
use App\Entity\Facture;
use App\Entity\User;
use App\Entity\Project;
use App\Entity\Partner;
use App\Entity\Testimonial;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class DashboardController extends AbstractDashboardController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ParameterBagInterface $params
    ) {
    }

    #[Route('/saeiblauhjc', name: 'admin')]
    public function index(): Response
    {
        $adminUrlGenerator = $this->container->get(AdminUrlGenerator::class);

        return $this->redirect($adminUrlGenerator->setController(DevisCrudController::class)->generateUrl());
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('Alré Web - Administration')
            ->setFaviconPath('images/favicon.png')
            ->setLocales(['fr' => '🇫🇷 Français'])
            ->setTranslationDomain('admin')
            ->renderContentMaximized()
            ->generateRelativeUrls();
    }

    public function configureAssets(): Assets
    {
        $assets = Assets::new()
            ->addCssFile('css/admin.css')
            ->addJsFile('js/admin-project-partners.js')
            ->addJsFile('js/admin-toggles.js');

        // Charger le CSS de floutage si le mode démo est activé
        if ($this->params->get('app.demo_mode')) {
            $assets->addCssFile('css/admin-blur.css');
        }

        return $assets;
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('Tableau de bord', 'fa fa-home');

        yield MenuItem::section('Site Public');
        yield MenuItem::linkToCrud('Portfolio', 'fas fa-folder-open', Project::class);
        yield MenuItem::linkToCrud('Partenaires', 'fas fa-handshake', Partner::class);
        yield MenuItem::linkToCrud('Témoignages', 'fas fa-star', Testimonial::class);
        yield MenuItem::linkToCrud('Messages de contact', 'fas fa-envelope', ContactMessage::class);

        yield MenuItem::section('Gestion commerciale');
        yield MenuItem::linkToCrud('Devis', 'fas fa-file-invoice', Devis::class);
        yield MenuItem::linkToCrud('Factures', 'fas fa-file-invoice-dollar', Facture::class);

        yield MenuItem::section('Clients');
        yield MenuItem::linkToCrud('Clients', 'fas fa-users', Client::class);

        yield MenuItem::section('Administration');
        yield MenuItem::linkToCrud('Mon Entreprise', 'fas fa-building', Company::class);
        yield MenuItem::linkToCrud('Utilisateurs', 'fas fa-user', User::class);

        if ($this->isGranted('ROLE_USER')) {
            yield MenuItem::section('');
        }
        yield MenuItem::linkToRoute('Retour au site', 'fas fa-external-link-alt', 'app_home');

        if ($this->isGranted('ROLE_USER')) {
            yield MenuItem::linkToLogout('Déconnexion', 'fas fa-sign-out-alt');
        }
    }

}