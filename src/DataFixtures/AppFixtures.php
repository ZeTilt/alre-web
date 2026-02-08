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

        // Add references for DemoFixtures
        $this->addReference('user-admin', $user);
        $this->addReference('company', $company);
        foreach ($clients as $i => $client) {
            $this->addReference('client-' . $i, $client);
        }

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
        $company->setAddress('12 Rue du Port');
        $company->setPostalCode('56000');
        $company->setCity('Vannes');
        $company->setPhone('06 12 34 56 78');
        $company->setEmail('contact@alre-web.bzh');
        $company->setSiret('12345678900012');
        $company->setWebsite('https://alre-web.bzh');
        $company->setLegalStatus('Auto-entrepreneur');
        $company->setLegalMentions('TVA non applicable, article 293 B du CGI');
        $company->setPlafondCaAnnuel('7770000');
        $company->setTauxCotisationsUrssaf('21.20');
        $company->setObjectifCaMensuel('450000');
        $company->setObjectifCaAnnuel('5400000');
        $company->setAnneeFiscaleEnCours(2026);

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
                'postalCode' => '35000',
                'city' => 'Rennes',
                'siret' => '88877766600011',
                'url' => 'https://techstart.fr',
            ],
            [
                'name' => 'BioBoutique',
                'contactFirstName' => 'Marie',
                'contactLastName' => 'Dupont',
                'email' => 'marie@bioboutique.com',
                'phone' => '02 97 34 56 78',
                'address' => '8 Rue du Commerce',
                'postalCode' => '56000',
                'city' => 'Vannes',
                'siret' => '77766655500022',
                'url' => 'https://bioboutique.com',
            ],
            [
                'name' => 'Architect Studio',
                'contactFirstName' => 'Thomas',
                'contactLastName' => 'Legrand',
                'email' => 'thomas@architectstudio.fr',
                'phone' => '02 97 45 67 89',
                'address' => '42 Quai des Indes',
                'postalCode' => '56100',
                'city' => 'Lorient',
                'siret' => '66655544400033',
                'url' => 'https://architectstudio.fr',
            ],
            [
                'name' => 'Restaurant Le Gourmet',
                'contactFirstName' => 'Pierre',
                'contactLastName' => 'Rousseau',
                'email' => 'contact@legourmet-restaurant.fr',
                'phone' => '02 97 56 78 90',
                'address' => '27 Place du Marché',
                'postalCode' => '56400',
                'city' => 'Auray',
                'siret' => '55544433300044',
                'url' => null,
            ],
            [
                'name' => 'Fitness Plus',
                'contactFirstName' => 'Julie',
                'contactLastName' => 'Bernard',
                'email' => 'julie@fitnessplus.com',
                'phone' => '02 97 67 89 01',
                'address' => '10 Rue du Sport',
                'postalCode' => '56000',
                'city' => 'Vannes',
                'siret' => '44433322200055',
                'url' => 'https://fitnessplus.com',
            ],
            [
                'name' => 'Agence Immobilière Prestige',
                'contactFirstName' => 'Laurent',
                'contactLastName' => 'Petit',
                'email' => 'l.petit@prestige-immo.fr',
                'phone' => '02 97 78 90 12',
                'address' => '5 Place de la République',
                'postalCode' => '56340',
                'city' => 'Carnac',
                'siret' => '33322211100066',
                'url' => 'https://prestige-immo.fr',
            ],
            [
                'name' => 'Boutique de Mode Élégance',
                'contactFirstName' => 'Isabelle',
                'contactLastName' => 'Moreau',
                'email' => 'contact@boutique-elegance.fr',
                'phone' => '02 99 45 67 89',
                'address' => '32 Rue de la Mode',
                'postalCode' => '35000',
                'city' => 'Rennes',
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
                'postalCode' => '29200',
                'city' => 'Brest',
                'siret' => '11100099988877',
                'url' => 'https://cabinet-juridis.fr',
            ],
            [
                'name' => 'École de Musique Harmony',
                'contactFirstName' => 'Céline',
                'contactLastName' => 'Leroy',
                'email' => 'contact@ecole-harmony.fr',
                'phone' => '02 97 20 45 67',
                'address' => '7 Rue des Arts',
                'postalCode' => '56170',
                'city' => 'Quiberon',
                'siret' => '99988877766655',
                'url' => null,
                'type' => Client::TYPE_ASSOCIATION,
            ],
            [
                'name' => 'Garage Auto+ Services',
                'contactFirstName' => 'David',
                'contactLastName' => 'Mercier',
                'email' => 'd.mercier@autoplus-services.fr',
                'phone' => '02 44 91 23 45',
                'address' => '45 Route de Nantes',
                'postalCode' => '44000',
                'city' => 'Nantes',
                'siret' => '88877766655544',
                'url' => 'https://autoplus-services.fr',
            ],
            [
                'name' => 'Fleuriste Les Roses Blanches',
                'contactFirstName' => 'Nathalie',
                'contactLastName' => 'Garnier',
                'email' => 'contact@roses-blanches.fr',
                'phone' => '02 97 56 78 90',
                'address' => '12 Place des Lices',
                'postalCode' => '56000',
                'city' => 'Vannes',
                'siret' => '77766655544433',
                'url' => null,
            ],
            [
                'name' => 'Association Aide & Solidarité',
                'contactFirstName' => 'Marc',
                'contactLastName' => 'Fontaine',
                'email' => 'contact@aide-solidarite.org',
                'phone' => '02 97 42 34 56',
                'address' => '28 Rue de la Fraternité',
                'postalCode' => '56100',
                'city' => 'Lorient',
                'siret' => '66655544433322',
                'url' => null,
                'type' => Client::TYPE_ASSOCIATION,
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
            if (isset($data['type'])) {
                $client->setType($data['type']);
            }

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
        // Service templates (tarifs page pricing)
        $sv = [ // Site Vitrine 1193€
            ['description' => 'Design moderne responsive', 'quantity' => 1, 'unitPrice' => 400],
            ['description' => 'Développement 5 pages personnalisées', 'quantity' => 5, 'unitPrice' => 99],
            ['description' => 'Formulaire de contact et SEO de base', 'quantity' => 1, 'unitPrice' => 148],
            ['description' => 'Formation gestion contenus', 'quantity' => 1, 'unitPrice' => 150],
        ];
        $pu = [ // Page Unique 493€
            ['description' => 'Création page unique responsive', 'quantity' => 1, 'unitPrice' => 393],
            ['description' => 'Formulaire contact et optimisation SEO', 'quantity' => 1, 'unitPrice' => 100],
        ];
        $ec = [ // E-commerce 2497€
            ['description' => 'Design boutique en ligne', 'quantity' => 1, 'unitPrice' => 500],
            ['description' => 'Développement catalogue et panier', 'quantity' => 1, 'unitPrice' => 997],
            ['description' => 'Paiement sécurisé et gestion stocks', 'quantity' => 1, 'unitPrice' => 500],
            ['description' => 'Espace client et emails transactionnels', 'quantity' => 1, 'unitPrice' => 300],
            ['description' => 'Formation complète', 'quantity' => 1, 'unitPrice' => 200],
        ];
        $hm = [['description' => 'Hébergement + Maintenance mensuel', 'quantity' => 12, 'unitPrice' => 53]]; // 636€
        $se = [['description' => 'Audit SEO complet et plan d\'action priorisé', 'quantity' => 1, 'unitPrice' => 247]]; // 247€
        $sv6 = [['description' => 'Pack SEO Visibilité mensuel', 'quantity' => 6, 'unitPrice' => 143]]; // 858€
        $sp6 = [['description' => 'Pack SEO Performance mensuel', 'quantity' => 6, 'unitPrice' => 227]]; // 1362€

        // [clientIdx, title, desc, items, status, devisDate, factStatus, factDate, payDate]
        // CA mensuel cible : 2500-4500€
        $entries = [
            // === Payés JANVIER 2025 (1193+493+247+493+247 = 2673€) ===
            [0, 'Site Vitrine Corporate', 'Site vitrine 5 pages startup', $sv, 'accepte', '2025-01-02', 'paye', '2025-01-15', '2025-01-28'],
            [1, 'Page Unique Promotions Bio', 'Landing page promotions', $pu, 'accepte', '2025-01-06', 'paye', '2025-01-17', '2025-01-29'],
            [3, 'Audit SEO Restaurant', 'Audit SEO site existant', $se, 'accepte', '2025-01-07', 'paye', '2025-01-16', '2025-01-28'],
            [2, 'Page Unique Architecture', 'Landing page projets', $pu, 'accepte', '2025-01-08', 'paye', '2025-01-18', '2025-01-30'],
            [4, 'Audit SEO Fitness', 'Audit SEO et recommandations', $se, 'accepte', '2025-01-10', 'paye', '2025-01-20', '2025-01-31'],

            // === Payés FÉVRIER 2025 (493+493+493+636+247+493 = 2855€) ===
            [5, 'Page Unique Immobilier', 'Landing page biens à la une', $pu, 'accepte', '2025-01-13', 'paye', '2025-02-03', '2025-02-15'],
            [7, 'Page Unique Cabinet Juridique', 'Landing page services', $pu, 'accepte', '2025-01-15', 'paye', '2025-02-05', '2025-02-18'],
            [9, 'Page Unique Garage Auto', 'Page vitrine services auto', $pu, 'accepte', '2025-01-17', 'paye', '2025-02-07', '2025-02-20'],
            [0, 'Hébergement Maintenance TechStart', 'Pack héb+maint 12 mois', $hm, 'accepte', '2025-01-20', 'paye', '2025-02-10', '2025-02-22'],
            [10, 'Audit SEO Fleuriste', 'Audit référencement', $se, 'accepte', '2025-01-22', 'paye', '2025-02-12', '2025-02-25'],
            [6, 'Page Unique Mode Élégance', 'Landing page collection', $pu, 'accepte', '2025-01-24', 'paye', '2025-02-14', '2025-02-27'],

            // === Payés MARS 2025 (1193+1193+636 = 3022€) ===
            [3, 'Site Vitrine Restaurant', 'Site complet menu et réservation', $sv, 'accepte', '2025-02-03', 'paye', '2025-03-03', '2025-03-15'],
            [2, 'Site Vitrine Architecture', 'Site portfolio projets', $sv, 'accepte', '2025-02-10', 'paye', '2025-03-10', '2025-03-22'],
            [1, 'Hébergement Maintenance BioBoutique', 'Pack héb+maint 12 mois', $hm, 'accepte', '2025-02-17', 'paye', '2025-03-12', '2025-03-25'],

            // === Payés AVRIL 2025 (1193+1193+493 = 2879€) ===
            [4, 'Site Vitrine Salle de Sport', 'Site avec planning cours', $sv, 'accepte', '2025-03-03', 'paye', '2025-04-01', '2025-04-14'],
            [6, 'Site Vitrine Boutique Mode', 'Site vitrine avec catalogue', $sv, 'accepte', '2025-03-10', 'paye', '2025-04-08', '2025-04-21'],
            [8, 'Page Unique École Musique', 'Page vitrine cours', $pu, 'accepte', '2025-03-17', 'paye', '2025-04-10', '2025-04-23'],

            // === Payés MAI 2025 (1193+1193+636 = 3022€) ===
            [7, 'Site Vitrine Cabinet Avocat', 'Site professionnel juridique', $sv, 'accepte', '2025-04-07', 'paye', '2025-05-05', '2025-05-18'],
            [5, 'Site Vitrine Agence Immobilière', 'Site avec listings biens', $sv, 'accepte', '2025-04-14', 'paye', '2025-05-12', '2025-05-25'],
            [2, 'Hébergement Maintenance Architecture', 'Pack héb+maint 12 mois', $hm, 'accepte', '2025-04-21', 'paye', '2025-05-15', '2025-05-28'],

            // === Refusé MAI (pas de CA) ===
            [8, 'Site Vitrine École Musique', 'Site complet inscriptions', $sv, 'refuse', '2025-05-05', null, null, null],

            // === Payés JUIN 2025 (1193+493+636+636 = 2958€) ===
            [9, 'Site Vitrine Garage Auto', 'Site avec prise de RDV', $sv, 'accepte', '2025-05-05', 'paye', '2025-06-02', '2025-06-15'],
            [10, 'Page Unique Bouquets', 'Landing page collections florales', $pu, 'accepte', '2025-05-12', 'paye', '2025-06-05', '2025-06-18'],
            [4, 'Hébergement Maintenance Fitness', 'Pack héb+maint 12 mois', $hm, 'accepte', '2025-05-19', 'paye', '2025-06-09', '2025-06-22'],
            [3, 'Hébergement Maintenance Restaurant', 'Pack héb+maint 12 mois', $hm, 'accepte', '2025-05-26', 'paye', '2025-06-16', '2025-06-28'],

            // === Payés JUILLET 2025 (2497+247+493 = 3237€) ===
            [6, 'Boutique en Ligne Mode', 'E-commerce catalogue produits', $ec, 'accepte', '2025-05-19', 'paye', '2025-07-01', '2025-07-15'],
            [1, 'Audit SEO BioBoutique', 'Audit SEO et plan action', $se, 'accepte', '2025-06-16', 'paye', '2025-07-07', '2025-07-18'],
            [0, 'Page Unique Campagne TechStart', 'Landing page marketing', $pu, 'accepte', '2025-06-23', 'paye', '2025-07-10', '2025-07-22'],

            // === Payés AOÛT 2025 (1193+858+636+636 = 3323€) ===
            [10, 'Site Vitrine Fleuriste', 'Site galerie créations florales', $sv, 'accepte', '2025-06-23', 'paye', '2025-08-04', '2025-08-18'],
            [1, 'Pack SEO Visibilité BioBoutique', 'SEO local 6 mois', $sv6, 'accepte', '2025-07-07', 'paye', '2025-08-01', '2025-08-15'],
            [7, 'Hébergement Maintenance Cabinet', 'Pack héb+maint 12 mois', $hm, 'accepte', '2025-07-14', 'paye', '2025-08-08', '2025-08-22'],
            [5, 'Hébergement Maintenance Immobilier', 'Pack héb+maint 12 mois', $hm, 'accepte', '2025-07-21', 'paye', '2025-08-15', '2025-08-28'],

            // === Payés SEPTEMBRE 2025 (1193+858+493 = 2544€) ===
            [8, 'Site Vitrine École Harmony', 'Site cours et inscriptions', $sv, 'accepte', '2025-07-28', 'paye', '2025-09-01', '2025-09-15'],
            [4, 'Pack SEO Visibilité Fitness', 'SEO local 6 mois', $sv6, 'accepte', '2025-08-11', 'paye', '2025-09-05', '2025-09-18'],
            [11, 'Page Unique Association', 'Page présentation activités', $pu, 'accepte', '2025-08-18', 'paye', '2025-09-08', '2025-09-22'],

            // === Payés OCTOBRE 2025 (1362+636+636+636 = 3270€) ===
            [0, 'Pack SEO Performance TechStart', 'SEO complet 6 mois', $sp6, 'accepte', '2025-09-01', 'paye', '2025-10-01', '2025-10-15'],
            [9, 'Hébergement Maintenance Garage', 'Pack héb+maint 12 mois', $hm, 'accepte', '2025-09-08', 'paye', '2025-10-03', '2025-10-16'],
            [10, 'Hébergement Maintenance Fleuriste', 'Pack héb+maint 12 mois', $hm, 'accepte', '2025-09-15', 'paye', '2025-10-08', '2025-10-22'],
            [6, 'Hébergement Maintenance Boutique', 'Pack héb+maint 12 mois', $hm, 'accepte', '2025-09-22', 'paye', '2025-10-15', '2025-10-28'],

            // === Payés NOVEMBRE 2025 (1193+858+247+858 = 3156€) ===
            [11, 'Site Vitrine Association', 'Site espace adhérents', $sv, 'accepte', '2025-09-29', 'paye', '2025-11-03', '2025-11-17'],
            [7, 'Pack SEO Visibilité Cabinet', 'SEO local 6 mois', $sv6, 'accepte', '2025-10-06', 'paye', '2025-11-05', '2025-11-18'],
            [9, 'Audit SEO Garage Auto', 'Audit référencement', $se, 'accepte', '2025-10-13', 'paye', '2025-11-07', '2025-11-20'],
            [3, 'Pack SEO Visibilité Restaurant', 'SEO local 6 mois', $sv6, 'accepte', '2025-10-20', 'paye', '2025-11-10', '2025-11-22'],

            // === Payés DÉCEMBRE 2025 (858+636+636+493 = 2623€) ===
            [2, 'Pack SEO Visibilité Architecture', 'SEO local 6 mois', $sv6, 'accepte', '2025-11-03', 'paye', '2025-12-01', '2025-12-12'],
            [8, 'Hébergement Maintenance École', 'Pack héb+maint 12 mois', $hm, 'accepte', '2025-11-10', 'paye', '2025-12-05', '2025-12-18'],
            [11, 'Hébergement Maintenance Association', 'Pack héb+maint 12 mois', $hm, 'accepte', '2025-11-17', 'paye', '2025-12-10', '2025-12-22'],
            [1, 'Page Unique Catalogue Bio', 'Landing page nouveau catalogue', $pu, 'accepte', '2025-11-24', 'paye', '2025-12-15', '2025-12-28'],

            // === Expiré / Envoyé fin 2025 ===
            [2, 'Pack SEO Performance Architecture', 'SEO complet 6 mois', $sp6, 'expire', '2025-12-01', null, null, null],
            [7, 'Pack SEO Performance Cabinet', 'SEO complet 6 mois', $sp6, 'envoye', '2025-12-08', null, null, null],

            // === Payés JANVIER 2026 (1362+493+493+247+247 = 2842€) ===
            [5, 'Pack SEO Performance Immobilier', 'SEO complet 6 mois', $sp6, 'accepte', '2025-12-01', 'paye', '2026-01-06', '2026-01-18'],
            [9, 'Page Unique Promotions Garage', 'Landing page promos', $pu, 'accepte', '2025-12-08', 'paye', '2026-01-08', '2026-01-20'],
            [4, 'Page Unique Planning Fitness', 'Landing page nouveau planning', $pu, 'accepte', '2025-12-15', 'paye', '2026-01-12', '2026-01-25'],
            [3, 'Audit SEO Restaurant 2026', 'Audit SEO annuel', $se, 'accepte', '2025-12-22', 'paye', '2026-01-15', '2026-01-28'],
            [11, 'Audit SEO Association', 'Audit SEO et recommandations', $se, 'accepte', '2026-01-06', 'paye', '2026-01-20', '2026-01-31'],

            // === 2026 en cours ===
            [5, 'Site E-commerce Immobilier', 'Boutique en ligne visites virtuelles', $ec, 'accepte', '2025-12-20', 'envoye', '2026-02-03', null],
            [4, 'Pack SEO Performance Fitness', 'SEO complet 6 mois', $sp6, 'envoye', '2026-01-28', null, null, null],
            [9, 'Boutique en Ligne Pièces Auto', 'E-commerce pièces et accessoires', $ec, 'brouillon', '2026-02-05', null, null, null],
        ];

        // Status mapping
        $statusMap = [
            'accepte' => Devis::STATUS_ACCEPTE,
            'refuse' => Devis::STATUS_REFUSE,
            'expire' => Devis::STATUS_EXPIRE,
            'envoye' => Devis::STATUS_ENVOYE,
            'brouillon' => Devis::STATUS_BROUILLON,
        ];

        // Pass 1: Create all devis
        $devisNum = ['2025' => 0, '2026' => 0];
        $createdDevis = [];
        foreach ($entries as $idx => $e) {
            [$ci, $title, $desc, $items, $status, $dDate, $fStatus, $fDate, $pDate] = $e;
            $devisDate = new \DateTimeImmutable($dDate);
            $year = $devisDate->format('Y');

            if ($status === 'brouillon') {
                $devisNumber = null;
            } else {
                $devisNum[$year] = ($devisNum[$year] ?? 0) + 1;
                $devisNumber = sprintf('DEV-%s-%03d', $year, $devisNum[$year]);
            }

            $d = $this->createDevis($manager, $user, $clients[$ci], $devisNumber,
                $title, $desc, $statusMap[$status], $items, $devisDate);
            $d->setDateEnvoi($devisDate->modify('+2 days'));
            $d->setDateValidite($devisDate->modify('+32 days'));
            if (in_array($status, ['accepte', 'refuse'])) {
                $d->setDateReponse($devisDate->modify('+8 days'));
            }

            $createdDevis[$idx] = ['devis' => $d, 'clientIdx' => $ci];
        }

        // Pass 2: Create factures sorted by factureDate
        $facData = [];
        foreach ($entries as $idx => $e) {
            if ($e[6] !== null) {
                $facData[] = ['idx' => $idx, 'fStatus' => $e[6], 'fDate' => $e[7], 'pDate' => $e[8]];
            }
        }
        usort($facData, fn($a, $b) => $a['fDate'] <=> $b['fDate']);

        $factNum = ['2025' => 0, '2026' => 0];
        foreach ($facData as $fd) {
            $factureDate = $fd['fDate'] ? new \DateTimeImmutable($fd['fDate']) : null;
            $paymentDate = $fd['pDate'] ? new \DateTimeImmutable($fd['pDate']) : null;
            $year = $factureDate ? $factureDate->format('Y') : '2025';
            $factNum[$year] = ($factNum[$year] ?? 0) + 1;
            $factNumber = sprintf('FACT-%s-%03d', $year, $factNum[$year]);

            $cd = $createdDevis[$fd['idx']];
            $this->createFacture($manager, $user, $clients[$cd['clientIdx']],
                $cd['devis'], $factNumber, $fd['fStatus'], $factureDate, $paymentDate);
        }
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
            $item->setVatRate(0.00);

            $devis->addItem($item);
            $manager->persist($item);
        }

        $devis->setVatRate('0.00');
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
        $facture->setVatRate('0.00');
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
