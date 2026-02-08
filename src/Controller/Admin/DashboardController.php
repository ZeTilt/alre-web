<?php

namespace App\Controller\Admin;

use App\Entity\BlockedIp;
use App\Entity\City;
use App\Entity\Client;
use App\Entity\Company;
use App\Entity\ContactMessage;
use App\Entity\Devis;
use App\Entity\Event;
use App\Entity\EventType;
use App\Entity\Facture;
use App\Entity\GoogleReview;
use App\Entity\Prospect;
use App\Entity\ProspectFollowUp;
use App\Entity\ProspectInteraction;
use App\Entity\SecurityLog;
use App\Entity\SeoKeyword;
use App\Entity\User;
use App\Entity\Project;
use App\Entity\Partner;
use App\Entity\Testimonial;
use App\Repository\CompanyRepository;
use App\Repository\DevisRepository;
use App\Repository\EventRepository;
use App\Repository\EventTypeRepository;
use App\Repository\FactureRepository;
use App\Repository\ProspectRepository;
use App\Repository\ProspectFollowUpRepository;
use App\Repository\GoogleReviewRepository;
use App\Repository\SeoDailyTotalRepository;
use App\Repository\SeoKeywordRepository;
use App\Repository\SeoPositionRepository;
use App\Service\DashboardPeriodService;
use App\Service\GoogleOAuthService;
use App\Service\GooglePlacesService;
use App\Service\ProspectionEmailService;
use App\Service\ReviewSyncService;
use App\Service\SeoDataImportService;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class DashboardController extends AbstractDashboardController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ParameterBagInterface $params,
        private GoogleOAuthService $googleOAuthService,
        private SeoDataImportService $seoDataImportService,
        private GooglePlacesService $googlePlacesService,
        private ReviewSyncService $reviewSyncService,
    ) {
    }

    #[Route('/saeiblauhjc', name: 'admin')]
    public function index(): Response
    {
        // Rediriger vers le tableau de bord business
        return $this->redirectToRoute('admin_business_dashboard');
    }

    #[Route('/saeiblauhjc/business-dashboard', name: 'admin_business_dashboard')]
    public function businessDashboard(
        Request $request,
        CompanyRepository $companyRepository,
        FactureRepository $factureRepository,
        DevisRepository $devisRepository,
        DashboardPeriodService $periodService,
        SeoKeywordRepository $seoKeywordRepository,
        SeoPositionRepository $seoPositionRepository,
        SeoDailyTotalRepository $seoDailyTotalRepository,
        GoogleReviewRepository $googleReviewRepository
    ): Response {
        // Récupérer les informations de l'entreprise
        $company = $companyRepository->findOneBy([]);

        // Gestion de la période sélectionnée
        $periodType = $request->query->get('period');
        $customStartStr = $request->query->get('startDate');
        $customEndStr = $request->query->get('endDate');

        // Si période dans l'URL, sauvegarder en session
        if ($periodType) {
            $periodService->savePeriodToSession($periodType, $customStartStr, $customEndStr);
        } else {
            // Sinon récupérer depuis la session
            $sessionData = $periodService->getPeriodFromSession();
            $periodType = $sessionData['type'];
            $customStartStr = $sessionData['customStart'];
            $customEndStr = $sessionData['customEnd'];
        }

        // Convertir les dates custom si fournies
        $customStart = $customStartStr ? new \DateTimeImmutable($customStartStr . ' 00:00:00') : null;
        $customEnd = $customEndStr ? new \DateTimeImmutable($customEndStr . ' 23:59:59') : null;

        // Obtenir les dates de la période
        $periodDates = $periodService->getPeriodDates($periodType, $customStart, $customEnd);
        $startOfPeriod = $periodDates['start'];
        $endOfPeriod = $periodDates['end'];
        $periodLabel = $periodDates['label'];
        $periodYear = $periodDates['year'];

        // Période de comparaison (N-1)
        $comparisonDates = $periodService->getComparisonPeriodDates($startOfPeriod, $endOfPeriod);
        $startOfComparison = $comparisonDates['start'];
        $endOfComparison = $comparisonDates['end'];

        // Labels des mois pour les graphiques
        $monthLabels = $periodService->getMonthLabelsForPeriod($startOfPeriod, $endOfPeriod);
        $monthKeys = $periodService->getMonthKeysForPeriod($startOfPeriod, $endOfPeriod);

        // Année fiscale en cours (pour les objectifs et plafond)
        $year = $company?->getAnneeFiscaleEnCours() ?? (int) date('Y');

        // Période en cours (mois et année) - pour les KPI du mois
        $now = new \DateTimeImmutable();
        $startOfMonth = $now->modify('first day of this month')->setTime(0, 0, 0);
        $endOfMonth = $now->modify('last day of this month')->setTime(23, 59, 59);
        $startOfYear = new \DateTimeImmutable($year . '-01-01 00:00:00');
        $endOfYear = new \DateTimeImmutable($year . '-12-31 23:59:59');

        // Période précédente pour comparaison du mois
        $startOfPreviousMonth = $now->modify('first day of last month')->setTime(0, 0, 0);
        $endOfPreviousMonth = $now->modify('last day of last month')->setTime(23, 59, 59);

        // ===== FINANCES =====

        // CA encaissé
        $caMoisEncaisse = $factureRepository->getRevenueByPeriod($startOfMonth, $endOfMonth, true);
        $caMoisPrecedentEncaisse = $factureRepository->getRevenueByPeriod($startOfPreviousMonth, $endOfPreviousMonth, true);
        $caAnneeEncaisse = $factureRepository->getRevenueByPeriod($startOfYear, $endOfYear, true);

        // Variation mois vs mois précédent
        $variationMoisPourcent = 0;
        if ($caMoisPrecedentEncaisse > 0) {
            $variationMoisPourcent = round((($caMoisEncaisse - $caMoisPrecedentEncaisse) / $caMoisPrecedentEncaisse) * 100, 1);
        }

        // Progression vs objectifs (MoneyField stocke en centimes, donc diviser par 100)
        $objectifMensuel = $company?->getObjectifCaMensuel() ? ((float) $company->getObjectifCaMensuel() / 100) : 0;
        $objectifAnnuel = $company?->getObjectifCaAnnuel() ? ((float) $company->getObjectifCaAnnuel() / 100) : 0;
        $progressionObjectifMensuel = $objectifMensuel > 0 ? round(($caMoisEncaisse / $objectifMensuel) * 100, 1) : 0;
        $progressionObjectifAnnuel = $objectifAnnuel > 0 ? round(($caAnneeEncaisse / $objectifAnnuel) * 100, 1) : 0;

        // Progression vs plafond auto-entrepreneur (MoneyField stocke en centimes, donc diviser par 100)
        $plafondCa = $company?->getPlafondCaAnnuel() ? ((float) $company->getPlafondCaAnnuel() / 100) : 0;
        $progressionPlafond = $plafondCa > 0 ? round(($caAnneeEncaisse / $plafondCa) * 100, 1) : 0;

        // Cotisations URSSAF estimées
        $tauxCotisations = $company?->getTauxCotisationsUrssaf() ? (float) $company->getTauxCotisationsUrssaf() : 0;
        $cotisationsEstimees = round($caAnneeEncaisse * ($tauxCotisations / 100), 2);
        $cotisationsMois = round($caMoisEncaisse * ($tauxCotisations / 100), 2);

        // En attente d'encaissement
        $caEnAttente = $factureRepository->getPendingRevenue();

        // ===== FACTURES =====

        $facturesEnRetard = $factureRepository->findOverdueFactures();
        $nbFacturesEnRetard = count($facturesEnRetard);
        $montantEnRetard = array_reduce($facturesEnRetard, fn($sum, $f) => $sum + (float) $f->getTotalTtc(), 0);

        $delaiMoyenPaiement = $factureRepository->getAveragePaymentDelay();

        // ===== DEVIS =====

        $devisPending = $devisRepository->findPendingDevis();
        $nbDevisPending = count($devisPending);
        $montantDevisPending = $devisRepository->getPendingQuotesTotal();

        $tauxConversion = $devisRepository->getConversionRate($startOfYear, $endOfYear);

        $devisARelancer = $devisRepository->getQuotesToFollowUp(7);
        $nbDevisARelancer = count($devisARelancer);

        // ===== DÉPENSES (fonctionnalité désactivée) =====

        $depensesMois = 0;
        $depensesAnnee = 0;

        // Bénéfice net (CA - dépenses - cotisations URSSAF)
        $beneficeNetMois = $caMoisEncaisse - $depensesMois - $cotisationsMois;
        $beneficeNetAnnee = $caAnneeEncaisse - $depensesAnnee - $cotisationsEstimees;

        // Marge bénéficiaire (en pourcentage)
        $margeNetMois = $caMoisEncaisse > 0 ? round(($beneficeNetMois / $caMoisEncaisse) * 100, 1) : 0;
        $margeNetAnnee = $caAnneeEncaisse > 0 ? round(($beneficeNetAnnee / $caAnneeEncaisse) * 100, 1) : 0;

        // ===== DONNÉES POUR GRAPHIQUES (basé sur la période sélectionnée) =====

        // CA mensuel encaissé (pour graphique ligne) - période sélectionnée
        $caParMoisData = $factureRepository->getMonthlyPaidRevenueForPeriod($startOfPeriod, $endOfPeriod);

        // Convertir les données en tableau indexé par ordre des mois
        $caParMois = [];
        foreach ($monthKeys as $key) {
            $caParMois[] = $caParMoisData[$key] ?? 0;
        }

        // CA période de comparaison N-1
        $caParMoisComparisonData = $factureRepository->getMonthlyPaidRevenueForPeriod($startOfComparison, $endOfComparison);
        $caParMoisComparison = [];
        $comparisonKeys = $periodService->getMonthKeysForPeriod($startOfComparison, $endOfComparison);
        foreach ($comparisonKeys as $key) {
            $caParMoisComparison[] = $caParMoisComparisonData[$key] ?? 0;
        }

        // Total CA période vs comparaison
        $caPeriode = $factureRepository->getRevenueByPeriod($startOfPeriod, $endOfPeriod, true);
        $caPeriodeComparison = $factureRepository->getRevenueByPeriod($startOfComparison, $endOfComparison, true);
        $variationPeriodePourcent = 0;
        if ($caPeriodeComparison > 0) {
            $variationPeriodePourcent = round((($caPeriode - $caPeriodeComparison) / $caPeriodeComparison) * 100, 1);
        }

        // Top clients (pour camembert) - basé sur la période sélectionnée
        $topClients = $factureRepository->getRevenueByClientForPeriod($startOfPeriod, $endOfPeriod);
        $topClients = array_slice($topClients, 0, 5); // Top 5 clients

        // Dépenses mensuelles (fonctionnalité désactivée)
        $depensesParMois = [];

        // Cotisations URSSAF mensuelles (pour graphique)
        $cotisationsParMois = [];
        foreach ($caParMois as $ca) {
            $cotisationsParMois[] = round($ca * ($tauxCotisations / 100), 2);
        }

        // ===== PRÉVISIONS =====

        // Calculer la moyenne des 3 derniers mois pour projection
        $currentMonth = (int) $now->format('m');
        $lastThreeMonths = [];
        for ($i = 2; $i >= 0; $i--) {
            $month = $currentMonth - $i;
            if ($month > 0 && $month <= 12) {
                $lastThreeMonths[] = $caParMois[$month] ?? 0;
            }
        }

        $avgLastThreeMonths = count($lastThreeMonths) > 0 ? array_sum($lastThreeMonths) / count($lastThreeMonths) : 0;

        // Projections pour les 3 prochains mois
        $projections = [];
        for ($i = 1; $i <= 3; $i++) {
            $futureMonth = $currentMonth + $i;
            if ($futureMonth <= 12) {
                $monthName = ['', 'Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Jun', 'Jul', 'Aoû', 'Sep', 'Oct', 'Nov', 'Déc'][$futureMonth];
                $projections[] = [
                    'month' => $monthName,
                    'amount' => $avgLastThreeMonths
                ];
            }
        }

        // Calculer la tendance (évolution mois actuel vs moyenne 3 derniers)
        $tendance = 0;
        if ($avgLastThreeMonths > 0 && $caMoisEncaisse > 0) {
            $tendance = round((($caMoisEncaisse - $avgLastThreeMonths) / $avgLastThreeMonths) * 100, 1);
        }

        return $this->render('admin/dashboard/index.html.twig', [
            'company' => $company,
            'year' => $year,

            // Sélecteur de période
            'periodType' => $periodType,
            'periodLabel' => $periodLabel,
            'periodChoices' => DashboardPeriodService::getPeriodChoices(),
            'customStartDate' => $customStartStr,
            'customEndDate' => $customEndStr,
            'startOfPeriod' => $startOfPeriod,
            'endOfPeriod' => $endOfPeriod,

            // CA période sélectionnée
            'caPeriode' => $caPeriode,
            'caPeriodeComparison' => $caPeriodeComparison,
            'variationPeriodePourcent' => $variationPeriodePourcent,

            // CA mois (KPI fixes)
            'caMoisEncaisse' => $caMoisEncaisse,
            'caAnneeEncaisse' => $caAnneeEncaisse,
            'variationMoisPourcent' => $variationMoisPourcent,

            // Objectifs
            'objectifMensuel' => $objectifMensuel,
            'objectifAnnuel' => $objectifAnnuel,
            'progressionObjectifMensuel' => $progressionObjectifMensuel,
            'progressionObjectifAnnuel' => $progressionObjectifAnnuel,

            // Plafond
            'plafondCa' => $plafondCa,
            'progressionPlafond' => $progressionPlafond,

            // Cotisations
            'cotisationsEstimees' => $cotisationsEstimees,
            'tauxCotisations' => $tauxCotisations,

            // En attente
            'caEnAttente' => $caEnAttente,

            // Factures
            'nbFacturesEnRetard' => $nbFacturesEnRetard,
            'montantEnRetard' => $montantEnRetard,
            'facturesEnRetard' => array_slice($facturesEnRetard, 0, 5), // Top 5
            'delaiMoyenPaiement' => $delaiMoyenPaiement,

            // Devis
            'nbDevisPending' => $nbDevisPending,
            'montantDevisPending' => $montantDevisPending,
            'tauxConversion' => $tauxConversion,
            'nbDevisARelancer' => $nbDevisARelancer,
            'devisARelancer' => array_slice($devisARelancer, 0, 5), // Top 5

            // Dépenses
            'depensesMois' => $depensesMois,
            'depensesAnnee' => $depensesAnnee,
            'beneficeNetMois' => $beneficeNetMois,
            'beneficeNetAnnee' => $beneficeNetAnnee,
            'margeNetMois' => $margeNetMois,
            'margeNetAnnee' => $margeNetAnnee,

            // Données graphiques
            'caParMois' => $caParMois,
            'caParMoisComparison' => $caParMoisComparison,
            'monthLabels' => $monthLabels,
            'topClients' => $topClients,
            'depensesParMois' => $depensesParMois,
            'cotisationsParMois' => $cotisationsParMois,

            // Prévisions
            'projections' => $projections,
            'tendance' => $tendance,

            // Mode démo
            'demoMode' => ($_ENV['APP_DEMO_MODE'] ?? '0') === '1',

            // Google OAuth
            'googleOAuthConfigured' => $this->googleOAuthService->isConfigured(),
            'googleOAuthConnected' => $this->googleOAuthService->isConnected(),
            'googleOAuthToken' => $this->googleOAuthService->getValidToken(),

            // SEO Sync
            'lastSeoSyncAt' => $this->seoDataImportService->getLastSyncDate(),

            // SEO Keywords with positions (Top 10 and Bottom 10)
            'seoKeywordsTop10' => $this->getTopSeoKeywords($seoKeywordRepository, 10),
            'seoKeywordsBottom10' => $this->getBottomSeoKeywords($seoKeywordRepository, 10),

            // SEO Position comparisons (current month vs previous month)
            'seoPositionComparisons' => $this->calculateSeoPositionComparisons(
                $seoKeywordRepository,
                $seoPositionRepository
            ),

            // SEO Chart data (last 30 days)
            'seoChartData' => $this->prepareSeoChartData($seoDailyTotalRepository),

            // SEO Performance categories
            'seoPerformanceData' => $this->categorizeSeoKeywords(
                $seoKeywordRepository->findAllWithLatestPosition()
            ),

            // SEO Keywords Chart (evolution over 30 days)
            'seoKeywordsChartData' => $this->prepareSeoKeywordsChartData($seoKeywordRepository, $seoDailyTotalRepository),

            // Google Reviews
            'googlePlacesConfigured' => $this->googlePlacesService->isConfigured(),
            'reviewStats' => $googleReviewRepository->getStats(),
            'reviewsDataFresh' => $this->reviewSyncService->isDataFresh(),
            'pendingReviews' => $googleReviewRepository->findPending(),
            'reviewsApiError' => $this->googlePlacesService->getLastError(),
        ]);
    }

    #[Route('/saeiblauhjc/calendar', name: 'admin_calendar')]
    public function calendar(EventRepository $eventRepository, EventTypeRepository $eventTypeRepository): Response
    {
        $upcomingEvents = $eventRepository->findUpcoming(7);
        $eventTypes = $eventTypeRepository->findAllOrdered();

        return $this->render('admin/calendar/index.html.twig', [
            'upcomingEvents' => $upcomingEvents,
            'eventTypes' => $eventTypes,
        ]);
    }

    #[Route('/saeiblauhjc/calendar/events', name: 'admin_calendar_events', methods: ['GET'])]
    public function calendarEvents(Request $request, EventRepository $eventRepository): JsonResponse
    {
        $start = new \DateTimeImmutable($request->query->get('start', 'first day of this month'));
        $end = new \DateTimeImmutable($request->query->get('end', 'last day of this month'));

        $events = $eventRepository->findByDateRange($start, $end);

        $data = [];
        foreach ($events as $event) {
            $eventType = $event->getEventType();
            $data[] = [
                'id' => $event->getId(),
                'title' => $event->getTitle(),
                'start' => $event->getStartAt()->format('c'),
                'end' => $event->getEndAt()?->format('c'),
                'allDay' => $event->isAllDay(),
                'color' => $event->getColor(),
                'extendedProps' => [
                    'typeId' => $eventType?->getId(),
                    'typeLabel' => $eventType?->getName() ?? 'Non défini',
                    'typeColor' => $eventType?->getColor() ?? '#8E8E93',
                    'description' => $event->getDescription(),
                    'location' => $event->getLocation(),
                    'clientId' => $event->getClient()?->getId(),
                    'clientName' => $event->getClient()?->getName(),
                    'formattedDate' => $this->formatEventDate($event),
                ],
            ];
        }

        return new JsonResponse($data);
    }

    #[Route('/saeiblauhjc/calendar/events/create', name: 'admin_calendar_event_create', methods: ['POST'])]
    public function createEvent(Request $request, EventTypeRepository $eventTypeRepository): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $event = new Event();
        $event->setTitle($data['title'] ?? 'Sans titre');

        // Set event type if provided
        if (!empty($data['eventTypeId'])) {
            $eventType = $eventTypeRepository->find($data['eventTypeId']);
            $event->setEventType($eventType);
        }

        $tz = new \DateTimeZone('Europe/Paris');
        $startAt = new \DateTime($data['date'] . ' ' . ($data['time'] ?? '09:00'), $tz);
        $event->setStartAt($startAt);

        if (!empty($data['time'])) {
            $endAt = (clone $startAt)->modify('+1 hour');
            $event->setEndAt($endAt);
        } else {
            $event->setAllDay(true);
        }

        $this->entityManager->persist($event);
        $this->entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'id' => $event->getId(),
            'message' => 'Événement créé'
        ]);
    }

    #[Route('/saeiblauhjc/prospection/pipeline', name: 'admin_prospection_pipeline')]
    public function prospectionPipeline(
        ProspectRepository $prospectRepository,
        ProspectFollowUpRepository $followUpRepository
    ): Response {
        $prospectsByStatus = $prospectRepository->findGroupedByStatus();
        $urgentFollowUps = $followUpRepository->findUrgent(2);

        // Stats
        $totalProspects = 0;
        foreach ($prospectsByStatus as $prospects) {
            $totalProspects += count($prospects);
        }

        $totalValue = $prospectRepository->getTotalEstimatedValue();
        $conversionRate = $prospectRepository->getConversionRate();
        $wonThisMonth = $prospectRepository->countWonThisMonth();
        $lostThisMonth = $prospectRepository->countLostThisMonth();

        return $this->render('admin/prospection/pipeline.html.twig', [
            'prospectsByStatus' => $prospectsByStatus,
            'urgentFollowUps' => $urgentFollowUps,
            'totalProspects' => $totalProspects,
            'totalValue' => $totalValue,
            'conversionRate' => $conversionRate,
            'wonThisMonth' => $wonThisMonth,
            'lostThisMonth' => $lostThisMonth,
        ]);
    }

    #[Route('/saeiblauhjc/prospection/contacts/{id}', name: 'admin_prospection_contacts', methods: ['GET'])]
    public function getProspectContacts(Prospect $prospect): JsonResponse
    {
        $contacts = [];
        foreach ($prospect->getContacts() as $contact) {
            $contacts[] = [
                'id' => $contact->getId(),
                'name' => $contact->getFirstName() . ' ' . $contact->getLastName(),
                'email' => $contact->getEmail(),
                'primary' => $contact->isPrimary(),
            ];
        }

        return new JsonResponse($contacts);
    }

    #[Route('/saeiblauhjc/prospection/update-status/{id}', name: 'admin_prospection_update_status', methods: ['POST'])]
    public function updateProspectStatus(Request $request, Prospect $prospect): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $newStatus = $data['status'] ?? null;

        $validStatuses = [
            Prospect::STATUS_IDENTIFIED,
            Prospect::STATUS_CONTACTED,
            Prospect::STATUS_IN_DISCUSSION,
            Prospect::STATUS_QUOTE_SENT,
        ];

        if (!$newStatus || !in_array($newStatus, $validStatuses)) {
            return new JsonResponse(['error' => 'Statut invalide'], 400);
        }

        $prospect->setStatus($newStatus);
        $prospect->setLastContactAt(new \DateTimeImmutable());
        $this->entityManager->flush();

        return new JsonResponse(['success' => true, 'status' => $newStatus]);
    }

    #[Route('/saeiblauhjc/prospection/send-email/{id}', name: 'admin_prospection_send_email')]
    public function sendProspectionEmail(
        Request $request,
        Prospect $prospect,
        ProspectionEmailService $emailService
    ): Response {
        if ($request->isMethod('POST')) {
            $toEmail = $request->request->get('email');
            $subject = $request->request->get('subject');
            $content = $request->request->get('content');
            $contactId = $request->request->get('contact');

            $contact = null;
            if ($contactId) {
                $contact = $this->entityManager->getRepository(\App\Entity\ProspectContact::class)->find($contactId);
            }

            try {
                $emailService->sendProspectionEmail($prospect, $toEmail, $subject, $content, $contact);
                $this->addFlash('success', 'Email envoyé avec succès !');
                return $this->redirectToRoute('admin', [
                    'crudAction' => 'detail',
                    'crudControllerFqcn' => ProspectCrudController::class,
                    'entityId' => $prospect->getId()
                ]);
            } catch (\Exception $e) {
                $this->addFlash('danger', 'Erreur lors de l\'envoi : ' . $e->getMessage());
            }
        }

        return $this->render('admin/prospection/send_email.html.twig', [
            'prospect' => $prospect,
            'defaultSubject' => $emailService->getDefaultSubject($prospect),
            'defaultContent' => $emailService->getDefaultEmailContent($prospect),
        ]);
    }

    #[Route('/saeiblauhjc/dashboard/export-csv', name: 'admin_dashboard_export_csv')]
    public function exportCsv(
        CompanyRepository $companyRepository,
        FactureRepository $factureRepository
    ): Response {
        $company = $companyRepository->findOneBy([]);
        $year = $company?->getAnneeFiscaleEnCours() ?? (int) date('Y');

        // Données mensuelles
        $caParMois = $factureRepository->getMonthlyPaidRevenueForYear($year);
        $depensesParMois = []; // Fonctionnalité dépenses désactivée

        // Créer le contenu CSV
        $csv = [];
        $csv[] = ['Rapport Dashboard ' . $year . ' - ' . ($company?->getName() ?? 'Alré Web')];
        $csv[] = ['Généré le ' . (new \DateTimeImmutable())->format('d/m/Y à H:i')];
        $csv[] = [];
        $csv[] = ['Mois', 'CA Encaissé (€)', 'Dépenses (€)', 'Cotisations URSSAF (€)', 'Bénéfice Net (€)', 'Marge (%)'];

        $moisNames = ['Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'];

        // Récupérer le taux de cotisations
        $tauxCotisations = $company?->getTauxCotisationsUrssaf() ? (float) $company->getTauxCotisationsUrssaf() : 0;

        $totalCA = 0;
        $totalDepenses = 0;
        $totalCotisations = 0;

        foreach ($caParMois as $month => $ca) {
            $depenses = $depensesParMois[$month] ?? 0;
            $cotisations = round($ca * ($tauxCotisations / 100), 2);
            $benefice = $ca - $depenses - $cotisations;
            $marge = $ca > 0 ? round(($benefice / $ca) * 100, 1) : 0;

            $totalCA += $ca;
            $totalDepenses += $depenses;
            $totalCotisations += $cotisations;

            $csv[] = [
                $moisNames[$month - 1],
                number_format($ca, 2, ',', ' '),
                number_format($depenses, 2, ',', ' '),
                number_format($cotisations, 2, ',', ' '),
                number_format($benefice, 2, ',', ' '),
                $marge . '%'
            ];
        }

        $totalBenefice = $totalCA - $totalDepenses - $totalCotisations;
        $totalMarge = $totalCA > 0 ? round(($totalBenefice / $totalCA) * 100, 1) : 0;

        $csv[] = [];
        $csv[] = ['TOTAL ' . $year, number_format($totalCA, 2, ',', ' '), number_format($totalDepenses, 2, ',', ' '), number_format($totalCotisations, 2, ',', ' '), number_format($totalBenefice, 2, ',', ' '), $totalMarge . '%'];

        // Générer le contenu CSV
        $output = fopen('php://temp', 'r+');
        foreach ($csv as $row) {
            fputcsv($output, $row, ';');
        }
        rewind($output);
        $content = stream_get_contents($output);
        fclose($output);

        // Ajouter BOM UTF-8 pour Excel
        $content = "\xEF\xBB\xBF" . $content;

        $response = new Response($content);
        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="dashboard_' . $year . '_' . date('Ymd') . '.csv"');

        return $response;
    }

    #[Route('/saeiblauhjc/seo/export-csv', name: 'admin_seo_export_csv')]
    public function exportSeoCsv(
        SeoKeywordRepository $seoKeywordRepository,
        SeoPositionRepository $seoPositionRepository
    ): Response {
        $since = new \DateTimeImmutable('-30 days');
        $now = new \DateTimeImmutable();
        $positions = $seoPositionRepository->findAllSince($since);

        // Récupérer les totaux journaliers (pour le graphique)
        $dailyTotals = $seoPositionRepository->getDailyTotals($since, $now);

        // Générer le contenu CSV
        $output = fopen('php://temp', 'r+');

        // BOM UTF-8 pour Excel
        fwrite($output, "\xEF\xBB\xBF");

        // === SECTION 1: Totaux journaliers (ce qui alimente le graphique) ===
        fputcsv($output, ['TOTAUX JOURNALIERS', '', ''], ';');
        fputcsv($output, ['Date', 'Clics', 'Impressions'], ';');

        // Générer toutes les dates des 30 derniers jours
        $currentDate = $since;
        while ($currentDate <= $now) {
            $dateKey = $currentDate->format('Y-m-d');
            $dayData = $dailyTotals[$dateKey] ?? null;

            fputcsv($output, [
                $dateKey,
                $dayData ? $dayData['clicks'] : 0,
                $dayData ? $dayData['impressions'] : 0,
            ], ';');

            $currentDate = $currentDate->modify('+1 day');
        }

        fputcsv($output, ['', '', ''], ';'); // Ligne vide de séparation

        // === SECTION 2: Détail par mot-clé ===
        fputcsv($output, ['DETAIL PAR MOT-CLE', '', '', '', '', '', '', '', ''], ';');
        fputcsv($output, [
            'Date',
            'Mot-clé',
            'Position',
            'Clics',
            'Impressions',
            'CTR',
            'URL cible',
            'Source',
            'Pertinence',
        ], ';');

        foreach ($positions as $position) {
            $keyword = $position->getKeyword();

            fputcsv($output, [
                $position->getDate()?->format('Y-m-d') ?? '',
                $keyword?->getKeyword() ?? '',
                round($position->getPosition(), 1),
                $position->getClicks(),
                $position->getImpressions(),
                round($position->getCtr(), 2),
                $keyword?->getTargetUrl() ?? '',
                $keyword?->getSource() ?? '',
                $keyword?->getRelevanceLevel() ?? '',
            ], ';');
        }

        rewind($output);
        $content = stream_get_contents($output);
        fclose($output);

        $response = new Response($content);
        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="seo_export_' . date('Y-m-d') . '.csv"');

        return $response;
    }

    /**
     * Récupère les N meilleurs mots-clés (toutes pertinences) triés par clics, impressions, position.
     *
     * @return array<SeoKeyword>
     */
    private function getTopSeoKeywords(SeoKeywordRepository $repository, int $limit = 10): array
    {
        $keywords = $repository->findAllWithLatestPosition();

        // Filtrer uniquement les actifs avec des données
        $keywords = array_filter($keywords, function($keyword) {
            return $keyword->isActive() && $keyword->getLatestPosition() !== null;
        });

        // Trier par clics DESC, impressions DESC, position ASC
        usort($keywords, function($a, $b) {
            $posA = $a->getLatestPosition();
            $posB = $b->getLatestPosition();

            // Clics DESC
            $clicksCompare = $posB->getClicks() <=> $posA->getClicks();
            if ($clicksCompare !== 0) {
                return $clicksCompare;
            }

            // Impressions DESC
            $impressionsCompare = $posB->getImpressions() <=> $posA->getImpressions();
            if ($impressionsCompare !== 0) {
                return $impressionsCompare;
            }

            // Position ASC (meilleure position = plus petit nombre)
            return $posA->getPosition() <=> $posB->getPosition();
        });

        return array_slice($keywords, 0, $limit);
    }

    /**
     * Récupère les N pires mots-clés (pertinence haute uniquement) triés par clics, impressions, position.
     *
     * @return array<SeoKeyword>
     */
    private function getBottomSeoKeywords(SeoKeywordRepository $repository, int $limit = 10): array
    {
        $keywords = $repository->findAllWithLatestPosition();

        // Filtrer uniquement les actifs avec pertinence haute et des données
        $keywords = array_filter($keywords, function($keyword) {
            return $keyword->isActive()
                && $keyword->getRelevanceLevel() === SeoKeyword::RELEVANCE_HIGH
                && $keyword->getLatestPosition() !== null;
        });

        // Trier par clics ASC, impressions ASC, position DESC (les pires en premier)
        usort($keywords, function($a, $b) {
            $posA = $a->getLatestPosition();
            $posB = $b->getLatestPosition();

            // Clics ASC (moins de clics = pire)
            $clicksCompare = $posA->getClicks() <=> $posB->getClicks();
            if ($clicksCompare !== 0) {
                return $clicksCompare;
            }

            // Impressions ASC (moins d'impressions = pire)
            $impressionsCompare = $posA->getImpressions() <=> $posB->getImpressions();
            if ($impressionsCompare !== 0) {
                return $impressionsCompare;
            }

            // Position DESC (pire position = plus grand nombre)
            return $posB->getPosition() <=> $posA->getPosition();
        });

        return array_slice($keywords, 0, $limit);
    }

    /**
     * Catégorise les mots-clés SEO en Top Performers, À améliorer, et Opportunités CTR.
     *
     * @param array $keywords Liste des mots-clés avec leurs positions
     * @return array{topPerformers: array, toImprove: array, ctrOpportunities: array}
     */
    private function categorizeSeoKeywords(array $keywords): array
    {
        $topPerformers = [];
        $toImprove = [];
        $ctrOpportunities = [];

        foreach ($keywords as $keyword) {
            if (!$keyword->isActive()) {
                continue;
            }

            $latestPosition = $keyword->getLatestPosition();
            if (!$latestPosition) {
                continue;
            }

            $position = $latestPosition->getPosition();
            $clicks = $latestPosition->getClicks();
            $impressions = $latestPosition->getImpressions();
            $ctr = $impressions > 0 ? round(($clicks / $impressions) * 100, 2) : 0;

            $keywordData = [
                'keyword' => $keyword->getKeyword(),
                'position' => $position,
                'clicks' => $clicks,
                'impressions' => $impressions,
                'ctr' => $ctr,
                'relevanceLevel' => $keyword->getRelevanceLevel(),
                'source' => $keyword->getSource(),
            ];

            // Top Performers: position <= 10 (première page)
            if ($position <= 10) {
                $topPerformers[] = $keywordData;
            }

            // À améliorer: position > 20 ET pertinence != low
            // Les mots-clés "low" ne sont pas prioritaires à améliorer
            if ($position > 20 && $keyword->getRelevanceLevel() !== SeoKeyword::RELEVANCE_LOW) {
                $toImprove[] = $keywordData;
            }

            // Opportunités CTR: beaucoup d'impressions (>= 100) mais CTR < 2%
            if ($impressions >= 100 && $ctr < 2) {
                $ctrOpportunities[] = $keywordData;
            }
        }

        // Trier par position (meilleure en premier pour top performers)
        usort($topPerformers, fn($a, $b) => $a['position'] <=> $b['position']);
        // Trier par pertinence (high > medium) puis par position (pire en premier)
        usort($toImprove, function($a, $b) {
            $relevanceOrder = [SeoKeyword::RELEVANCE_HIGH => 0, SeoKeyword::RELEVANCE_MEDIUM => 1, SeoKeyword::RELEVANCE_LOW => 2];
            $relevanceA = $relevanceOrder[$a['relevanceLevel']] ?? 2;
            $relevanceB = $relevanceOrder[$b['relevanceLevel']] ?? 2;
            if ($relevanceA !== $relevanceB) {
                return $relevanceA <=> $relevanceB;
            }
            return $b['position'] <=> $a['position'];
        });
        // Trier par impressions (plus d'impressions = plus grande opportunité)
        usort($ctrOpportunities, fn($a, $b) => $b['impressions'] <=> $a['impressions']);

        return [
            'topPerformers' => array_slice($topPerformers, 0, 5),
            'toImprove' => array_slice($toImprove, 0, 5),
            'ctrOpportunities' => array_slice($ctrOpportunities, 0, 5),
        ];
    }

    /**
     * Prépare les données pour le graphique SEO (clics/impressions sur 30 jours).
     *
     * @return array{labels: array, clicks: array, impressions: array, hasEnoughData: bool}
     */
    private function prepareSeoChartData(SeoDailyTotalRepository $dailyTotalRepository): array
    {
        $now = new \DateTimeImmutable();
        $chartStartDate = $now->modify('-29 days')->setTime(0, 0, 0);
        // Récupérer 6 jours supplémentaires pour calculer la moyenne 7j dès le début
        $dataStartDate = $now->modify('-35 days')->setTime(0, 0, 0);

        // Récupérer les totaux journaliers (vrais clics, pas anonymisés)
        $dailyTotals = $dailyTotalRepository->findByDateRange($dataStartDate, $now);

        if (empty($dailyTotals)) {
            return [
                'labels' => [],
                'clicks' => [],
                'impressions' => [],
                'ctr' => [],
                'position' => [],
                'clicks7d' => [],
                'impressions7d' => [],
                'ctr7d' => [],
                'position7d' => [],
                'hasEnoughData' => false,
                'daysWithData' => 0,
            ];
        }

        // Trouver la première et dernière date avec des données
        $firstDataDate = null;
        $lastDataDate = null;
        foreach ($dailyTotals as $total) {
            $date = $total->getDate();
            if ($firstDataDate === null || $date < $firstDataDate) {
                $firstDataDate = $date;
            }
            if ($lastDataDate === null || $date > $lastDataDate) {
                $lastDataDate = $date;
            }
        }

        // Indexer par date pour un accès rapide
        $dailyData = [];
        foreach ($dailyTotals as $total) {
            $dateKey = $total->getDate()->format('Y-m-d');
            $dailyData[$dateKey] = [
                'clicks' => $total->getClicks(),
                'impressions' => $total->getImpressions(),
                'ctr' => $total->getCtr(),
                'position' => $total->getPosition(),
            ];
        }

        $daysWithData = count($dailyTotals);
        $hasEnoughData = $daysWithData >= 7;

        // Construire les tableaux de données complets (incluant les 6 jours avant pour le calcul 7j)
        $allLabels = [];
        $allClicks = [];
        $allImpressions = [];
        $allCtr = [];
        $allPosition = [];

        $currentDate = $firstDataDate;
        $endDate = $lastDataDate;

        while ($currentDate <= $endDate) {
            $dateKey = $currentDate->format('Y-m-d');
            $allLabels[] = $currentDate->format('d/m');

            if (isset($dailyData[$dateKey])) {
                $allClicks[] = $dailyData[$dateKey]['clicks'];
                $allImpressions[] = $dailyData[$dateKey]['impressions'];
                $allCtr[] = $dailyData[$dateKey]['ctr'];
                $allPosition[] = $dailyData[$dateKey]['position'];
            } else {
                $allClicks[] = 0;
                $allImpressions[] = 0;
                $allCtr[] = null;
                $allPosition[] = null;
            }

            $currentDate = $currentDate->modify('+1 day');
        }

        // Calculer les moyennes mobiles sur 7 jours (vraies moyennes 7j)
        $allClicks7d = [];
        $allImpressions7d = [];
        $allCtr7d = [];
        $allPosition7d = [];

        for ($i = 0; $i < count($allClicks); $i++) {
            // Toujours utiliser 7 jours si disponibles
            $windowStart = max(0, $i - 6);

            $sumClicks = 0;
            $sumImpressions = 0;
            $sumCtr = 0;
            $sumPosition = 0;
            $countCtr = 0;
            $countPosition = 0;

            for ($j = $windowStart; $j <= $i; $j++) {
                $sumClicks += $allClicks[$j];
                $sumImpressions += $allImpressions[$j];
                if ($allCtr[$j] !== null) {
                    $sumCtr += $allCtr[$j];
                    $countCtr++;
                }
                if ($allPosition[$j] !== null && $allPosition[$j] > 0) {
                    $sumPosition += $allPosition[$j];
                    $countPosition++;
                }
            }

            $allClicks7d[] = $sumClicks;
            $allImpressions7d[] = $sumImpressions;
            $allCtr7d[] = $countCtr > 0 ? round($sumCtr / $countCtr, 2) : null;
            $allPosition7d[] = $countPosition > 0 ? round($sumPosition / $countPosition, 1) : null;
        }

        // Déterminer l'index de début pour l'affichage (max 30 jours)
        $totalDays = count($allLabels);
        $displayDays = min(30, $totalDays);
        $startIndex = $totalDays - $displayDays;

        // Extraire uniquement les données à afficher
        $labels = array_slice($allLabels, $startIndex);
        $clicks = array_slice($allClicks, $startIndex);
        $impressions = array_slice($allImpressions, $startIndex);
        $ctr = array_slice($allCtr, $startIndex);
        $position = array_slice($allPosition, $startIndex);
        $clicks7d = array_slice($allClicks7d, $startIndex);
        $impressions7d = array_slice($allImpressions7d, $startIndex);
        $ctr7d = array_slice($allCtr7d, $startIndex);
        $position7d = array_slice($allPosition7d, $startIndex);

        return [
            'labels' => $labels,
            'clicks' => $clicks,
            'impressions' => $impressions,
            'ctr' => $ctr,
            'position' => $position,
            'clicks7d' => $clicks7d,
            'impressions7d' => $impressions7d,
            'ctr7d' => $ctr7d,
            'position7d' => $position7d,
            'hasEnoughData' => $hasEnoughData,
            'daysWithData' => $daysWithData,
        ];
    }

    /**
     * Calcule les comparaisons de positions SEO entre le mois courant et le mois précédent.
     *
     * @return array<int, array{currentPosition: ?float, previousPosition: ?float, variation: ?float, status: string}>
     */
    private function calculateSeoPositionComparisons(
        SeoKeywordRepository $keywordRepository,
        SeoPositionRepository $positionRepository
    ): array {
        $now = new \DateTimeImmutable();

        // Période du mois courant
        $currentMonthStart = $now->modify('first day of this month')->setTime(0, 0, 0);
        $currentMonthEnd = $now->setTime(23, 59, 59);

        // Période du mois précédent
        $previousMonthStart = $now->modify('first day of last month')->setTime(0, 0, 0);
        $previousMonthEnd = $now->modify('last day of last month')->setTime(23, 59, 59);

        // Récupérer les positions moyennes pour chaque période
        $currentPositions = $positionRepository->getAveragePositionsForAllKeywords(
            $currentMonthStart,
            $currentMonthEnd
        );
        $previousPositions = $positionRepository->getAveragePositionsForAllKeywords(
            $previousMonthStart,
            $previousMonthEnd
        );

        // Récupérer tous les mots-clés actifs
        $keywords = $keywordRepository->findActiveKeywords();

        $comparisons = [];
        foreach ($keywords as $keyword) {
            $keywordId = $keyword->getId();
            $currentData = $currentPositions[$keywordId] ?? null;
            $previousData = $previousPositions[$keywordId] ?? null;

            $currentPosition = $currentData['avgPosition'] ?? null;
            $previousPosition = $previousData['avgPosition'] ?? null;

            // Calculer la variation
            $variation = null;
            $status = 'no_data';

            if ($currentPosition !== null && $previousPosition !== null) {
                // Variation = position précédente - position actuelle
                // Positif = amélioration (on monte dans le classement)
                // Négatif = dégradation (on descend dans le classement)
                $variation = round($previousPosition - $currentPosition, 1);

                if ($variation > 0) {
                    $status = 'improved';
                } elseif ($variation < 0) {
                    $status = 'degraded';
                } else {
                    $status = 'stable';
                }
            } elseif ($currentPosition !== null && $previousPosition === null) {
                $status = 'new';
            }

            $comparisons[$keywordId] = [
                'currentPosition' => $currentPosition,
                'previousPosition' => $previousPosition,
                'variation' => $variation,
                'status' => $status,
            ];
        }

        return $comparisons;
    }

    /**
     * Prépare les données pour le graphique d'évolution des mots-clés SEO.
     * Utilise MIN(SeoPosition.date) comme date de première apparition GSC (pas createdAt).
     * S'arrête à la dernière date avec des données (comme les autres graphiques SEO).
     */
    private function prepareSeoKeywordsChartData(
        SeoKeywordRepository $repo,
        SeoDailyTotalRepository $dailyTotalRepo
    ): array {
        $days = 30;
        // Use All to include inactive keywords in the chart cumulative
        $firstAppearances = $repo->getKeywordFirstAppearancesAll();
        $relevanceCounts = $repo->getRelevanceCounts();
        $deactivations = $repo->getKeywordDeactivations();
        $inactiveCount = $repo->countInactive();

        // Total actif actuel et badges
        $currentTotal = 0;
        $relevanceMap = ['high' => 0, 'medium' => 0, 'low' => 0];
        foreach ($relevanceCounts as $row) {
            $relevanceMap[$row['relevanceLevel']] = (int) $row['cnt'];
            $currentTotal += (int) $row['cnt'];
        }

        // Trouver la dernière date avec des données (cohérent avec les autres graphiques)
        $now = new \DateTimeImmutable();
        $dataStartDate = $now->modify("-{$days} days")->setTime(0, 0, 0);
        $dailyTotals = $dailyTotalRepo->findByDateRange($dataStartDate, $now);

        $lastDataDate = null;
        foreach ($dailyTotals as $total) {
            $date = $total->getDate();
            if ($lastDataDate === null || $date > $lastDataDate) {
                $lastDataDate = $date;
            }
        }

        // Si pas de données, utiliser aujourd'hui comme fallback
        $endDate = $lastDataDate ?? $now;
        $startDate = $endDate->modify("-" . ($days - 1) . " days");
        $sinceDate = $startDate->format('Y-m-d');

        // Séparer : mots-clés apparus avant la période (base) vs dans la période (nouveaux)
        $baseCount = 0;
        $newByDay = [];
        foreach ($firstAppearances as $row) {
            $firstSeen = $row['firstSeen'];
            $level = $row['relevanceLevel'];

            if ($firstSeen < $sinceDate) {
                $baseCount++;
            } else {
                if (!isset($newByDay[$firstSeen])) {
                    $newByDay[$firstSeen] = ['high' => 0, 'medium' => 0, 'low' => 0];
                }
                $newByDay[$firstSeen][$level]++;
            }
        }

        // Désactivations : avant la période (base) vs dans la période
        $baseDeactivated = 0;
        $deactivatedByDay = [];
        foreach ($deactivations as $row) {
            $deactivatedDate = $row['deactivatedDate'];
            if ($deactivatedDate < $sinceDate) {
                $baseDeactivated++;
            } else {
                $deactivatedByDay[$deactivatedDate] = ($deactivatedByDay[$deactivatedDate] ?? 0) + 1;
            }
        }

        // Générer les jours de startDate à endDate avec cumulatif progressif
        $labels = [];
        $newHigh = [];
        $newMedium = [];
        $newLow = [];
        $deactivated = [];
        $totalKeywords = [];
        $cumulative = $baseCount - $baseDeactivated;

        $currentDate = $startDate;
        while ($currentDate <= $endDate) {
            $dateKey = $currentDate->format('Y-m-d');

            $dayHigh = $newByDay[$dateKey]['high'] ?? 0;
            $dayMedium = $newByDay[$dateKey]['medium'] ?? 0;
            $dayLow = $newByDay[$dateKey]['low'] ?? 0;
            $dayDeactivated = $deactivatedByDay[$dateKey] ?? 0;

            $cumulative += $dayHigh + $dayMedium + $dayLow - $dayDeactivated;

            $labels[] = $currentDate->format('d/m');
            $newHigh[] = $dayHigh;
            $newMedium[] = $dayMedium;
            $newLow[] = $dayLow;
            $deactivated[] = $dayDeactivated > 0 ? -$dayDeactivated : 0;
            $totalKeywords[] = $cumulative;

            $currentDate = $currentDate->modify('+1 day');
        }

        return [
            'labels' => $labels,
            'totalKeywords' => $totalKeywords,
            'newHigh' => $newHigh,
            'newMedium' => $newMedium,
            'newLow' => $newLow,
            'deactivated' => $deactivated,
            'currentTotal' => $currentTotal,
            'inactiveCount' => $inactiveCount,
            'relevanceCounts' => $relevanceMap,
        ];
    }

    private function formatEventDate(Event $event): string
    {
        $start = $event->getStartAt();
        $end = $event->getEndAt();

        // Format jour en français
        $days = ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];
        $months = ['', 'janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];

        $dayName = $days[(int) $start->format('w')];
        $dayNum = $start->format('j');
        $monthName = $months[(int) $start->format('n')];
        $year = $start->format('Y');

        if ($event->isAllDay()) {
            return ucfirst($dayName) . ' ' . $dayNum . ' ' . $monthName . ' ' . $year . ' (journée entière)';
        }

        $result = ucfirst($dayName) . ' ' . $dayNum . ' ' . $monthName . ' ' . $year . ' à ' . $start->format('H:i');

        if ($end && $end->format('Y-m-d') === $start->format('Y-m-d')) {
            $result .= ' - ' . $end->format('H:i');
        }

        return $result;
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
        yield MenuItem::linkToRoute('Tableau de bord', 'fa fa-home', 'admin_business_dashboard');
        yield MenuItem::linkToRoute('Calendrier', 'fa fa-calendar-alt', 'admin_calendar');

        yield MenuItem::section('Site Public');
        yield MenuItem::linkToCrud('Portfolio', 'fas fa-folder-open', Project::class);
        yield MenuItem::linkToCrud('Partenaires', 'fas fa-handshake', Partner::class);
        yield MenuItem::linkToCrud('Témoignages', 'fas fa-star', Testimonial::class);
        yield MenuItem::linkToCrud('Avis Google', 'fab fa-google', GoogleReview::class);
        yield MenuItem::linkToCrud('Messages de contact', 'fas fa-envelope', ContactMessage::class);

        yield MenuItem::section('SEO');
        yield MenuItem::linkToCrud('Mots-clés SEO', 'fas fa-search', SeoKeyword::class);
        yield MenuItem::linkToCrud('Villes (SEO Local)', 'fas fa-map-marker-alt', City::class);

        yield MenuItem::section('Gestion commerciale');
        yield MenuItem::linkToCrud('Devis', 'fas fa-file-invoice', Devis::class);
        yield MenuItem::linkToCrud('Factures', 'fas fa-file-invoice-dollar', Facture::class);
        yield MenuItem::linkToCrud('Événements', 'fas fa-calendar-check', Event::class);
        yield MenuItem::linkToCrud('Types d\'événements', 'fas fa-tags', EventType::class);

        yield MenuItem::section('Clients');
        yield MenuItem::linkToCrud('Clients', 'fas fa-users', Client::class);

        yield MenuItem::section('Sécurité');
        yield MenuItem::linkToCrud('Logs de sécurité', 'fas fa-shield-alt', SecurityLog::class);
        yield MenuItem::linkToCrud('IPs bloquées', 'fas fa-ban', BlockedIp::class);

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