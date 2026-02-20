<?php

namespace App\DataFixtures;

use App\Entity\City;
use App\Entity\Client;
use App\Entity\ClientSeoDailyTotal;
use App\Entity\ClientSeoKeyword;
use App\Entity\ClientSeoPage;
use App\Entity\ClientSeoPosition;
use App\Entity\ClientSite;
use App\Entity\Event;
use App\Entity\EventType;
use App\Entity\GoogleOAuthToken;
use App\Entity\GoogleReview;
use App\Entity\Prospect;
use App\Entity\ProspectContact;
use App\Entity\ProspectFollowUp;
use App\Entity\ProspectInteraction;
use App\Entity\SeoDailyTotal;
use App\Entity\SeoKeyword;
use App\Entity\SeoPosition;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class DemoFixtures extends Fixture implements DependentFixtureInterface
{
    public function getDependencies(): array
    {
        return [AppFixtures::class];
    }

    public function load(ObjectManager $manager): void
    {
        // 0. Google OAuth Token (needed for SEO dashboard to display)
        $this->createGoogleOAuthToken($manager);

        // 1. Event Types & Events
        $eventTypes = $this->createEventTypes($manager);
        $this->createEvents($manager, $eventTypes);

        // 2. Prospects
        $this->createProspects($manager);

        // 3. SEO Keywords & Positions
        $this->createSeoData($manager);

        // 4. SEO Daily Totals
        $this->createSeoDailyTotals($manager);

        // 5. Google Reviews
        $this->createGoogleReviews($manager);

        // 6. Client SEO (Fitness Plus)
        $this->createClientSeoData($manager);

        // 7. Cities
        $this->createCities($manager);

        $manager->flush();

        echo "\n✓ Demo fixtures loaded successfully!\n";
        echo "  - Google OAuth Token: 1\n";
        echo "  - Event Types: 5\n";
        echo "  - Events: 15\n";
        echo "  - Prospects: 12\n";
        echo "  - SEO Keywords: 35\n";
        echo "  - SEO Daily Totals: 35\n";
        echo "  - Google Reviews: 6\n";
        echo "  - Client Sites: 1\n";
        echo "  - Cities: 5\n";
    }

    // =========================================================================
    // GOOGLE OAUTH TOKEN
    // =========================================================================

    private function createGoogleOAuthToken(ObjectManager $manager): void
    {
        $token = new GoogleOAuthToken();
        $token->setAccessToken('demo_access_token_fixture')
            ->setRefreshToken('demo_refresh_token_fixture')
            ->setExpiresAt(new \DateTimeImmutable('+30 days'))
            ->setScope('https://www.googleapis.com/auth/webmasters.readonly');
        $manager->persist($token);
    }

    // =========================================================================
    // EVENT TYPES
    // =========================================================================

    private function createEventTypes(ObjectManager $manager): array
    {
        $types = [
            ['name' => 'Rendez-vous client', 'color' => '#27A3B4', 'position' => 1],
            ['name' => 'Développement', 'color' => '#3A4556', 'position' => 2],
            ['name' => 'Prospection', 'color' => '#D5B18A', 'position' => 3],
            ['name' => 'Formation', 'color' => '#28a745', 'position' => 4],
            ['name' => 'Administratif', 'color' => '#8E8E93', 'position' => 5],
        ];

        $eventTypes = [];
        foreach ($types as $data) {
            $et = new EventType();
            $et->setName($data['name']);
            $et->setColor($data['color']);
            $et->setPosition($data['position']);
            $manager->persist($et);
            $eventTypes[$data['name']] = $et;
        }

        return $eventTypes;
    }

    // =========================================================================
    // EVENTS
    // =========================================================================

    private function createEvents(ObjectManager $manager, array $eventTypes): void
    {
        $client0 = $this->getReference('client-0', Client::class); // TechStart
        $client1 = $this->getReference('client-1', Client::class); // BioBoutique
        $client4 = $this->getReference('client-4', Client::class); // Fitness Plus
        $client11 = $this->getReference('client-11', Client::class); // Association

        $events = [
            // 5 passés (jan - début fév)
            [
                'title' => 'RDV TechStart - Validation maquettes',
                'type' => 'Rendez-vous client',
                'start' => '2026-01-20 10:00',
                'end' => '2026-01-20 11:30',
                'location' => 'Visioconférence',
                'client' => $client0,
            ],
            [
                'title' => 'Livraison site Association Aide & Solidarité',
                'type' => 'Développement',
                'start' => '2026-01-24',
                'end' => '2026-01-24',
                'allDay' => true,
                'client' => $client11,
            ],
            [
                'title' => 'Formation BioBoutique - Back-office',
                'type' => 'Formation',
                'start' => '2026-01-28 14:00',
                'end' => '2026-01-28 16:00',
                'location' => 'BioBoutique, Nantes',
                'client' => $client1,
            ],
            [
                'title' => 'RDV prospect Camping du Littoral',
                'type' => 'Prospection',
                'start' => '2026-02-03 09:30',
                'end' => '2026-02-03 10:30',
                'location' => 'Carnac',
            ],
            [
                'title' => 'Déclaration URSSAF T4 2025',
                'type' => 'Administratif',
                'start' => '2026-02-04 09:00',
                'end' => '2026-02-04 10:00',
                'description' => 'Déclaration trimestrielle URSSAF',
            ],

            // 5 cette semaine (7-14 fév)
            [
                'title' => 'RDV BioBoutique - Point évolutions',
                'type' => 'Rendez-vous client',
                'start' => '2026-02-09 10:00',
                'end' => '2026-02-09 11:00',
                'location' => 'Visioconférence',
                'client' => $client1,
            ],
            [
                'title' => 'Sprint dev - Refonte TechStart',
                'type' => 'Développement',
                'start' => '2026-02-10 09:00',
                'end' => '2026-02-12 18:00',
                'description' => 'Développement pages produits et intégration API',
                'client' => $client0,
            ],
            [
                'title' => 'Prospection LinkedIn - Artisans Morbihan',
                'type' => 'Prospection',
                'start' => '2026-02-11 14:00',
                'end' => '2026-02-11 15:30',
            ],
            [
                'title' => 'Formation Symfony 7.2 - Nouveautés',
                'type' => 'Formation',
                'start' => '2026-02-12 10:00',
                'end' => '2026-02-12 12:00',
                'description' => 'Veille technique : nouvelles features Symfony 7.2',
            ],
            [
                'title' => 'Suivi projet Association',
                'type' => 'Rendez-vous client',
                'start' => '2026-02-13 15:00',
                'end' => '2026-02-13 16:00',
                'location' => 'Visioconférence',
                'client' => $client11,
            ],

            // 5 futurs (15 fév - mars)
            [
                'title' => 'Démo Fitness Plus - Module planning',
                'type' => 'Rendez-vous client',
                'start' => '2026-02-17 14:00',
                'end' => '2026-02-17 15:30',
                'location' => 'Fitness Plus, Lille',
                'client' => $client4,
            ],
            [
                'title' => 'Livraison Refonte TechStart',
                'type' => 'Développement',
                'start' => '2026-02-21',
                'end' => '2026-02-21',
                'allDay' => true,
                'client' => $client0,
            ],
            [
                'title' => 'RDV prospect Cave à Vins',
                'type' => 'Prospection',
                'start' => '2026-02-25 11:00',
                'end' => '2026-02-25 12:00',
                'location' => 'Visioconférence',
            ],
            [
                'title' => 'Suivi SEO - Bilan mensuel',
                'type' => 'Administratif',
                'start' => '2026-03-02 09:00',
                'end' => '2026-03-02 10:30',
                'description' => 'Analyse des performances SEO du mois de février',
            ],
            [
                'title' => 'RDV comptable - Bilan annuel 2025',
                'type' => 'Administratif',
                'start' => '2026-03-10 14:00',
                'end' => '2026-03-10 16:00',
                'location' => 'Cabinet comptable, Vannes',
            ],
        ];

        foreach ($events as $data) {
            $event = new Event();
            $event->setTitle($data['title']);
            $event->setEventType($eventTypes[$data['type']]);
            $event->setDescription($data['description'] ?? null);
            $event->setLocation($data['location'] ?? null);
            $event->setClient($data['client'] ?? null);
            $event->setAllDay($data['allDay'] ?? false);

            if ($data['allDay'] ?? false) {
                $event->setStartAt(new \DateTime($data['start'] . ' 00:00:00'));
                $event->setEndAt(new \DateTime($data['end'] . ' 23:59:59'));
            } else {
                $event->setStartAt(new \DateTime($data['start']));
                $event->setEndAt(isset($data['end']) ? new \DateTime($data['end']) : null);
            }

            $manager->persist($event);
        }
    }

    // =========================================================================
    // PROSPECTS
    // =========================================================================

    private function createProspects(ObjectManager $manager): void
    {
        $prospectsData = [
            // === IDENTIFIED (3) ===
            [
                'companyName' => 'Boulangerie Artisanale Le Fournil',
                'activity' => 'Boulangerie-pâtisserie artisanale',
                'city' => 'Vannes', 'postalCode' => '56000',
                'source' => Prospect::SOURCE_WEBSITE,
                'status' => Prospect::STATUS_IDENTIFIED,
                'estimatedValue' => '1200',
                'notes' => 'A trouvé notre site via Google. Pas encore de site web, présence uniquement Facebook.',
                'createdAt' => '2026-01-25',
                'contacts' => [
                    ['firstName' => 'Anne', 'lastName' => 'Le Goff', 'email' => 'lefournil.vannes@gmail.com', 'phone' => '02 97 45 12 34', 'role' => 'Gérante', 'isPrimary' => true],
                ],
                'interactions' => [],
                'followUps' => [
                    ['type' => 'email', 'subject' => 'Premier contact - Présentation services', 'dueAt' => '2026-02-10'],
                ],
            ],
            [
                'companyName' => 'Yoga Studio Zen',
                'activity' => 'Studio de yoga et bien-être',
                'city' => 'Lorient', 'postalCode' => '56100',
                'source' => Prospect::SOURCE_LINKEDIN,
                'status' => Prospect::STATUS_IDENTIFIED,
                'estimatedValue' => '1800',
                'notes' => 'Profil LinkedIn actif, recherche de visibilité en ligne. Site existant très daté.',
                'createdAt' => '2026-02-01',
                'contacts' => [
                    ['firstName' => 'Camille', 'lastName' => 'Morvan', 'email' => 'camille@yogazen-lorient.fr', 'role' => 'Fondatrice', 'isPrimary' => true],
                ],
                'interactions' => [],
                'followUps' => [
                    ['type' => 'linkedin', 'subject' => 'Message LinkedIn - Présentation', 'dueAt' => '2026-02-12'],
                ],
            ],
            [
                'companyName' => 'EdTech Solutions',
                'activity' => 'Startup éducation en ligne',
                'city' => 'Paris', 'postalCode' => '75011',
                'website' => 'https://edtech-solutions.fr',
                'source' => Prospect::SOURCE_REFERRAL,
                'sourceDetail' => 'Recommandation Sophie Martin (TechStart)',
                'status' => Prospect::STATUS_IDENTIFIED,
                'estimatedValue' => '8000',
                'notes' => 'Startup EdTech en croissance, besoin d\'une refonte plateforme. Recommandé par TechStart.',
                'createdAt' => '2026-02-05',
                'contacts' => [
                    ['firstName' => 'Maxime', 'lastName' => 'Dufour', 'email' => 'maxime@edtech-solutions.fr', 'phone' => '06 78 90 12 34', 'role' => 'CTO', 'isPrimary' => true],
                    ['firstName' => 'Laura', 'lastName' => 'Petit', 'email' => 'laura@edtech-solutions.fr', 'role' => 'CEO'],
                ],
                'interactions' => [],
                'followUps' => [
                    ['type' => 'email', 'subject' => 'Email d\'introduction via Sophie Martin', 'dueAt' => '2026-02-09'],
                ],
            ],

            // === CONTACTED (3) ===
            [
                'companyName' => 'Cabinet Comptable Breizh',
                'activity' => 'Expertise comptable',
                'city' => 'Rennes', 'postalCode' => '35000',
                'website' => 'https://cabinet-breizh.fr',
                'source' => Prospect::SOURCE_COLD_EMAIL,
                'status' => Prospect::STATUS_CONTACTED,
                'estimatedValue' => '3500',
                'notes' => 'Site web actuel peu attractif, pas de prise de RDV en ligne.',
                'createdAt' => '2026-01-15',
                'lastContactAt' => '2026-01-20',
                'contacts' => [
                    ['firstName' => 'Philippe', 'lastName' => 'Le Breton', 'email' => 'p.lebreton@cabinet-breizh.fr', 'phone' => '02 99 34 56 78', 'role' => 'Associé', 'isPrimary' => true],
                ],
                'interactions' => [
                    ['type' => 'email', 'direction' => 'sent', 'subject' => 'Email de prospection - Modernisation site web', 'createdAt' => '2026-01-18'],
                    ['type' => 'email', 'direction' => 'received', 'subject' => 'RE: Modernisation site web - Intéressé', 'createdAt' => '2026-01-20'],
                ],
                'followUps' => [
                    ['type' => 'phone', 'subject' => 'Appel pour planifier un RDV découverte', 'dueAt' => '2026-02-08'],
                ],
            ],
            [
                'companyName' => 'Salon Coiff\'Nature',
                'activity' => 'Salon de coiffure bio',
                'city' => 'Quiberon', 'postalCode' => '56170',
                'source' => Prospect::SOURCE_FACEBOOK,
                'status' => Prospect::STATUS_CONTACTED,
                'estimatedValue' => '900',
                'notes' => 'Salon de coiffure bio, très actif sur Facebook mais pas de site web.',
                'createdAt' => '2026-01-22',
                'lastContactAt' => '2026-01-28',
                'contacts' => [
                    ['firstName' => 'Sandrine', 'lastName' => 'Kervella', 'email' => 'sandrine.coiffnature@gmail.com', 'phone' => '06 12 34 56 78', 'role' => 'Gérante', 'isPrimary' => true],
                ],
                'interactions' => [
                    ['type' => 'facebook', 'direction' => 'sent', 'subject' => 'Message Facebook - Proposition site vitrine', 'createdAt' => '2026-01-25'],
                    ['type' => 'facebook', 'direction' => 'received', 'subject' => 'RE: Intéressée, demande de tarifs', 'createdAt' => '2026-01-28'],
                ],
                'followUps' => [
                    ['type' => 'email', 'subject' => 'Envoi brochure tarifaire', 'dueAt' => '2026-02-07'],
                ],
            ],
            [
                'companyName' => 'Coach Sportif Atlantic',
                'activity' => 'Coaching sportif personnel',
                'city' => 'Nantes', 'postalCode' => '44000',
                'website' => 'https://coach-atlantic.fr',
                'source' => Prospect::SOURCE_LINKEDIN,
                'status' => Prospect::STATUS_CONTACTED,
                'estimatedValue' => '2200',
                'notes' => 'Coaching sportif, site existant WordPress lent. Cherche plus de professionnalisme.',
                'createdAt' => '2026-01-10',
                'lastContactAt' => '2026-01-18',
                'contacts' => [
                    ['firstName' => 'Romain', 'lastName' => 'Guégan', 'email' => 'romain@coach-atlantic.fr', 'phone' => '06 45 67 89 01', 'role' => 'Fondateur', 'isPrimary' => true],
                ],
                'interactions' => [
                    ['type' => 'linkedin', 'direction' => 'sent', 'subject' => 'Message LinkedIn - Présentation services web', 'createdAt' => '2026-01-12'],
                    ['type' => 'linkedin', 'direction' => 'received', 'subject' => 'RE: Intéressé par une refonte', 'createdAt' => '2026-01-15'],
                    ['type' => 'email', 'direction' => 'sent', 'subject' => 'Suite LinkedIn - Portfolio et références', 'createdAt' => '2026-01-18'],
                ],
                'followUps' => [
                    ['type' => 'phone', 'subject' => 'Relance téléphonique', 'dueAt' => '2026-02-06'],
                ],
            ],

            // === IN_DISCUSSION (3) ===
            [
                'companyName' => 'Clinique Vétérinaire du Scorff',
                'activity' => 'Clinique vétérinaire',
                'city' => 'Lorient', 'postalCode' => '56100',
                'website' => 'https://vetoscorff.fr',
                'source' => Prospect::SOURCE_REFERRAL,
                'sourceDetail' => 'Recommandation client BioBoutique',
                'status' => Prospect::STATUS_IN_DISCUSSION,
                'estimatedValue' => '4500',
                'notes' => 'Clinique vétérinaire 3 praticiens. Besoin site + prise de RDV en ligne. Budget validé en réunion.',
                'createdAt' => '2026-01-05',
                'lastContactAt' => '2026-02-03',
                'contacts' => [
                    ['firstName' => 'Dr. Yann', 'lastName' => 'Le Meur', 'email' => 'yann.lemeur@vetoscorff.fr', 'phone' => '02 97 21 43 65', 'role' => 'Vétérinaire associé', 'isPrimary' => true],
                    ['firstName' => 'Nolwenn', 'lastName' => 'Bertho', 'email' => 'accueil@vetoscorff.fr', 'role' => 'Assistante administrative'],
                ],
                'interactions' => [
                    ['type' => 'email', 'direction' => 'sent', 'subject' => 'Proposition de refonte site web', 'createdAt' => '2026-01-08'],
                    ['type' => 'meeting', 'direction' => 'sent', 'subject' => 'RDV découverte - Besoins et budget', 'createdAt' => '2026-01-15'],
                    ['type' => 'email', 'direction' => 'sent', 'subject' => 'CR réunion + premières recommandations', 'createdAt' => '2026-01-17'],
                    ['type' => 'video_call', 'direction' => 'sent', 'subject' => 'Présentation maquettes', 'createdAt' => '2026-02-03'],
                ],
                'followUps' => [
                    ['type' => 'email', 'subject' => 'Envoi devis détaillé', 'dueAt' => '2026-02-11'],
                ],
            ],
            [
                'companyName' => 'Camping du Littoral',
                'activity' => 'Camping 3 étoiles',
                'city' => 'Carnac', 'postalCode' => '56340',
                'website' => 'https://camping-littoral-carnac.fr',
                'source' => Prospect::SOURCE_WEBSITE,
                'status' => Prospect::STATUS_IN_DISCUSSION,
                'estimatedValue' => '5500',
                'notes' => 'Camping 3 étoiles, saison commence en avril. Urgence pour refonte avant été 2026.',
                'createdAt' => '2026-01-08',
                'lastContactAt' => '2026-02-01',
                'contacts' => [
                    ['firstName' => 'Jean-Marc', 'lastName' => 'Hervé', 'email' => 'jm.herve@camping-littoral.fr', 'phone' => '02 97 52 13 24', 'role' => 'Directeur', 'isPrimary' => true],
                ],
                'interactions' => [
                    ['type' => 'email', 'direction' => 'received', 'subject' => 'Demande via formulaire contact - Refonte site', 'createdAt' => '2026-01-08'],
                    ['type' => 'phone', 'direction' => 'sent', 'subject' => 'Appel découverte - Besoins et planning', 'createdAt' => '2026-01-10'],
                    ['type' => 'meeting', 'direction' => 'sent', 'subject' => 'Visite sur site - Tour du camping', 'createdAt' => '2026-01-20'],
                    ['type' => 'email', 'direction' => 'sent', 'subject' => 'Envoi benchmark + recommandations', 'createdAt' => '2026-02-01'],
                ],
                'followUps' => [
                    ['type' => 'email', 'subject' => 'Envoi proposition commerciale', 'dueAt' => '2026-02-09'],
                ],
            ],
            [
                'companyName' => 'Cabinet Ostéo Toulouse',
                'activity' => 'Cabinet d\'ostéopathie',
                'city' => 'Toulouse', 'postalCode' => '31000',
                'source' => Prospect::SOURCE_LINKEDIN,
                'status' => Prospect::STATUS_IN_DISCUSSION,
                'estimatedValue' => '2800',
                'notes' => 'Cabinet 2 praticiens, besoin Doctolib-like mais en propre. Déjà un WordPress basique.',
                'createdAt' => '2026-01-12',
                'lastContactAt' => '2026-01-30',
                'contacts' => [
                    ['firstName' => 'Antoine', 'lastName' => 'Dupuy', 'email' => 'a.dupuy@osteo-toulouse.fr', 'phone' => '06 23 45 67 89', 'role' => 'Ostéopathe', 'isPrimary' => true],
                ],
                'interactions' => [
                    ['type' => 'linkedin', 'direction' => 'sent', 'subject' => 'Message LinkedIn - Site pro + réservation', 'createdAt' => '2026-01-14'],
                    ['type' => 'video_call', 'direction' => 'sent', 'subject' => 'Visio découverte - Besoins et fonctionnalités', 'createdAt' => '2026-01-22'],
                    ['type' => 'email', 'direction' => 'sent', 'subject' => 'CR visio + exemples réalisations similaires', 'createdAt' => '2026-01-24'],
                    ['type' => 'email', 'direction' => 'received', 'subject' => 'RE: Très intéressé, attente devis', 'createdAt' => '2026-01-30'],
                ],
                'followUps' => [
                    ['type' => 'email', 'subject' => 'Préparation et envoi du devis', 'dueAt' => '2026-02-13'],
                ],
            ],

            // === QUOTE_SENT (2) ===
            [
                'companyName' => 'Hôtel Bord de Mer',
                'activity' => 'Hôtel 4 étoiles',
                'city' => 'Carnac', 'postalCode' => '56340',
                'website' => 'https://hotel-bordmer-carnac.fr',
                'source' => Prospect::SOURCE_EVENT,
                'sourceDetail' => 'Salon du tourisme Vannes 2025',
                'status' => Prospect::STATUS_QUOTE_SENT,
                'estimatedValue' => '7500',
                'notes' => 'Hôtel 4 étoiles, refonte complète avec booking engine. Rencontré au salon du tourisme.',
                'createdAt' => '2025-12-10',
                'lastContactAt' => '2026-01-25',
                'contacts' => [
                    ['firstName' => 'Claire', 'lastName' => 'Tanguy', 'email' => 'direction@hotel-bordmer.fr', 'phone' => '02 97 52 87 65', 'role' => 'Directrice', 'isPrimary' => true],
                    ['firstName' => 'Stéphane', 'lastName' => 'Riou', 'email' => 's.riou@hotel-bordmer.fr', 'role' => 'Responsable marketing'],
                ],
                'interactions' => [
                    ['type' => 'meeting', 'direction' => 'sent', 'subject' => 'Salon du tourisme - Premier contact', 'createdAt' => '2025-12-10'],
                    ['type' => 'email', 'direction' => 'sent', 'subject' => 'Suite salon - Portfolio et références hôtelières', 'createdAt' => '2025-12-15'],
                    ['type' => 'video_call', 'direction' => 'sent', 'subject' => 'Visio - Cahier des charges détaillé', 'createdAt' => '2026-01-08'],
                    ['type' => 'email', 'direction' => 'sent', 'subject' => 'Envoi devis détaillé', 'createdAt' => '2026-01-25'],
                ],
                'followUps' => [
                    ['type' => 'phone', 'subject' => 'Relance téléphonique devis', 'dueAt' => '2026-02-06'],
                ],
            ],
            [
                'companyName' => 'Cave à Vins Le Chai',
                'activity' => 'Cave à vins et épicerie fine',
                'city' => 'Bordeaux', 'postalCode' => '33000',
                'source' => Prospect::SOURCE_REFERRAL,
                'sourceDetail' => 'Recommandation Pierre Rousseau (Restaurant Le Gourmet)',
                'status' => Prospect::STATUS_QUOTE_SENT,
                'estimatedValue' => '3200',
                'notes' => 'Cave à vins haut de gamme, veut un site e-commerce. Recommandé par le Restaurant Le Gourmet.',
                'createdAt' => '2026-01-02',
                'lastContactAt' => '2026-02-01',
                'contacts' => [
                    ['firstName' => 'Vincent', 'lastName' => 'Castex', 'email' => 'vincent@lechai-bordeaux.fr', 'phone' => '05 56 78 90 12', 'role' => 'Propriétaire', 'isPrimary' => true],
                ],
                'interactions' => [
                    ['type' => 'email', 'direction' => 'sent', 'subject' => 'Recommandation Pierre Rousseau - Présentation', 'createdAt' => '2026-01-05'],
                    ['type' => 'phone', 'direction' => 'sent', 'subject' => 'Appel découverte - Besoins e-commerce', 'createdAt' => '2026-01-12'],
                    ['type' => 'email', 'direction' => 'sent', 'subject' => 'Envoi proposition commerciale e-commerce', 'createdAt' => '2026-02-01'],
                ],
                'followUps' => [
                    ['type' => 'email', 'subject' => 'Relance devis e-commerce', 'dueAt' => '2026-02-15'],
                ],
            ],

            // === WON (1) ===
            [
                'companyName' => 'Restaurant Le Gourmet',
                'activity' => 'Restaurant gastronomique',
                'city' => 'Bordeaux', 'postalCode' => '33000',
                'source' => Prospect::SOURCE_COLD_EMAIL,
                'status' => Prospect::STATUS_WON,
                'estimatedValue' => '6600',
                'notes' => 'Converti en client ! Projet de refonte site restaurant livré en janvier 2026.',
                'createdAt' => '2025-09-15',
                'lastContactAt' => '2025-10-30',
                'convertedClient' => 3, // clients[3] = Restaurant Le Gourmet
                'contacts' => [
                    ['firstName' => 'Pierre', 'lastName' => 'Rousseau', 'email' => 'contact@legourmet-restaurant.fr', 'phone' => '04 56 78 90 12', 'role' => 'Gérant', 'isPrimary' => true],
                ],
                'interactions' => [
                    ['type' => 'email', 'direction' => 'sent', 'subject' => 'Prospection - Refonte site restaurant', 'createdAt' => '2025-09-18'],
                    ['type' => 'phone', 'direction' => 'sent', 'subject' => 'Appel découverte', 'createdAt' => '2025-09-25'],
                    ['type' => 'meeting', 'direction' => 'sent', 'subject' => 'RDV au restaurant - Présentation', 'createdAt' => '2025-10-05'],
                    ['type' => 'email', 'direction' => 'sent', 'subject' => 'Envoi devis', 'createdAt' => '2025-10-20'],
                    ['type' => 'email', 'direction' => 'received', 'subject' => 'Devis accepté !', 'createdAt' => '2025-10-30'],
                ],
                'followUps' => [],
            ],
        ];

        foreach ($prospectsData as $data) {
            $prospect = new Prospect();
            $prospect->setCompanyName($data['companyName']);
            $prospect->setActivity($data['activity']);
            $prospect->setCity($data['city']);
            $prospect->setPostalCode($data['postalCode']);
            $prospect->setWebsite($data['website'] ?? null);
            $prospect->setSource($data['source']);
            $prospect->setSourceDetail($data['sourceDetail'] ?? null);
            $prospect->setStatus($data['status']);
            $prospect->setEstimatedValue($data['estimatedValue']);
            $prospect->setNotes($data['notes']);
            $prospect->setCreatedAt(new \DateTimeImmutable($data['createdAt']));
            $prospect->setUpdatedAt(new \DateTimeImmutable($data['lastContactAt'] ?? $data['createdAt']));
            if (isset($data['lastContactAt'])) {
                $prospect->setLastContactAt(new \DateTimeImmutable($data['lastContactAt']));
            }

            if (isset($data['convertedClient'])) {
                $prospect->setConvertedClient($this->getReference('client-' . $data['convertedClient'], Client::class));
            }

            $contacts = [];
            foreach ($data['contacts'] as $cData) {
                $contact = new ProspectContact();
                $contact->setFirstName($cData['firstName']);
                $contact->setLastName($cData['lastName']);
                $contact->setEmail($cData['email'] ?? null);
                $contact->setPhone($cData['phone'] ?? null);
                $contact->setRole($cData['role'] ?? null);
                $contact->setIsPrimary($cData['isPrimary'] ?? false);
                $contact->setCreatedAt(new \DateTimeImmutable($data['createdAt']));
                $prospect->addContact($contact);
                $manager->persist($contact);
                $contacts[] = $contact;
            }

            foreach ($data['interactions'] as $iData) {
                $interaction = new ProspectInteraction();
                $interaction->setType($iData['type']);
                $interaction->setDirection($iData['direction']);
                $interaction->setSubject($iData['subject']);
                $interaction->setCreatedAt(new \DateTimeImmutable($iData['createdAt']));
                $interaction->setContact($contacts[0] ?? null);
                $prospect->addInteraction($interaction);
                $manager->persist($interaction);
            }

            foreach ($data['followUps'] as $fData) {
                $followUp = new ProspectFollowUp();
                $followUp->setType($fData['type']);
                $followUp->setSubject($fData['subject']);
                $followUp->setDueAt(new \DateTime($fData['dueAt']));
                $followUp->setIsCompleted(false);
                $followUp->setCreatedAt(new \DateTimeImmutable($data['createdAt']));
                $followUp->setContact($contacts[0] ?? null);
                $prospect->addFollowUp($followUp);
                $manager->persist($followUp);
            }

            $manager->persist($prospect);
        }
    }

    // =========================================================================
    // SEO KEYWORDS & POSITIONS
    // =========================================================================

    private function createSeoData(ObjectManager $manager): void
    {
        $keywordsData = [
            // HIGH RELEVANCE (12)
            ['keyword' => 'développeur web vannes', 'relevance' => 'high', 'url' => 'https://alre-web.bzh/', 'basePos' => 5, 'baseClicks' => 15, 'baseImpr' => 120],
            ['keyword' => 'création site internet morbihan', 'relevance' => 'high', 'url' => 'https://alre-web.bzh/services', 'basePos' => 8, 'baseClicks' => 10, 'baseImpr' => 95],
            ['keyword' => 'agence web bretagne', 'relevance' => 'high', 'url' => 'https://alre-web.bzh/', 'basePos' => 12, 'baseClicks' => 8, 'baseImpr' => 150],
            ['keyword' => 'developpeur freelance vannes', 'relevance' => 'high', 'url' => 'https://alre-web.bzh/', 'basePos' => 3, 'baseClicks' => 25, 'baseImpr' => 80],
            ['keyword' => 'site internet vannes', 'relevance' => 'high', 'url' => 'https://alre-web.bzh/services', 'basePos' => 7, 'baseClicks' => 12, 'baseImpr' => 110],
            ['keyword' => 'création site web auray', 'relevance' => 'high', 'url' => 'https://alre-web.bzh/developpeur-web-auray', 'basePos' => 6, 'baseClicks' => 8, 'baseImpr' => 60],
            ['keyword' => 'développeur symfony bretagne', 'relevance' => 'high', 'url' => 'https://alre-web.bzh/', 'basePos' => 4, 'baseClicks' => 6, 'baseImpr' => 45],
            ['keyword' => 'refonte site internet morbihan', 'relevance' => 'high', 'url' => 'https://alre-web.bzh/services', 'basePos' => 10, 'baseClicks' => 5, 'baseImpr' => 55],
            ['keyword' => 'tarif site internet vannes', 'relevance' => 'high', 'url' => 'https://alre-web.bzh/tarifs', 'basePos' => 9, 'baseClicks' => 7, 'baseImpr' => 40],
            ['keyword' => 'webmaster vannes', 'relevance' => 'high', 'url' => 'https://alre-web.bzh/', 'basePos' => 15, 'baseClicks' => 3, 'baseImpr' => 35],
            ['keyword' => 'création site e-commerce bretagne', 'relevance' => 'high', 'url' => 'https://alre-web.bzh/services', 'basePos' => 18, 'baseClicks' => 4, 'baseImpr' => 70],
            ['keyword' => 'développeur php vannes', 'relevance' => 'high', 'url' => 'https://alre-web.bzh/', 'basePos' => 6, 'baseClicks' => 5, 'baseImpr' => 30],

            // MEDIUM RELEVANCE (13)
            ['keyword' => 'alré web', 'relevance' => 'medium', 'url' => 'https://alre-web.bzh/', 'basePos' => 1, 'baseClicks' => 5, 'baseImpr' => 8],
            ['keyword' => 'site vitrine prix', 'relevance' => 'medium', 'url' => 'https://alre-web.bzh/tarifs', 'basePos' => 25, 'baseClicks' => 2, 'baseImpr' => 50],
            ['keyword' => 'développeur web freelance', 'relevance' => 'medium', 'url' => 'https://alre-web.bzh/', 'basePos' => 35, 'baseClicks' => 1, 'baseImpr' => 80],
            ['keyword' => 'creation site internet pas cher', 'relevance' => 'medium', 'url' => 'https://alre-web.bzh/tarifs', 'basePos' => 40, 'baseClicks' => 1, 'baseImpr' => 60],
            ['keyword' => 'seo bretagne', 'relevance' => 'medium', 'url' => 'https://alre-web.bzh/services', 'basePos' => 20, 'baseClicks' => 2, 'baseImpr' => 40],
            ['keyword' => 'maintenance site web', 'relevance' => 'medium', 'url' => 'https://alre-web.bzh/services', 'basePos' => 30, 'baseClicks' => 1, 'baseImpr' => 35],
            ['keyword' => 'hébergement site web', 'relevance' => 'medium', 'url' => 'https://alre-web.bzh/services', 'basePos' => 45, 'baseClicks' => 0, 'baseImpr' => 25],
            ['keyword' => 'développeur web lorient', 'relevance' => 'medium', 'url' => 'https://alre-web.bzh/developpeur-web-lorient', 'basePos' => 12, 'baseClicks' => 3, 'baseImpr' => 45],
            ['keyword' => 'agence web vannes', 'relevance' => 'medium', 'url' => 'https://alre-web.bzh/', 'basePos' => 15, 'baseClicks' => 3, 'baseImpr' => 55],
            ['keyword' => 'site internet professionnel', 'relevance' => 'medium', 'url' => 'https://alre-web.bzh/services', 'basePos' => 38, 'baseClicks' => 1, 'baseImpr' => 70],
            ['keyword' => 'création site wordpress alternative', 'relevance' => 'medium', 'url' => 'https://alre-web.bzh/', 'basePos' => 28, 'baseClicks' => 1, 'baseImpr' => 20],
            ['keyword' => 'développeur web quimper', 'relevance' => 'medium', 'url' => 'https://alre-web.bzh/', 'basePos' => 22, 'baseClicks' => 2, 'baseImpr' => 30],
            ['keyword' => 'site internet artisan', 'relevance' => 'medium', 'url' => 'https://alre-web.bzh/services', 'basePos' => 32, 'baseClicks' => 1, 'baseImpr' => 40],

            // LOW RELEVANCE (10)
            ['keyword' => 'comment créer un site internet', 'relevance' => 'low', 'url' => 'https://alre-web.bzh/', 'basePos' => 55, 'baseClicks' => 0, 'baseImpr' => 25],
            ['keyword' => 'meilleur cms 2026', 'relevance' => 'low', 'url' => 'https://alre-web.bzh/', 'basePos' => 65, 'baseClicks' => 0, 'baseImpr' => 15],
            ['keyword' => 'symfony vs laravel', 'relevance' => 'low', 'url' => 'https://alre-web.bzh/', 'basePos' => 42, 'baseClicks' => 1, 'baseImpr' => 20],
            ['keyword' => 'prix site internet freelance', 'relevance' => 'low', 'url' => 'https://alre-web.bzh/tarifs', 'basePos' => 30, 'baseClicks' => 1, 'baseImpr' => 30],
            ['keyword' => 'développeur web salaire', 'relevance' => 'low', 'url' => 'https://alre-web.bzh/', 'basePos' => 70, 'baseClicks' => 0, 'baseImpr' => 10],
            ['keyword' => 'formation développeur web', 'relevance' => 'low', 'url' => 'https://alre-web.bzh/', 'basePos' => 60, 'baseClicks' => 0, 'baseImpr' => 12],
            ['keyword' => 'devis site internet', 'relevance' => 'low', 'url' => 'https://alre-web.bzh/contact', 'basePos' => 35, 'baseClicks' => 1, 'baseImpr' => 25],
            ['keyword' => 'site internet gratuit', 'relevance' => 'low', 'url' => 'https://alre-web.bzh/', 'basePos' => 75, 'baseClicks' => 0, 'baseImpr' => 8],
            ['keyword' => 'créer site internet entreprise', 'relevance' => 'low', 'url' => 'https://alre-web.bzh/services', 'basePos' => 48, 'baseClicks' => 0, 'baseImpr' => 18],
            ['keyword' => 'référencement google', 'relevance' => 'low', 'url' => 'https://alre-web.bzh/services', 'basePos' => 50, 'baseClicks' => 1, 'baseImpr' => 30],
        ];

        $startDate = new \DateTimeImmutable('2026-01-05');

        foreach ($keywordsData as $kData) {
            $keyword = new SeoKeyword();
            $keyword->setKeyword($kData['keyword']);
            $keyword->setTargetUrl($kData['url']);
            $keyword->setIsActive(true);
            $keyword->setSource(SeoKeyword::SOURCE_AUTO_GSC);
            $keyword->setRelevanceLevel($kData['relevance']);
            $keyword->setLastSeenInGsc(new \DateTimeImmutable('2026-02-08'));
            $keyword->setLastSyncAt(new \DateTimeImmutable('2026-02-08'));
            $keyword->setCreatedAt(new \DateTimeImmutable('2025-12-01'));

            $manager->persist($keyword);

            // Generate 30 days of positions with slight positive trend
            for ($day = 0; $day < 35; $day++) {
                $date = $startDate->modify("+{$day} days");
                $position = new SeoPosition();
                $position->setKeyword($keyword);
                $position->setDate($date);

                // Position improves slightly over time (lower = better)
                $trendFactor = 1 - ($day / 35) * 0.15; // 15% improvement over 35 days
                $dailyVariation = (mt_rand(-15, 10) / 100); // -15% to +10% daily noise
                $pos = max(1, $kData['basePos'] * $trendFactor * (1 + $dailyVariation));
                $position->setPosition(round($pos, 1));

                // Clicks and impressions with realistic variation
                $weekendFactor = (int)$date->format('N') >= 6 ? 0.5 : 1.0;
                $trendGrowth = 1 + ($day / 35) * 0.2; // 20% growth over period

                $impr = max(0, (int)round($kData['baseImpr'] * $trendGrowth * $weekendFactor * (1 + mt_rand(-20, 20) / 100)));
                $maxClicks = max(0, (int)round($kData['baseClicks'] * $trendGrowth * $weekendFactor));
                $clicks = max(0, min($impr, $maxClicks + mt_rand(-1, 2)));

                $position->setClicks($clicks);
                $position->setImpressions($impr);

                $manager->persist($position);
            }
        }
    }

    // =========================================================================
    // SEO DAILY TOTALS
    // =========================================================================

    private function createSeoDailyTotals(ObjectManager $manager): void
    {
        $startDate = new \DateTimeImmutable('2026-01-05');

        for ($day = 0; $day < 35; $day++) {
            $date = $startDate->modify("+{$day} days");
            $total = new SeoDailyTotal();
            $total->setDate($date);

            // Trend: growing from ~25 clicks / ~1200 impressions to ~45 clicks / ~2100 impressions
            $progress = $day / 34;
            $weekendFactor = (int)$date->format('N') >= 6 ? 0.55 : 1.0;

            $baseClicks = 25 + ($progress * 20); // 25 -> 45
            $baseImpressions = 1200 + ($progress * 900); // 1200 -> 2100
            $basePosition = 28 - ($progress * 6); // 28 -> 22

            $clicks = max(5, (int)round($baseClicks * $weekendFactor * (1 + mt_rand(-18, 18) / 100)));
            $impressions = max(200, (int)round($baseImpressions * $weekendFactor * (1 + mt_rand(-15, 15) / 100)));
            $position = round($basePosition * (1 + mt_rand(-8, 8) / 100), 1);

            $total->setClicks($clicks);
            $total->setImpressions($impressions);
            $total->setPosition($position);

            $manager->persist($total);
        }
    }

    // =========================================================================
    // GOOGLE REVIEWS
    // =========================================================================

    private function createGoogleReviews(ObjectManager $manager): void
    {
        $reviews = [
            [
                'googleReviewId' => 'gr_alreweb_001',
                'authorName' => 'Sophie M.',
                'rating' => 5,
                'comment' => 'Fabrice a créé notre site TechStart avec un professionnalisme remarquable. Il a parfaitement compris nos besoins et livré un résultat au-delà de nos attentes. Je recommande vivement !',
                'reviewDate' => '2025-10-15',
            ],
            [
                'googleReviewId' => 'gr_alreweb_002',
                'authorName' => 'Marie D.',
                'rating' => 5,
                'comment' => 'Excellent travail pour notre boutique en ligne BioBoutique. Le site est magnifique, rapide et nos clients adorent. Le suivi après livraison est top aussi.',
                'reviewDate' => '2025-11-02',
            ],
            [
                'googleReviewId' => 'gr_alreweb_003',
                'authorName' => 'Thomas L.',
                'rating' => 4,
                'comment' => 'Très bon travail sur notre site vitrine Architect Studio. Quelques allers-retours nécessaires sur le design mais le résultat final est impeccable. Bon rapport qualité/prix.',
                'reviewDate' => '2025-11-20',
            ],
            [
                'googleReviewId' => 'gr_alreweb_004',
                'authorName' => 'Julie B.',
                'rating' => 5,
                'comment' => 'La plateforme de cours en ligne Fitness Plus fonctionne parfaitement. Fabrice a su nous proposer une solution évolutive et performante. Un vrai partenaire technique !',
                'reviewDate' => '2025-12-05',
            ],
            [
                'googleReviewId' => 'gr_alreweb_005',
                'authorName' => 'Laurent P.',
                'rating' => 5,
                'comment' => 'Un développeur web sérieux et compétent. Notre portail immobilier est performant et nos clients trouvent les biens beaucoup plus facilement. Merci Fabrice !',
                'reviewDate' => '2026-01-08',
            ],
            [
                'googleReviewId' => 'gr_alreweb_006',
                'authorName' => 'Marc F.',
                'rating' => 5,
                'comment' => 'Très satisfait du site créé pour notre association. Fabrice est à l\'écoute, réactif et force de proposition. L\'espace membres est exactement ce qu\'il nous fallait.',
                'reviewDate' => '2026-01-28',
            ],
        ];

        foreach ($reviews as $data) {
            $review = new GoogleReview();
            $review->setGoogleReviewId($data['googleReviewId']);
            $review->setAuthorName($data['authorName']);
            $review->setRating($data['rating']);
            $review->setComment($data['comment']);
            $review->setReviewDate(new \DateTimeImmutable($data['reviewDate']));
            $review->setIsApproved(true);
            $review->setCreatedAt(new \DateTimeImmutable($data['reviewDate']));
            $review->setUpdatedAt(new \DateTimeImmutable($data['reviewDate']));

            $manager->persist($review);
        }
    }

    // =========================================================================
    // CLIENT SEO DATA (Fitness Plus)
    // =========================================================================

    private function createClientSeoData(ObjectManager $manager): void
    {
        $client = $this->getReference('client-4', Client::class); // Fitness Plus

        $clientSite = new ClientSite();
        $clientSite->setClient($client);
        $clientSite->setName('Fitness Plus');
        $clientSite->setUrl('https://fitnessplus.com');
        $clientSite->setIsActive(true);
        $manager->persist($clientSite);

        // Client SEO Keywords (20)
        $clientKeywords = [
            ['keyword' => 'cours de fitness en ligne', 'basePos' => 8, 'baseClicks' => 12, 'baseImpr' => 150],
            ['keyword' => 'coaching sportif à distance', 'basePos' => 12, 'baseClicks' => 8, 'baseImpr' => 100],
            ['keyword' => 'fitness plus avis', 'basePos' => 2, 'baseClicks' => 20, 'baseImpr' => 30],
            ['keyword' => 'cours yoga en ligne', 'basePos' => 18, 'baseClicks' => 5, 'baseImpr' => 120],
            ['keyword' => 'abonnement fitness en ligne', 'basePos' => 15, 'baseClicks' => 6, 'baseImpr' => 80],
            ['keyword' => 'programme musculation maison', 'basePos' => 25, 'baseClicks' => 3, 'baseImpr' => 200],
            ['keyword' => 'cours pilates streaming', 'basePos' => 10, 'baseClicks' => 7, 'baseImpr' => 60],
            ['keyword' => 'fitness plus tarifs', 'basePos' => 3, 'baseClicks' => 15, 'baseImpr' => 25],
            ['keyword' => 'coach sportif en ligne', 'basePos' => 20, 'baseClicks' => 4, 'baseImpr' => 90],
            ['keyword' => 'sport à la maison', 'basePos' => 35, 'baseClicks' => 2, 'baseImpr' => 180],
            ['keyword' => 'cours de zumba en ligne', 'basePos' => 14, 'baseClicks' => 5, 'baseImpr' => 55],
            ['keyword' => 'fitness plus planning', 'basePos' => 1, 'baseClicks' => 18, 'baseImpr' => 22],
            ['keyword' => 'salle de sport lille', 'basePos' => 28, 'baseClicks' => 2, 'baseImpr' => 150],
            ['keyword' => 'cours collectifs en ligne', 'basePos' => 16, 'baseClicks' => 4, 'baseImpr' => 70],
            ['keyword' => 'programme remise en forme', 'basePos' => 22, 'baseClicks' => 3, 'baseImpr' => 95],
            ['keyword' => 'fitness streaming live', 'basePos' => 9, 'baseClicks' => 8, 'baseImpr' => 45],
            ['keyword' => 'cours cardio en ligne', 'basePos' => 13, 'baseClicks' => 6, 'baseImpr' => 75],
            ['keyword' => 'application fitness', 'basePos' => 40, 'baseClicks' => 1, 'baseImpr' => 130],
            ['keyword' => 'coaching personnalisé en ligne', 'basePos' => 11, 'baseClicks' => 7, 'baseImpr' => 50],
            ['keyword' => 'fitness plus inscription', 'basePos' => 2, 'baseClicks' => 12, 'baseImpr' => 18],
        ];

        $startDate = new \DateTimeImmutable('2026-01-10');

        foreach ($clientKeywords as $ckData) {
            $ck = new ClientSeoKeyword();
            $ck->setClientSite($clientSite);
            $ck->setKeyword($ckData['keyword']);
            $ck->setIsActive(true);
            $manager->persist($ck);

            // 30 days of positions
            for ($day = 0; $day < 30; $day++) {
                $date = $startDate->modify("+{$day} days");
                $pos = new ClientSeoPosition();
                $pos->setClientSeoKeyword($ck);
                $pos->setDate($date);

                $trendFactor = 1 - ($day / 30) * 0.12;
                $dailyVariation = mt_rand(-12, 8) / 100;
                $posVal = max(1, $ckData['basePos'] * $trendFactor * (1 + $dailyVariation));
                $pos->setPosition(round($posVal, 1));

                $weekendFactor = (int)$date->format('N') >= 6 ? 0.5 : 1.0;
                $trendGrowth = 1 + ($day / 30) * 0.15;

                $impr = max(0, (int)round($ckData['baseImpr'] * $trendGrowth * $weekendFactor * (1 + mt_rand(-20, 20) / 100)));
                $clicks = max(0, min($impr, (int)round($ckData['baseClicks'] * $trendGrowth * $weekendFactor) + mt_rand(-1, 2)));

                $pos->setClicks($clicks);
                $pos->setImpressions($impr);
                $manager->persist($pos);
            }
        }

        // Client SEO Daily Totals (30 days)
        for ($day = 0; $day < 30; $day++) {
            $date = $startDate->modify("+{$day} days");
            $dt = new ClientSeoDailyTotal();
            $dt->setClientSite($clientSite);
            $dt->setDate($date);

            $progress = $day / 29;
            $weekendFactor = (int)$date->format('N') >= 6 ? 0.55 : 1.0;

            $baseClicks = 80 + ($progress * 40);
            $baseImpressions = 1500 + ($progress * 600);
            $basePosition = 18 - ($progress * 4);

            $dt->setClicks(max(10, (int)round($baseClicks * $weekendFactor * (1 + mt_rand(-15, 15) / 100))));
            $dt->setImpressions(max(300, (int)round($baseImpressions * $weekendFactor * (1 + mt_rand(-12, 12) / 100))));
            $dt->setPosition(round($basePosition * (1 + mt_rand(-6, 6) / 100), 1));

            $manager->persist($dt);
        }

        // Client SEO Pages (10 top pages)
        $pages = [
            ['url' => 'https://fitnessplus.com/', 'clicks' => 350, 'impressions' => 2800, 'position' => 8.5],
            ['url' => 'https://fitnessplus.com/cours', 'clicks' => 280, 'impressions' => 2100, 'position' => 10.2],
            ['url' => 'https://fitnessplus.com/tarifs', 'clicks' => 220, 'impressions' => 1500, 'position' => 6.3],
            ['url' => 'https://fitnessplus.com/planning', 'clicks' => 180, 'impressions' => 800, 'position' => 3.1],
            ['url' => 'https://fitnessplus.com/coach/julie', 'clicks' => 95, 'impressions' => 600, 'position' => 12.4],
            ['url' => 'https://fitnessplus.com/blog/remise-en-forme', 'clicks' => 75, 'impressions' => 1200, 'position' => 22.1],
            ['url' => 'https://fitnessplus.com/inscription', 'clicks' => 65, 'impressions' => 400, 'position' => 5.8],
            ['url' => 'https://fitnessplus.com/cours/yoga', 'clicks' => 55, 'impressions' => 900, 'position' => 15.7],
            ['url' => 'https://fitnessplus.com/cours/cardio', 'clicks' => 45, 'impressions' => 700, 'position' => 13.2],
            ['url' => 'https://fitnessplus.com/blog/exercices-maison', 'clicks' => 40, 'impressions' => 1100, 'position' => 28.5],
        ];

        $pageDate = new \DateTimeImmutable('2026-02-08');
        foreach ($pages as $pData) {
            $page = new ClientSeoPage();
            $page->setClientSite($clientSite);
            $page->setUrl($pData['url']);
            $page->setClicks($pData['clicks']);
            $page->setImpressions($pData['impressions']);
            $page->setCtr($pData['impressions'] > 0 ? round(($pData['clicks'] / $pData['impressions']) * 100, 2) : 0);
            $page->setPosition($pData['position']);
            $page->setDate($pageDate);
            $manager->persist($page);
        }

    }

    // =========================================================================
    // CITIES (for local pages)
    // =========================================================================

    private function createCities(ObjectManager $manager): void
    {
        // Skip if cities already exist
        $existingCount = $manager->getRepository(City::class)->count([]);
        if ($existingCount > 0) {
            return;
        }

        $cities = [
            [
                'name' => 'Vannes',
                'slug' => 'vannes',
                'region' => 'Morbihan, Bretagne',
                'description' => 'Vannes, préfecture du Morbihan, est une ville dynamique au cœur du Golfe du Morbihan. Avec son centre médiéval, ses remparts et son port de plaisance, Vannes attire entrepreneurs et artisans. Alré Web accompagne les entreprises vannetaises dans leur transformation digitale avec des sites web performants et un référencement local optimisé.',
                'nearby' => ['Auray', 'Séné', 'Arradon', 'Theix-Noyalo'],
                'keywords' => ['développeur web vannes', 'création site internet vannes', 'agence web vannes'],
                'sortOrder' => 1,
            ],
            [
                'name' => 'Lorient',
                'slug' => 'lorient',
                'region' => 'Morbihan, Bretagne',
                'description' => 'Lorient, ville portuaire du Morbihan, est un bassin économique majeur de la Bretagne Sud. Connue pour son festival interceltique et sa base sous-marine, Lorient accueille de nombreuses entreprises innovantes. Alré Web propose aux professionnels lorientais des solutions web sur mesure pour développer leur visibilité en ligne.',
                'nearby' => ['Lanester', 'Hennebont', 'Ploemeur', 'Quéven'],
                'keywords' => ['développeur web lorient', 'création site internet lorient', 'agence web lorient'],
                'sortOrder' => 2,
            ],
            [
                'name' => 'Auray',
                'slug' => 'auray',
                'region' => 'Morbihan, Bretagne',
                'description' => 'Auray, charmante cité de caractère du Morbihan, est le point de départ vers la presqu\'île de Quiberon et les îles du Golfe. Son port de Saint-Goustan et son marché animé en font un pôle d\'attraction touristique et commercial. Alré Web aide les commerçants et entrepreneurs d\'Auray à rayonner sur le web.',
                'nearby' => ['Vannes', 'Carnac', 'Brech', 'Pluneret'],
                'keywords' => ['développeur web auray', 'création site web auray', 'site internet auray'],
                'sortOrder' => 3,
            ],
            [
                'name' => 'Quiberon',
                'slug' => 'quiberon',
                'region' => 'Morbihan, Bretagne',
                'description' => 'Quiberon, presqu\'île emblématique de la côte morbihannaise, est une destination touristique prisée. Entre Côte Sauvage et plages familiales, Quiberon accueille hôtels, restaurants et commerces qui ont besoin d\'une présence web forte. Alré Web conçoit des sites internet adaptés au secteur touristique quiberonnais.',
                'nearby' => ['Carnac', 'Plouharnel', 'Saint-Pierre-Quiberon', 'Auray'],
                'keywords' => ['site internet quiberon', 'création site web quiberon', 'développeur web quiberon'],
                'sortOrder' => 4,
            ],
            [
                'name' => 'Carnac',
                'slug' => 'carnac',
                'region' => 'Morbihan, Bretagne',
                'description' => 'Carnac, célèbre pour ses alignements mégalithiques, est une station balnéaire réputée du Morbihan. La ville allie patrimoine historique et dynamisme touristique. Alré Web accompagne les professionnels de Carnac — campings, hôtels, restaurants, commerces — dans la création de sites web attractifs et bien référencés.',
                'nearby' => ['Quiberon', 'Auray', 'La Trinité-sur-Mer', 'Plouharnel'],
                'keywords' => ['site internet carnac', 'création site web carnac', 'développeur web carnac'],
                'sortOrder' => 5,
            ],
        ];

        foreach ($cities as $data) {
            $city = new City();
            $city->setName($data['name']);
            $city->setSlug($data['slug']);
            $city->setRegion($data['region']);
            $city->setDescription($data['description']);
            $city->setNearby($data['nearby']);
            $city->setKeywords($data['keywords']);
            $city->setSortOrder($data['sortOrder']);
            $city->setIsActive(true);

            $manager->persist($city);
        }
    }
}
