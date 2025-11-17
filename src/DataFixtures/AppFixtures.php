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

        // 3. Create Clients
        $clients = $this->createClients($manager);

        // 4. Create Partners
        $partners = $this->createPartners($manager);

        // 5. Create Projects (fictifs + réel)
        $projects = $this->createProjects($manager, $clients, $partners);

        // 6. Create Testimonials
        $this->createTestimonials($manager, $clients);

        // 7. Create Devis & Factures
        $this->createDevisAndFactures($manager, $user, $clients);

        $manager->flush();

        echo "\n✓ Fixtures loaded successfully!\n";
        echo "  - Company: 1\n";
        echo "  - Users: 1\n";
        echo "  - Clients: " . count($clients) . "\n";
        echo "  - Partners: " . count($partners) . "\n";
        echo "  - Projects: " . count($projects) . "\n";
        echo "  - Devis: 6\n";
        echo "  - Factures: 4\n";
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

    private function createDevisAndFactures(ObjectManager $manager, User $user, array $clients): void
    {
        // Devis 1 - Accepté et facturé (BioBoutique - E-commerce)
        $devis1 = $this->createDevis(
            $manager,
            $user,
            $clients[1],
            'DEV-2024-001',
            'Site E-commerce Bio',
            'Développement complet d\'une boutique en ligne',
            Devis::STATUS_ACCEPTE,
            [
                ['description' => 'Analyse et conception', 'quantity' => 1, 'unitPrice' => 800],
                ['description' => 'Développement front-end et back-end', 'quantity' => 25, 'unitPrice' => 400],
                ['description' => 'Intégration Stripe', 'quantity' => 1, 'unitPrice' => 600],
                ['description' => 'Tests et mise en ligne', 'quantity' => 1, 'unitPrice' => 500],
            ]
        );
        $devis1->setDateEnvoi(new \DateTimeImmutable('2024-01-15'));
        $devis1->setDateReponse(new \DateTimeImmutable('2024-01-20'));

        // Facture pour devis 1
        $this->createFacture(
            $manager,
            $user,
            $clients[1],
            $devis1,
            'FACT-2024-001',
            'paye'
        );

        // Devis 2 - Accepté et facturé (Architect Studio)
        $devis2 = $this->createDevis(
            $manager,
            $user,
            $clients[2],
            'DEV-2023-015',
            'Site Vitrine Architecture',
            'Création d\'un site vitrine avec portfolio',
            Devis::STATUS_ACCEPTE,
            [
                ['description' => 'Conception graphique et UX', 'quantity' => 1, 'unitPrice' => 1200],
                ['description' => 'Développement du site', 'quantity' => 15, 'unitPrice' => 400],
                ['description' => 'Galerie photos interactive', 'quantity' => 1, 'unitPrice' => 800],
                ['description' => 'Formation client', 'quantity' => 1, 'unitPrice' => 400],
            ]
        );
        $devis2->setDateEnvoi(new \DateTimeImmutable('2023-10-01'));
        $devis2->setDateReponse(new \DateTimeImmutable('2023-10-05'));

        $this->createFacture(
            $manager,
            $user,
            $clients[2],
            $devis2,
            'FACT-2023-005',
            'paye'
        );

        // Devis 3 - Accepté et facturé (Fitness Plus)
        $devis3 = $this->createDevis(
            $manager,
            $user,
            $clients[4],
            'DEV-2023-010',
            'Plateforme Cours en Ligne',
            'Développement plateforme streaming + abonnements',
            Devis::STATUS_ACCEPTE,
            [
                ['description' => 'Architecture et conception', 'quantity' => 1, 'unitPrice' => 1500],
                ['description' => 'Développement API + Front-end React', 'quantity' => 30, 'unitPrice' => 400],
                ['description' => 'Intégration vidéo et Stripe', 'quantity' => 1, 'unitPrice' => 1200],
                ['description' => 'Tests et optimisations', 'quantity' => 1, 'unitPrice' => 800],
            ]
        );
        $devis3->setDateEnvoi(new \DateTimeImmutable('2023-05-01'));
        $devis3->setDateReponse(new \DateTimeImmutable('2023-05-10'));

        $this->createFacture(
            $manager,
            $user,
            $clients[4],
            $devis3,
            'FACT-2023-002',
            'paye'
        );

        // Devis 4 - Accepté et facturé (Prestige Immo)
        $devis4 = $this->createDevis(
            $manager,
            $user,
            $clients[5],
            'DEV-2024-005',
            'Portail Immobilier',
            'Site d\'annonces avec recherche avancée',
            Devis::STATUS_ACCEPTE,
            [
                ['description' => 'Analyse fonctionnelle et UX', 'quantity' => 1, 'unitPrice' => 1000],
                ['description' => 'Développement moteur de recherche', 'quantity' => 20, 'unitPrice' => 400],
                ['description' => 'Intégration Elasticsearch et Maps', 'quantity' => 1, 'unitPrice' => 1500],
                ['description' => 'Tests et déploiement', 'quantity' => 1, 'unitPrice' => 600],
            ]
        );
        $devis4->setDateEnvoi(new \DateTimeImmutable('2024-03-01'));
        $devis4->setDateReponse(new \DateTimeImmutable('2024-03-08'));

        $this->createFacture(
            $manager,
            $user,
            $clients[5],
            $devis4,
            'FACT-2024-003',
            'envoye'
        );

        // Devis 5 - Envoyé (TechStart)
        $devis5 = $this->createDevis(
            $manager,
            $user,
            $clients[0],
            'DEV-2025-001',
            'Refonte Site Corporate',
            'Modernisation du site web institutionnel',
            Devis::STATUS_ENVOYE,
            [
                ['description' => 'Audit et recommandations', 'quantity' => 1, 'unitPrice' => 600],
                ['description' => 'Refonte design', 'quantity' => 1, 'unitPrice' => 1200],
                ['description' => 'Développement', 'quantity' => 18, 'unitPrice' => 400],
                ['description' => 'Migration contenu et SEO', 'quantity' => 1, 'unitPrice' => 800],
            ]
        );
        $devis5->setDateEnvoi(new \DateTimeImmutable('2025-01-10'));
        $devis5->setDateValidite(new \DateTimeImmutable('2025-02-10'));

        // Devis 6 - Brouillon (Restaurant)
        $devis6 = $this->createDevis(
            $manager,
            $user,
            $clients[3],
            null, // Pas encore de numéro en brouillon
            'Maintenance et Évolutions',
            'Contrat de maintenance et ajout de fonctionnalités',
            Devis::STATUS_BROUILLON,
            [
                ['description' => 'Maintenance mensuelle', 'quantity' => 12, 'unitPrice' => 150],
                ['description' => 'Ajout module avis clients', 'quantity' => 1, 'unitPrice' => 800],
                ['description' => 'Optimisation performances', 'quantity' => 1, 'unitPrice' => 600],
            ]
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
        array $items
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

        $totalHt = 0;
        foreach ($items as $itemData) {
            $item = new DevisItem();
            $item->setDescription($itemData['description']);
            $item->setQuantity($itemData['quantity']);
            $item->setUnitPrice($itemData['unitPrice']);
            $item->setVatRate(20.00);
            $item->setDevis($devis);

            $manager->persist($item);

            $totalHt += $itemData['quantity'] * $itemData['unitPrice'];
        }

        $devis->setTotalHt((string) $totalHt);
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
        string $status
    ): Facture {
        $facture = new Facture();
        $facture->setNumber($number);
        $facture->setTitle($devis->getTitle());
        $facture->setClient($client);
        $facture->setDevis($devis);
        $facture->setStatus($status);
        $facture->setCreatedBy($user);
        $facture->setConditions('TVA non applicable, article 293 B du CGI. Paiement à 30 jours.');

        if ($status === 'paye') {
            $facture->setDatePaiement(new \DateTimeImmutable('-15 days'));
        }

        // Copy items from devis
        foreach ($devis->getItems() as $devisItem) {
            $factureItem = new FactureItem();
            $factureItem->setDescription($devisItem->getDescription());
            $factureItem->setQuantity($devisItem->getQuantity());
            $factureItem->setUnitPrice($devisItem->getUnitPrice());
            $factureItem->setVatRate($devisItem->getVatRate());
            $factureItem->setFacture($facture);

            $manager->persist($factureItem);
        }

        $facture->setTotalHt($devis->getTotalHt());
        $facture->calculateTotals();

        $manager->persist($facture);

        return $facture;
    }
}
