<?php

namespace App\DataFixtures;

use App\Entity\Client;
use App\Entity\Company;
use App\Entity\Devis;
use App\Entity\DevisItem;
use App\Entity\Facture;
use App\Entity\FactureItem;
use App\Entity\Partner;
use App\Entity\Project;
use App\Entity\ProjectImage;
use App\Entity\ProjectPartner;
use App\Entity\Testimonial;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    private UserPasswordHasherInterface $passwordHasher;

    public function __construct(UserPasswordHasherInterface $passwordHasher)
    {
        $this->passwordHasher = $passwordHasher;
    }

    public function load(ObjectManager $manager): void
    {
        // 1. Create Company
        $company = $this->createCompany($manager);

        // 2. Create User
        $user = $this->createUser($manager);

        // 3. Create Clients (12 clients pour 16 devis)
        $clients = $this->createClients($manager);

        // 4. Create Partners
        $partners = $this->createPartners($manager);

        // 5. Create Projects (fictifs + réel)
        $projects = $this->createProjects($manager, $clients, $partners);

        // 6. Create Testimonials
        $this->createTestimonials($manager, $clients);

        // 7. Create Devis & Factures (cohérents)
        $this->createDevisAndFactures($manager, $user, $clients);

        $manager->flush();

        $stats = $this->getStats($manager);

        echo "\n✓ Fixtures loaded successfully!\n";
        echo "  - Company: 1\n";
        echo "  - Users: 1\n";
        echo "  - Clients: " . count($clients) . "\n";
        echo "  - Partners: " . count($partners) . "\n";
        echo "  - Projects: " . count($projects) . "\n";
        echo "  - Devis: " . $stats['devis'] . "\n";
        echo "  - Factures: " . $stats['factures'] . "\n";
        echo "  - Testimonials: 3\n";
    }

    private function createCompany(ObjectManager $manager): Company
    {
        $company = new Company();
        $company->setName('Alré Web');
        $company->setOwnerName('Fabrice Dhuicque');
        $company->setTitle('Développeur Web Freelance');
        $company->setAddress('123 Rue de la Tech');
        $company->setPostalCode('59000');
        $company->setCity('Lille');
        $company->setPhone('06 12 34 56 78');
        $company->setEmail('contact@alre-web.bzh');
        $company->setSiret('12345678900012');
        $company->setWebsite('https://alre-web.bzh');
        $company->setLegalStatus('Auto-entrepreneur');
        $company->setLegalMentions('TVA non applicable, article 293 B du CGI');

        $manager->persist($company);

        return $company;
    }

    private function createUser(ObjectManager $manager): User
    {
        $user = new User();
        $user->setUsername('fabrice');
        $user->setEmail('fabrice@alre-web.bzh');
        $user->setRoles(['ROLE_ADMIN']);
        $hashedPassword = $this->passwordHasher->hashPassword($user, 'admin123');
        $user->setPassword($hashedPassword);

        $manager->persist($user);

        return $user;
    }

    private function createClients(ObjectManager $manager): array
    {
        $clientsData = [
            [
                'name' => 'TechStart SAS',
                'contactFirstName' => 'Sophie',
                'contactLastName' => 'Martin',
                'email' => 'contact@techstart.fr',
                'phone' => '01 23 45 67 89',
                'address' => '15 Avenue de l\'Innovation',
                'postalCode' => '75001',
                'city' => 'Paris',
                'siret' => '88877766600011',
                'url' => 'https://techstart.fr',
            ],
            [
                'name' => 'BioBoutique',
                'contactFirstName' => 'Marie',
                'contactLastName' => 'Dupont',
                'email' => 'marie@bioboutique.com',
                'phone' => '02 34 56 78 90',
                'address' => '8 Rue du Commerce',
                'postalCode' => '44000',
                'city' => 'Nantes',
                'siret' => '77766655500022',
                'url' => 'https://bioboutique.com',
            ],
            [
                'name' => 'Architect Studio',
                'contactFirstName' => 'Thomas',
                'contactLastName' => 'Legrand',
                'email' => 'thomas@architectstudio.fr',
                'phone' => '03 45 67 89 01',
                'address' => '42 Boulevard Haussmann',
                'postalCode' => '69001',
                'city' => 'Lyon',
                'siret' => '66655544400033',
                'url' => 'https://architectstudio.fr',
            ],
            [
                'name' => 'Restaurant Le Gourmet',
                'contactFirstName' => 'Pierre',
                'contactLastName' => 'Rousseau',
                'email' => 'contact@legourmet-restaurant.fr',
                'phone' => '04 56 78 90 12',
                'address' => '27 Place du Marché',
                'postalCode' => '33000',
                'city' => 'Bordeaux',
                'siret' => '55544433300044',
                'url' => null,
            ],
            [
                'name' => 'Fitness Plus',
                'contactFirstName' => 'Julie',
                'contactLastName' => 'Bernard',
                'email' => 'julie@fitnessplus.com',
                'phone' => '05 67 89 01 23',
                'address' => '10 Rue du Sport',
                'postalCode' => '59000',
                'city' => 'Lille',
                'siret' => '44433322200055',
                'url' => 'https://fitnessplus.com',
            ],
            [
                'name' => 'Agence Immobilière Prestige',
                'contactFirstName' => 'Laurent',
                'contactLastName' => 'Petit',
                'email' => 'l.petit@prestige-immo.fr',
                'phone' => '06 78 90 12 34',
                'address' => '5 Avenue de la République',
                'postalCode' => '13001',
                'city' => 'Marseille',
                'siret' => '33322211100066',
                'url' => 'https://prestige-immo.fr',
            ],
            [
                'name' => 'Boutique de Mode Élégance',
                'contactFirstName' => 'Isabelle',
                'contactLastName' => 'Moreau',
                'email' => 'contact@boutique-elegance.fr',
                'phone' => '01 45 67 89 12',
                'address' => '32 Rue de la Mode',
                'postalCode' => '75008',
                'city' => 'Paris',
                'siret' => '22211100099977',
                'url' => 'https://boutique-elegance.fr',
            ],
            [
                'name' => 'Cabinet d\'Avocat Juridis',
                'contactFirstName' => 'Maître François',
                'contactLastName' => 'Dubois',
                'email' => 'contact@cabinet-juridis.fr',
                'phone' => '02 98 76 54 32',
                'address' => '18 Boulevard des Avocats',
                'postalCode' => '35000',
                'city' => 'Rennes',
                'siret' => '11100099988877',
                'url' => 'https://cabinet-juridis.fr',
            ],
            [
                'name' => 'École de Musique Harmony',
                'contactFirstName' => 'Céline',
                'contactLastName' => 'Leroy',
                'email' => 'contact@ecole-harmony.fr',
                'phone' => '03 20 45 67 89',
                'address' => '7 Rue des Arts',
                'postalCode' => '59800',
                'city' => 'Lille',
                'siret' => '99988877766655',
                'url' => null,
            ],
            [
                'name' => 'Garage Auto+ Services',
                'contactFirstName' => 'David',
                'contactLastName' => 'Mercier',
                'email' => 'd.mercier@autoplus-services.fr',
                'phone' => '04 91 23 45 67',
                'address' => '45 Route Nationale',
                'postalCode' => '13015',
                'city' => 'Marseille',
                'siret' => '88877766655544',
                'url' => 'https://autoplus-services.fr',
            ],
            [
                'name' => 'Fleuriste Les Roses Blanches',
                'contactFirstName' => 'Nathalie',
                'contactLastName' => 'Garnier',
                'email' => 'contact@roses-blanches.fr',
                'phone' => '05 56 78 90 12',
                'address' => '12 Place des Fleurs',
                'postalCode' => '33200',
                'city' => 'Bordeaux',
                'siret' => '77766655544433',
                'url' => null,
            ],
            [
                'name' => 'Association Aide & Solidarité',
                'contactFirstName' => 'Marc',
                'contactLastName' => 'Fontaine',
                'email' => 'contact@aide-solidarite.org',
                'phone' => '01 42 34 56 78',
                'address' => '28 Avenue de la Fraternité',
                'postalCode' => '75013',
                'city' => 'Paris',
                'siret' => '66655544433322',
                'url' => null,
            ],
        ];

        $clients = [];
        foreach ($clientsData as $data) {
            $client = new Client();
            $client->setName($data['name']);
            $client->setContactFirstName($data['contactFirstName']);
            $client->setContactLastName($data['contactLastName']);
            $client->setEmail($data['email']);
            $client->setPhone($data['phone']);
            $client->setAddress($data['address']);
            $client->setPostalCode($data['postalCode']);
            $client->setCity($data['city']);
            $client->setSiret($data['siret']);
            $client->setUrl($data['url']);

            $manager->persist($client);
            $clients[] = $client;
        }

        return $clients;
    }

    private function createPartners(ObjectManager $manager): array
    {
        $partnersData = [
            [
                'name' => 'Studio Créatif Design',
                'url' => 'https://studiocrea-design.fr',
                'email' => 'contact@studiocrea-design.fr',
                'phone' => '01 98 76 54 32',
                'domains' => ['Design graphique', 'UX/UI Design', 'Identité visuelle'],
            ],
            [
                'name' => 'Copywriting Pro',
                'url' => 'https://copywritingpro.fr',
                'email' => 'hello@copywritingpro.fr',
                'phone' => '02 87 65 43 21',
                'domains' => ['Rédaction web', 'Content marketing', 'SEO copywriting'],
            ],
            [
                'name' => 'PhotoArt Studio',
                'url' => 'https://photoart-studio.com',
                'email' => 'contact@photoart-studio.com',
                'phone' => '03 76 54 32 10',
                'domains' => ['Photographie professionnelle', 'Retouche photo', 'Shooting produits'],
            ],
        ];

        $partners = [];
        foreach ($partnersData as $data) {
            $partner = new Partner();
            $partner->setName($data['name']);
            $partner->setUrl($data['url']);
            $partner->setEmail($data['email']);
            $partner->setPhone($data['phone']);
            $partner->setDomains($data['domains']);
            $partner->setIsActive(true);

            $manager->persist($partner);
            $partners[] = $partner;
        }

        return $partners;
    }

    private function createProjects(ObjectManager $manager, array $clients, array $partners): array
    {
        $projectsData = [
            [
                'title' => 'E-commerce Bio',
                'slug' => 'ecommerce-bio',
                'category' => 'web_development',
                'shortDescription' => 'Boutique en ligne de produits bio avec paiement sécurisé',
                'fullDescription' => '<p>Développement complet d\'une boutique en ligne spécialisée dans les produits biologiques. Le site permet aux clients de parcourir un catalogue de produits, de passer commande et de payer en ligne en toute sécurité.</p>',
                'context' => '<p>BioBoutique souhaitait digitaliser son activité pour toucher une clientèle plus large et proposer la vente en ligne de ses produits bio.</p>',
                'solutions' => '<p>Mise en place d\'un site e-commerce avec Symfony, intégration de Stripe pour les paiements, système de gestion des stocks et interface d\'administration.</p>',
                'results' => '<p>+150% de chiffre d\'affaires en 6 mois, interface intuitive appréciée des clients.</p>',
                'technologies' => ['Symfony', 'MySQL', 'Stripe', 'Bootstrap'],
                'projectUrl' => 'https://bioboutique.com',
                'completionYear' => 2024,
                'isPublished' => true,
                'featured' => true,
                'client' => $clients[1], // BioBoutique
                'partner' => $partners[0], // Design
                'partnerDomains' => ['Design graphique', 'UX/UI Design'],
            ],
            [
                'title' => 'Site Vitrine Architecture',
                'slug' => 'site-vitrine-architecture',
                'category' => 'web_design',
                'shortDescription' => 'Site vitrine élégant pour cabinet d\'architecture avec portfolio projets',
                'fullDescription' => '<p>Création d\'un site vitrine moderne et élégant pour présenter les réalisations du cabinet d\'architecture Architect Studio.</p>',
                'context' => '<p>Le cabinet souhaitait un site web à son image : épuré, moderne et mettant en valeur ses réalisations architecturales.</p>',
                'solutions' => '<p>Design sur-mesure, galerie photos interactive, formulaire de contact, optimisation SEO.</p>',
                'results' => '<p>+200% de demandes de devis en ligne, excellent retour des clients sur le design.</p>',
                'technologies' => ['Symfony', 'Twig', 'JavaScript', 'CSS3'],
                'projectUrl' => 'https://architectstudio.fr',
                'completionYear' => 2023,
                'isPublished' => true,
                'client' => $clients[2], // Architect Studio
                'partner' => $partners[2], // Photo
                'partnerDomains' => ['Photographie professionnelle', 'Retouche photo'],
            ],
            [
                'title' => 'Application de Réservation Restaurant',
                'slug' => 'app-reservation-restaurant',
                'category' => 'web_application',
                'shortDescription' => 'Système de réservation en ligne et gestion des tables',
                'fullDescription' => '<p>Développement d\'une application web permettant aux clients de réserver une table en ligne et au restaurant de gérer ses réservations efficacement.</p>',
                'context' => '<p>Le restaurant Le Gourmet recevait trop d\'appels téléphoniques pour les réservations et souhaitait automatiser ce processus.</p>',
                'solutions' => '<p>Application avec calendrier de réservation, gestion des disponibilités, notifications par email, tableau de bord restaurateur.</p>',
                'results' => '<p>-70% d\'appels téléphoniques, meilleure gestion du planning, satisfaction client améliorée.</p>',
                'technologies' => ['Symfony', 'API Platform', 'Vue.js', 'MySQL'],
                'projectUrl' => null,
                'completionYear' => 2024,
                'isPublished' => true,
                'client' => $clients[3], // Restaurant Le Gourmet
                'partner' => null,
                'partnerDomains' => [],
            ],
            [
                'title' => 'Plateforme de Cours en Ligne',
                'slug' => 'plateforme-cours-fitness',
                'category' => 'web_application',
                'shortDescription' => 'Plateforme de streaming de cours de fitness avec abonnements',
                'fullDescription' => '<p>Création d\'une plateforme complète permettant aux membres de Fitness Plus de suivre des cours en ligne, avec système d\'abonnement et suivi de progression.</p>',
                'context' => '<p>Pendant le confinement, Fitness Plus a dû fermer sa salle et souhaitait continuer à proposer ses cours à distance.</p>',
                'solutions' => '<p>Plateforme de streaming vidéo, système d\'abonnement avec Stripe, espace membre personnalisé, suivi des séances.</p>',
                'results' => '<p>500+ abonnés en 3 mois, maintien de l\'activité pendant la fermeture, nouvelle source de revenus pérenne.</p>',
                'technologies' => ['Symfony', 'API Platform', 'React', 'Stripe', 'AWS S3'],
                'projectUrl' => 'https://fitnessplus.com',
                'completionYear' => 2023,
                'isPublished' => true,
                'featured' => true,
                'client' => $clients[4], // Fitness Plus
                'partner' => $partners[1], // Copywriting
                'partnerDomains' => ['Rédaction web', 'Content marketing'],
            ],
            [
                'title' => 'Portail Immobilier',
                'slug' => 'portail-immobilier-prestige',
                'category' => 'web_development',
                'shortDescription' => 'Site d\'annonces immobilières avec recherche avancée',
                'fullDescription' => '<p>Développement d\'un portail immobilier complet avec système de recherche avancée, visite virtuelle et estimation en ligne.</p>',
                'context' => '<p>L\'agence Prestige Immobilière voulait se démarquer avec un site moderne offrant une expérience utilisateur optimale pour la recherche de biens.</p>',
                'solutions' => '<p>Moteur de recherche multi-critères, intégration de visites virtuelles 360°, système de favori, outil d\'estimation, espace client.</p>',
                'results' => '<p>+180% de leads qualifiés, réduction de 40% du temps de recherche client, augmentation des ventes.</p>',
                'technologies' => ['Symfony', 'Elasticsearch', 'Vue.js', 'MySQL', 'Google Maps API'],
                'projectUrl' => 'https://prestige-immo.fr',
                'completionYear' => 2024,
                'isPublished' => true,
                'client' => $clients[5], // Prestige Immo
                'partner' => $partners[0], // Design
                'partnerDomains' => ['UX/UI Design', 'Identité visuelle'],
            ],
            // PROJET RÉEL - Alré Web (le dernier)
            [
                'title' => 'Alré Web - Portfolio & Gestion d\'Activité',
                'slug' => 'alre-web-portfolio',
                'category' => 'web_application',
                'shortDescription' => 'Site portfolio et plateforme de gestion d\'activité pour développeur freelance',
                'fullDescription' => '<p>Développement complet d\'une plateforme combinant un portfolio professionnel et des outils de gestion d\'activité pour auto-entrepreneur. Le site présente mes compétences, projets et services tout en intégrant une interface d\'administration complète pour gérer devis, factures et clients.</p><p>L\'objectif était de créer un outil tout-en-un qui me permette à la fois de présenter mon travail aux prospects et de gérer efficacement mon activité au quotidien.</p>',
                'context' => '<p>En tant que développeur web freelance, j\'avais besoin d\'un site professionnel pour présenter mes services et mes réalisations, mais aussi d\'outils pour gérer mon activité : génération de devis et factures, suivi clients, etc.</p><p>Plutôt que d\'utiliser plusieurs outils séparés (site vitrine + logiciel de facturation), j\'ai décidé de développer ma propre solution intégrée, adaptée spécifiquement à mes besoins.</p>',
                'solutions' => '<ul><li><strong>Portfolio moderne</strong> : présentation des projets avec images, technologies utilisées, partenaires, témoignages clients</li><li><strong>Gestion commerciale</strong> : création et suivi de devis, conversion en factures, numérotation automatique</li><li><strong>Gestion clients</strong> : base de données clients avec historique des projets et documents</li><li><strong>Interface d\'administration</strong> : tableau de bord avec EasyAdmin pour gérer facilement tous les contenus</li><li><strong>Monitoring</strong> : système d\'alertes email en cas d\'erreur en production</li><li><strong>SEO</strong> : sitemap dynamique, balises meta optimisées, pages légales</li></ul>',
                'results' => '<ul><li>Site professionnel et moderne reflétant mon expertise</li><li>Gain de temps quotidien grâce aux outils de gestion intégrés</li><li>Meilleure organisation de mon activité</li><li>Facilité de mise à jour du portfolio et ajout de nouveaux projets</li><li>Solution sur-mesure évolutive selon mes besoins futurs</li></ul>',
                'technologies' => ['Symfony 7', 'Doctrine ORM', 'EasyAdmin 4', 'MySQL', 'Twig', 'JavaScript', 'CSS3'],
                'projectUrl' => 'https://alre-web.bzh',
                'completionYear' => 2025,
                'isPublished' => true,
                'featured' => true,
                'client' => null,
                'partner' => null,
                'partnerDomains' => [],
            ],
        ];

        $projects = [];
        foreach ($projectsData as $data) {
            $project = new Project();
            $project->setTitle($data['title']);
            $project->setSlug($data['slug']);
            $project->setCategory($data['category']);
            $project->setShortDescription($data['shortDescription']);
            $project->setFullDescription($data['fullDescription']);
            $project->setContext($data['context']);
            $project->setSolutions($data['solutions']);
            $project->setResults($data['results']);
            $project->setTechnologies($data['technologies']);
            $project->setProjectUrl($data['projectUrl']);
            $project->setCompletionYear($data['completionYear']);
            $project->setIsPublished($data['isPublished']);
            $project->setFeatured($data['featured'] ?? false);
            $project->setClient($data['client']);

            $manager->persist($project);

            // Add project partner if exists
            if ($data['partner']) {
                $projectPartner = new ProjectPartner();
                $projectPartner->setProject($project);
                $projectPartner->setPartner($data['partner']);
                $projectPartner->setSelectedDomains($data['partnerDomains']);
                $manager->persist($projectPartner);
            }

            $projects[] = $project;
        }

        return $projects;
    }

    private function createTestimonials(ObjectManager $manager, array $clients): void
    {
        $testimonialsData = [
            [
                'clientName' => 'Sophie Martin',
                'clientCompany' => 'TechStart SAS',
                'content' => 'Travailler avec Fabrice a été un véritable plaisir. Son expertise technique et sa compréhension de nos besoins nous ont permis de lancer notre plateforme dans les délais. Je recommande vivement ses services !',
                'rating' => 5,
                'projectType' => 'Plateforme web',
                'isPublished' => true,
            ],
            [
                'clientName' => 'Marie Dupont',
                'clientCompany' => 'BioBoutique',
                'content' => 'Le site e-commerce développé par Fabrice a transformé notre activité. L\'interface est intuitive, le back-office facile à utiliser, et nos ventes ont explosé. Un grand merci pour ce travail de qualité !',
                'rating' => 5,
                'projectType' => 'E-commerce',
                'isPublished' => true,
            ],
            [
                'clientName' => 'Julie Bernard',
                'clientCompany' => 'Fitness Plus',
                'content' => 'La plateforme de cours en ligne nous a permis de continuer notre activité pendant la crise sanitaire. Fabrice a su nous proposer une solution adaptée et évolutive. Très satisfaite de sa réactivité et de son professionnalisme.',
                'rating' => 5,
                'projectType' => 'Application web',
                'isPublished' => true,
            ],
        ];

        foreach ($testimonialsData as $data) {
            $testimonial = new Testimonial();
            $testimonial->setClientName($data['clientName']);
            $testimonial->setClientCompany($data['clientCompany']);
            $testimonial->setContent($data['content']);
            $testimonial->setRating($data['rating']);
            $testimonial->setProjectType($data['projectType']);
            $testimonial->setIsPublished($data['isPublished']);

            $manager->persist($testimonial);
        }
    }

    private function getStats(ObjectManager $manager): array
    {
        $devisCount = $manager->getRepository(Devis::class)->count([]);
        $facturesCount = $manager->getRepository(Facture::class)->count([]);

        return [
            'devis' => $devisCount,
            'factures' => $facturesCount,
        ];
    }

    private function createDevisAndFactures(ObjectManager $manager, User $user, array $clients): void
    {
        // ============================================
        // ANNÉE 2026 - Archives
        // ============================================

        // Jan 2023 : Site vitrine TechStart - Accepté et facturé payé
        $devis2023_01 = $this->createDevis(
            $manager,
            $user,
            $clients[0], // TechStart
            'DEV-2024-001',
            'Site Vitrine Corporate',
            'Création site vitrine 5 pages',
            Devis::STATUS_ACCEPTE,
            [
                ['description' => 'Maquette et design', 'quantity' => 1, 'unitPrice' => 800],
                ['description' => 'Développement 5 pages', 'quantity' => 5, 'unitPrice' => 300],
                ['description' => 'Formulaire de contact', 'quantity' => 1, 'unitPrice' => 200],
                ['description' => 'Formation client', 'quantity' => 2, 'unitPrice' => 150],
            ],
            new \DateTimeImmutable('2024-01-10')
        );
        $devis2023_01->setDateEnvoi(new \DateTimeImmutable('2024-01-12'));
        $devis2023_01->setDateValidite(new \DateTimeImmutable('2024-02-11')); // 30 jours
        $devis2023_01->setDateReponse(new \DateTimeImmutable('2024-01-20'));

        $this->createFacture(
            $manager,
            $user,
            $clients[0],
            $devis2023_01,
            'FACT-2024-001',
            'paye',
            new \DateTimeImmutable('2024-02-15'),
            new \DateTimeImmutable('2024-03-10')
        );

        // Mars 2023 : Refonte Restaurant - Refusé (trop cher)
        $devis2023_02 = $this->createDevis(
            $manager,
            $user,
            $clients[3], // Restaurant
            'DEV-2024-002',
            'Refonte complète site restaurant',
            'Refonte totale avec réservation en ligne',
            Devis::STATUS_REFUSE,
            [
                ['description' => 'Audit et analyse', 'quantity' => 1, 'unitPrice' => 1200],
                ['description' => 'Refonte design', 'quantity' => 1, 'unitPrice' => 2500],
                ['description' => 'Développement', 'quantity' => 30, 'unitPrice' => 400],
            ],
            new \DateTimeImmutable('2024-03-05')
        );
        $devis2023_02->setDateEnvoi(new \DateTimeImmutable('2024-03-08'));
        $devis2023_02->setDateValidite(new \DateTimeImmutable('2024-04-07')); // 30 jours
        $devis2023_02->setDateReponse(new \DateTimeImmutable('2024-03-22'));

        // Mai 2023 : Plateforme Fitness - Accepté et facturé payé
        $devis2023_03 = $this->createDevis(
            $manager,
            $user,
            $clients[4], // Fitness Plus
            'DEV-2024-003',
            'Plateforme Cours en Ligne',
            'Développement plateforme streaming + abonnements',
            Devis::STATUS_ACCEPTE,
            [
                ['description' => 'Architecture et conception', 'quantity' => 1, 'unitPrice' => 1500],
                ['description' => 'Développement API + Front-end React', 'quantity' => 30, 'unitPrice' => 400],
                ['description' => 'Intégration vidéo et Stripe', 'quantity' => 1, 'unitPrice' => 1200],
                ['description' => 'Tests et optimisations', 'quantity' => 1, 'unitPrice' => 800],
            ],
            new \DateTimeImmutable('2024-05-02')
        );
        $devis2023_03->setDateEnvoi(new \DateTimeImmutable('2024-05-05'));
        $devis2023_03->setDateValidite(new \DateTimeImmutable('2024-06-04')); // 30 jours
        $devis2023_03->setDateReponse(new \DateTimeImmutable('2024-05-15'));

        $this->createFacture(
            $manager,
            $user,
            $clients[4],
            $devis2023_03,
            'FACT-2024-002',
            'paye',
            new \DateTimeImmutable('2024-08-20'),
            new \DateTimeImmutable('2024-09-15')
        );

        // Juillet 2024 : Application Mobile Fitness - Refusé (trop cher)
        $devis2024_04 = $this->createDevis(
            $manager,
            $user,
            $clients[4], // Fitness Plus
            'DEV-2024-004',
            'Application Mobile Fitness',
            'Développement application mobile iOS/Android',
            Devis::STATUS_REFUSE,
            [
                ['description' => 'Conception UX/UI mobile', 'quantity' => 1, 'unitPrice' => 2500],
                ['description' => 'Développement app iOS', 'quantity' => 40, 'unitPrice' => 500],
                ['description' => 'Développement app Android', 'quantity' => 40, 'unitPrice' => 500],
                ['description' => 'API Backend', 'quantity' => 15, 'unitPrice' => 400],
                ['description' => 'Tests et déploiement', 'quantity' => 1, 'unitPrice' => 1500],
            ],
            new \DateTimeImmutable('2024-07-03')
        );
        $devis2024_04->setDateEnvoi(new \DateTimeImmutable('2024-07-06'));
        $devis2024_04->setDateValidite(new \DateTimeImmutable('2024-08-05')); // 30 jours
        $devis2024_04->setDateReponse(new \DateTimeImmutable('2024-07-18'));

        // Octobre 2023 : Site Architect Studio - Accepté et facturé payé
        $devis2023_05 = $this->createDevis(
            $manager,
            $user,
            $clients[2], // Architect Studio
            'DEV-2024-005',
            'Site Vitrine Architecture',
            'Création d\'un site vitrine avec portfolio',
            Devis::STATUS_ACCEPTE,
            [
                ['description' => 'Conception graphique et UX', 'quantity' => 1, 'unitPrice' => 1200],
                ['description' => 'Développement du site', 'quantity' => 15, 'unitPrice' => 400],
                ['description' => 'Galerie photos interactive', 'quantity' => 1, 'unitPrice' => 800],
                ['description' => 'Formation client', 'quantity' => 1, 'unitPrice' => 400],
            ],
            new \DateTimeImmutable('2024-09-25')
        );
        $devis2023_05->setDateEnvoi(new \DateTimeImmutable('2024-09-28'));
        $devis2023_05->setDateValidite(new \DateTimeImmutable('2024-10-28')); // 30 jours
        $devis2023_05->setDateReponse(new \DateTimeImmutable('2024-10-05'));

        $this->createFacture(
            $manager,
            $user,
            $clients[2],
            $devis2023_05,
            'FACT-2024-003',
            'paye',
            new \DateTimeImmutable('2024-12-10'),
            new \DateTimeImmutable('2025-01-08')
        );

        // ============================================
        // ANNÉE 2026
        // ============================================

        // Janvier 2024 : E-commerce BioBoutique - Accepté et facturé payé
        $devis2024_01 = $this->createDevis(
            $manager,
            $user,
            $clients[1], // BioBoutique
            'DEV-2025-001',
            'Site E-commerce Bio',
            'Développement complet d\'une boutique en ligne',
            Devis::STATUS_ACCEPTE,
            [
                ['description' => 'Analyse et conception', 'quantity' => 1, 'unitPrice' => 800],
                ['description' => 'Développement front-end et back-end', 'quantity' => 25, 'unitPrice' => 400],
                ['description' => 'Intégration Stripe', 'quantity' => 1, 'unitPrice' => 600],
                ['description' => 'Tests et mise en ligne', 'quantity' => 1, 'unitPrice' => 500],
            ],
            new \DateTimeImmutable('2025-01-08')
        );
        $devis2024_01->setDateEnvoi(new \DateTimeImmutable('2025-01-10'));
        $devis2024_01->setDateValidite(new \DateTimeImmutable('2025-02-09')); // 30 jours
        $devis2024_01->setDateReponse(new \DateTimeImmutable('2025-01-18'));

        $this->createFacture(
            $manager,
            $user,
            $clients[1],
            $devis2024_01,
            'FACT-2025-001',
            'paye',
            new \DateTimeImmutable('2025-04-22'),
            new \DateTimeImmutable('2025-05-15')
        );

        // Février 2024 : Menu Digital Restaurant - Accepté et facturé payé
        $devis2024_02 = $this->createDevis(
            $manager,
            $user,
            $clients[3], // Restaurant
            'DEV-2025-002',
            'Site Menu Digital',
            'Création menu digital QR code',
            Devis::STATUS_ACCEPTE,
            [
                ['description' => 'Design menu digital', 'quantity' => 1, 'unitPrice' => 500],
                ['description' => 'Développement responsive', 'quantity' => 1, 'unitPrice' => 800],
                ['description' => 'QR codes personnalisés', 'quantity' => 1, 'unitPrice' => 200],
            ],
            new \DateTimeImmutable('2025-02-12')
        );
        $devis2024_02->setDateEnvoi(new \DateTimeImmutable('2025-02-14'));
        $devis2024_02->setDateValidite(new \DateTimeImmutable('2025-03-15')); // 30 jours
        $devis2024_02->setDateReponse(new \DateTimeImmutable('2025-02-20'));

        $this->createFacture(
            $manager,
            $user,
            $clients[3],
            $devis2024_02,
            'FACT-2025-002',
            'paye',
            new \DateTimeImmutable('2025-03-05'),
            new \DateTimeImmutable('2025-03-28')
        );

        // Mars 2024 : Portail Immobilier - Accepté et facturé en attente paiement
        $devis2024_03 = $this->createDevis(
            $manager,
            $user,
            $clients[5], // Prestige Immo
            'DEV-2025-003',
            'Portail Immobilier',
            'Site d\'annonces avec recherche avancée',
            Devis::STATUS_ACCEPTE,
            [
                ['description' => 'Analyse fonctionnelle et UX', 'quantity' => 1, 'unitPrice' => 1000],
                ['description' => 'Développement moteur de recherche', 'quantity' => 20, 'unitPrice' => 400],
                ['description' => 'Intégration Elasticsearch et Maps', 'quantity' => 1, 'unitPrice' => 1500],
                ['description' => 'Tests et déploiement', 'quantity' => 1, 'unitPrice' => 600],
            ],
            new \DateTimeImmutable('2025-02-26')
        );
        $devis2024_03->setDateEnvoi(new \DateTimeImmutable('2025-02-28'));
        $devis2024_03->setDateValidite(new \DateTimeImmutable('2025-03-29')); // 30 jours
        $devis2024_03->setDateReponse(new \DateTimeImmutable('2025-03-08'));

        $this->createFacture(
            $manager,
            $user,
            $clients[5],
            $devis2024_03,
            'FACT-2025-003',
            'envoye',
            new \DateTimeImmutable('2025-06-12')
        );

        // Avril 2025 : Site Boutique Mode - Accepté et facturé payé
        $devis2025_04 = $this->createDevis(
            $manager,
            $user,
            $clients[6], // Boutique Élégance
            'DEV-2025-004',
            'Site Vitrine Boutique Mode',
            'Site élégant avec catalogue produits',
            Devis::STATUS_ACCEPTE,
            [
                ['description' => 'Maquette et design sur mesure', 'quantity' => 1, 'unitPrice' => 1500],
                ['description' => 'Développement site + catalogue', 'quantity' => 12, 'unitPrice' => 400],
                ['description' => 'Optimisation images et SEO', 'quantity' => 1, 'unitPrice' => 600],
            ],
            new \DateTimeImmutable('2025-03-18')
        );
        $devis2025_04->setDateEnvoi(new \DateTimeImmutable('2025-03-20'));
        $devis2025_04->setDateValidite(new \DateTimeImmutable('2025-04-19')); // 30 jours
        $devis2025_04->setDateReponse(new \DateTimeImmutable('2025-03-28'));

        $this->createFacture(
            $manager,
            $user,
            $clients[6],
            $devis2025_04,
            'FACT-2025-004',
            'paye',
            new \DateTimeImmutable('2025-05-25'),
            new \DateTimeImmutable('2025-06-12')
        );

        // Juin 2024 : Site Cabinet Avocat - Accepté et facturé payé
        $devis2024_05 = $this->createDevis(
            $manager,
            $user,
            $clients[7], // Cabinet Juridis
            'DEV-2025-005',
            'Site Cabinet d\'Avocat',
            'Site professionnel avec présentation des services',
            Devis::STATUS_ACCEPTE,
            [
                ['description' => 'Design sobre et professionnel', 'quantity' => 1, 'unitPrice' => 900],
                ['description' => 'Développement 8 pages', 'quantity' => 8, 'unitPrice' => 350],
                ['description' => 'Formulaire prise de contact', 'quantity' => 1, 'unitPrice' => 400],
                ['description' => 'Mentions légales conformes', 'quantity' => 1, 'unitPrice' => 200],
            ],
            new \DateTimeImmutable('2025-05-27')
        );
        $devis2024_05->setDateEnvoi(new \DateTimeImmutable('2025-05-29'));
        $devis2024_05->setDateValidite(new \DateTimeImmutable('2025-06-28')); // 30 jours
        $devis2024_05->setDateReponse(new \DateTimeImmutable('2025-06-05'));

        $this->createFacture(
            $manager,
            $user,
            $clients[7],
            $devis2024_05,
            'FACT-2025-005',
            'paye',
            new \DateTimeImmutable('2025-07-20'),
            new \DateTimeImmutable('2025-08-10')
        );

        // Août 2024 : Site Garage - Accepté et facturé payé
        $devis2024_06 = $this->createDevis(
            $manager,
            $user,
            $clients[9], // Garage Auto+
            'DEV-2025-006',
            'Site Vitrine Garage Auto',
            'Site avec prise de RDV en ligne',
            Devis::STATUS_ACCEPTE,
            [
                ['description' => 'Design et maquette', 'quantity' => 1, 'unitPrice' => 700],
                ['description' => 'Développement site vitrine', 'quantity' => 10, 'unitPrice' => 350],
                ['description' => 'Module prise de RDV', 'quantity' => 1, 'unitPrice' => 1200],
            ],
            new \DateTimeImmutable('2025-07-15')
        );
        $devis2024_06->setDateEnvoi(new \DateTimeImmutable('2025-07-17'));
        $devis2024_06->setDateValidite(new \DateTimeImmutable('2025-08-16')); // 30 jours
        $devis2024_06->setDateReponse(new \DateTimeImmutable('2025-07-25'));

        $this->createFacture(
            $manager,
            $user,
            $clients[9],
            $devis2024_06,
            'FACT-2025-006',
            'paye',
            new \DateTimeImmutable('2025-09-10'),
            new \DateTimeImmutable('2025-10-02')
        );

        // Septembre 2024 : Corrections BioBoutique - Accepté et facturé payé
        $devis2024_07 = $this->createDevis(
            $manager,
            $user,
            $clients[1], // BioBoutique
            'DEV-2025-007',
            'Corrections et améliorations',
            'Corrections diverses et optimisations',
            Devis::STATUS_ACCEPTE,
            [
                ['description' => 'Correction formulaire contact', 'quantity' => 1, 'unitPrice' => 200],
                ['description' => 'Mise à jour images produits', 'quantity' => 1, 'unitPrice' => 150],
                ['description' => 'Optimisation vitesse', 'quantity' => 1, 'unitPrice' => 300],
                ['description' => 'Ajout filtres produits', 'quantity' => 1, 'unitPrice' => 500],
            ],
            new \DateTimeImmutable('2025-09-02')
        );
        $devis2024_07->setDateEnvoi(new \DateTimeImmutable('2025-09-04'));
        $devis2024_07->setDateValidite(new \DateTimeImmutable('2025-10-04')); // 30 jours
        $devis2024_07->setDateReponse(new \DateTimeImmutable('2025-09-06'));

        $this->createFacture(
            $manager,
            $user,
            $clients[1],
            $devis2024_07,
            'FACT-2025-007',
            'paye',
            new \DateTimeImmutable('2025-09-18'),
            new \DateTimeImmutable('2025-10-05')
        );

        // Septembre 2025 : Site Fleuriste - Refusé (préfère solution clé en main)
        $devis2025_08 = $this->createDevis(
            $manager,
            $user,
            $clients[10], // Fleuriste
            'DEV-2025-008',
            'Site Vitrine Fleuriste',
            'Site avec galerie et commande en ligne',
            Devis::STATUS_REFUSE,
            [
                ['description' => 'Conception et maquette', 'quantity' => 1, 'unitPrice' => 800],
                ['description' => 'Développement site + galerie', 'quantity' => 8, 'unitPrice' => 350],
                ['description' => 'Formulaire commande personnalisée', 'quantity' => 1, 'unitPrice' => 600],
            ],
            new \DateTimeImmutable('2025-09-18')
        );
        $devis2025_08->setDateEnvoi(new \DateTimeImmutable('2025-09-20'));
        $devis2025_08->setDateValidite(new \DateTimeImmutable('2025-10-20')); // 30 jours
        $devis2025_08->setDateReponse(new \DateTimeImmutable('2025-10-02'));

        // Septembre 2024 : Site École de Musique - Accepté et facturé RELANCE
        $devis2024_09 = $this->createDevis(
            $manager,
            $user,
            $clients[8], // École Harmony
            'DEV-2025-009',
            'Site Vitrine École de Musique',
            'Site avec présentation des cours et inscriptions',
            Devis::STATUS_ACCEPTE,
            [
                ['description' => 'Design musical et coloré', 'quantity' => 1, 'unitPrice' => 700],
                ['description' => 'Développement site 6 pages', 'quantity' => 6, 'unitPrice' => 350],
                ['description' => 'Formulaire inscription en ligne', 'quantity' => 1, 'unitPrice' => 600],
                ['description' => 'Présentation professeurs et cours', 'quantity' => 1, 'unitPrice' => 400],
            ],
            new \DateTimeImmutable('2025-09-16')
        );
        $devis2024_09->setDateEnvoi(new \DateTimeImmutable('2025-09-18'));
        $devis2024_09->setDateValidite(new \DateTimeImmutable('2025-10-18')); // 30 jours
        $devis2024_09->setDateReponse(new \DateTimeImmutable('2025-09-25'));

        // Facture envoyée avec échéance dépassée → devrait passer auto à "a_relancer"
        $this->createFacture(
            $manager,
            $user,
            $clients[8],
            $devis2024_09,
            'FACT-2025-008',
            'envoye',
            new \DateTimeImmutable('2025-10-05')
        );

        // Octobre 2024 : Maintenance Cabinet Avocat - Accepté et facturé EN RETARD
        $devis2024_10 = $this->createDevis(
            $manager,
            $user,
            $clients[7], // Cabinet Juridis
            'DEV-2025-010',
            'Maintenance Annuelle Cabinet',
            'Contrat de maintenance pour 12 mois',
            Devis::STATUS_ACCEPTE,
            [
                ['description' => 'Maintenance mensuelle (12 mois)', 'quantity' => 12, 'unitPrice' => 120],
                ['description' => 'Mises à jour de sécurité', 'quantity' => 1, 'unitPrice' => 400],
                ['description' => 'Support technique prioritaire', 'quantity' => 1, 'unitPrice' => 300],
            ],
            new \DateTimeImmutable('2025-10-01')
        );
        $devis2024_10->setDateEnvoi(new \DateTimeImmutable('2025-10-03'));
        $devis2024_10->setDateValidite(new \DateTimeImmutable('2025-11-02')); // 30 jours
        $devis2024_10->setDateReponse(new \DateTimeImmutable('2025-10-08'));

        // Facture envoyée avec échéance dépassée → devrait passer auto à "a_relancer"
        $this->createFacture(
            $manager,
            $user,
            $clients[7],
            $devis2024_10,
            'FACT-2025-009',
            'envoye',
            new \DateTimeImmutable('2025-10-15') // Échéance 14/11 - dépassée il y a 3 jours
        );

        // Novembre 2024 : Évolutions Boutique Mode - Accepté et facturé ENVOYE (récent)
        $devis2024_11 = $this->createDevis(
            $manager,
            $user,
            $clients[6], // Boutique Élégance
            'DEV-2025-011',
            'Évolutions Site Boutique',
            'Ajout fonctionnalités et améliorations',
            Devis::STATUS_ACCEPTE,
            [
                ['description' => 'Module newsletter', 'quantity' => 1, 'unitPrice' => 600],
                ['description' => 'Filtres avancés produits', 'quantity' => 1, 'unitPrice' => 800],
                ['description' => 'Intégration Instagram', 'quantity' => 1, 'unitPrice' => 400],
            ],
            new \DateTimeImmutable('2025-10-28')
        );
        $devis2024_11->setDateEnvoi(new \DateTimeImmutable('2025-10-30'));
        $devis2024_11->setDateValidite(new \DateTimeImmutable('2025-11-29')); // 30 jours
        $devis2024_11->setDateReponse(new \DateTimeImmutable('2025-11-05'));

        $this->createFacture(
            $manager,
            $user,
            $clients[6],
            $devis2024_11,
            'FACT-2025-010',
            'envoye',
            new \DateTimeImmutable('2025-11-06')
        );

        // Octobre 2025 : Marketplace TechStart - Annulé (projet abandonné)
        $devis2025_12 = $this->createDevis(
            $manager,
            $user,
            $clients[0], // TechStart
            'DEV-2025-012',
            'Marketplace Multi-Vendeurs',
            'Plateforme marketplace avec paiement et gestion vendeurs',
            Devis::STATUS_ANNULE,
            [
                ['description' => 'Architecture et conception', 'quantity' => 1, 'unitPrice' => 3000],
                ['description' => 'Développement marketplace', 'quantity' => 60, 'unitPrice' => 450],
                ['description' => 'Système paiement multi-vendeurs', 'quantity' => 1, 'unitPrice' => 2500],
                ['description' => 'Interface gestion vendeurs', 'quantity' => 1, 'unitPrice' => 2000],
            ],
            new \DateTimeImmutable('2025-10-22')
        );
        $devis2025_12->setDateEnvoi(new \DateTimeImmutable('2025-10-25'));
        $devis2025_12->setDateValidite(new \DateTimeImmutable('2025-11-24')); // 30 jours
        $devis2025_12->setDateReponse(new \DateTimeImmutable('2025-11-10'));

        // ============================================
        // EN COURS (Nov 2025)
        // ============================================

        // Devis envoyé avec validité dépassée → devrait passer auto à "a_relancer"
        $devis2025_01 = $this->createDevis(
            $manager,
            $user,
            $clients[11], // Association
            'DEV-2025-013',
            'Site Associatif',
            'Site pour association avec espace membres',
            Devis::STATUS_ENVOYE,
            [
                ['description' => 'Design et ergonomie', 'quantity' => 1, 'unitPrice' => 700],
                ['description' => 'Développement site + espace membre', 'quantity' => 15, 'unitPrice' => 350],
                ['description' => 'Système d\'adhésion en ligne', 'quantity' => 1, 'unitPrice' => 800],
            ],
            new \DateTimeImmutable('2025-10-05')
        );
        $devis2025_01->setDateEnvoi(new \DateTimeImmutable('2025-10-08'));
        $devis2025_01->setDateValidite(new \DateTimeImmutable('2025-11-07')); // 30 jours - dépassée il y a 10 jours

        // Envoyé récemment (il y a 1 semaine)
        $devis2025_02 = $this->createDevis(
            $manager,
            $user,
            $clients[0], // TechStart
            'DEV-2025-014',
            'Refonte Site Corporate',
            'Modernisation du site web institutionnel',
            Devis::STATUS_ENVOYE,
            [
                ['description' => 'Audit et recommandations', 'quantity' => 1, 'unitPrice' => 600],
                ['description' => 'Refonte design moderne', 'quantity' => 1, 'unitPrice' => 1200],
                ['description' => 'Développement responsive', 'quantity' => 18, 'unitPrice' => 400],
                ['description' => 'Migration contenu et SEO', 'quantity' => 1, 'unitPrice' => 800],
            ],
            new \DateTimeImmutable('2025-11-04')
        );
        $devis2025_02->setDateEnvoi(new \DateTimeImmutable('2025-11-06'));
        $devis2025_02->setDateValidite(new \DateTimeImmutable('2025-12-06')); // 30 jours

        // Brouillons en préparation
        $this->createDevis(
            $manager,
            $user,
            $clients[2], // Architect Studio
            null,
            'Application Mobile',
            'Étude de faisabilité application mobile',
            Devis::STATUS_BROUILLON,
            [
                ['description' => 'Audit et recommandations', 'quantity' => 1, 'unitPrice' => 1500],
                ['description' => 'Maquettes UI/UX', 'quantity' => 1, 'unitPrice' => 2000],
                ['description' => 'Prototype interactif', 'quantity' => 1, 'unitPrice' => 1200],
            ],
            new \DateTimeImmutable('2025-11-11')
        );

        $this->createDevis(
            $manager,
            $user,
            $clients[3], // Restaurant
            null,
            'Maintenance Annuelle',
            'Contrat de maintenance et évolutions',
            Devis::STATUS_BROUILLON,
            [
                ['description' => 'Maintenance mensuelle (12 mois)', 'quantity' => 12, 'unitPrice' => 150],
                ['description' => 'Module avis clients', 'quantity' => 1, 'unitPrice' => 800],
                ['description' => 'Optimisation performances', 'quantity' => 1, 'unitPrice' => 600],
            ],
            new \DateTimeImmutable('2025-11-13')
        );
    }

    private function createDevis(
        ObjectManager $manager,
        User $user,
        Client $client,
        ?string $number,
        string $title,
        string $description,
        string $status,
        array $items,
        ?\DateTimeImmutable $dateCreation = null
    ): Devis {
        $devis = new Devis();
        if ($number) {
            $devis->setNumber($number);
        }
        $devis->setTitle($title);
        $devis->setDescription($description);
        $devis->setClient($client);
        $devis->setStatus($status);
        $devis->setCreatedBy($user);
        $devis->setConditions('Devis valable 30 jours. Acompte de 30% à la commande. Paiement du solde à la livraison.');

        if ($dateCreation) {
            $devis->setDateCreation($dateCreation);
            $devis->setCreatedAt($dateCreation);
        }

        foreach ($items as $itemData) {
            $item = new DevisItem();
            $item->setDescription($itemData['description']);
            $item->setQuantity($itemData['quantity']);
            $item->setUnitPrice($itemData['unitPrice']);
            $item->setVatRate(20.00);

            $devis->addItem($item);
            $manager->persist($item);
        }

        $devis->calculateTotals();

        $manager->persist($devis);

        return $devis;
    }

    private function createFacture(
        ObjectManager $manager,
        User $user,
        Client $client,
        Devis $devis,
        string $number,
        string $status,
        ?\DateTimeImmutable $dateFacture = null,
        ?\DateTimeImmutable $datePaiement = null
    ): Facture {
        $facture = new Facture();
        $facture->setNumber($number);
        $facture->setTitle($devis->getTitle());
        $facture->setClient($client);
        $facture->setDevis($devis);
        $facture->setStatus($status);
        $facture->setCreatedBy($user);
        $facture->setConditions('TVA non applicable, article 293 B du CGI. Paiement à 30 jours.');

        if ($dateFacture) {
            $facture->setDateFacture($dateFacture);
            $facture->setDateEcheance((clone $dateFacture)->modify('+30 days'));
            $facture->setCreatedAt($dateFacture);
            $facture->setDateEnvoi($dateFacture); // Facture envoyée le jour de création
        }

        if ($status === 'paye' && $datePaiement) {
            $facture->setDatePaiement($datePaiement);
        }

        // Copy items from devis
        foreach ($devis->getItems() as $devisItem) {
            $factureItem = new FactureItem();
            $factureItem->setDescription($devisItem->getDescription());
            $factureItem->setQuantity($devisItem->getQuantity());
            $factureItem->setUnitPrice($devisItem->getUnitPrice());
            $factureItem->setVatRate($devisItem->getVatRate());

            $facture->addItem($factureItem);
            $manager->persist($factureItem);
        }

        $facture->calculateTotals();

        $manager->persist($facture);

        return $facture;
    }
}
