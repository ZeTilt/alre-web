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
use App\Entity\SecurityLog;
use App\Entity\ClientSeoKeyword;
use App\Entity\ClientSeoReport;
use App\Entity\DepartmentPage;
use App\Entity\PageOptimization;
use App\Entity\SeoKeyword;
use App\Entity\User;
use App\Entity\Project;
use App\Entity\Partner;
use App\Entity\Testimonial;
use App\Repository\BingConfigRepository;
use App\Repository\CompanyRepository;
use App\Repository\DevisRepository;
use App\Repository\EventRepository;
use App\Repository\EventTypeRepository;
use App\Repository\FactureRepository;
use App\Repository\ProspectRepository;
use App\Repository\ProspectFollowUpRepository;
use App\Repository\ClientSeoImportRepository;
use App\Repository\ClientSeoKeywordRepository;
use App\Repository\ClientSiteRepository;
use App\Repository\PageOptimizationRepository;
use App\Repository\SeoDailyTotalRepository;
use App\Repository\SeoKeywordRepository;
use App\Repository\SeoPositionRepository;
use App\Repository\ClientSeoDailyTotalRepository;
use App\Repository\ClientSeoPositionRepository;
use App\Repository\ClientSeoReportRepository;
use App\Service\ClientSeoDashboardService;
use App\Service\ClientSeoReportService;
use App\Service\SeoReportService;
use App\Service\CityKeywordMatcher;
use App\Service\DashboardPeriodService;
use App\Service\DashboardSeoService;
use App\Service\ProspectionEmailService;
use App\Entity\ClientSite;
use App\Form\ClientSiteType;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Annotation\Route;

class DashboardController extends AbstractDashboardController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ParameterBagInterface $params,
        private DashboardSeoService $dashboardSeoService,
        private ClientSiteRepository $clientSiteRepository,
        private CityKeywordMatcher $cityKeywordMatcher,
    ) {
    }

    #[Route('/saeiblauhjc', name: 'admin')]
    public function index(): Response
    {
        return $this->redirectToRoute('admin_main_dashboard');
    }

    #[Route('/saeiblauhjc/business-dashboard', name: 'admin_business_dashboard')]
    public function businessDashboard(): Response
    {
        return $this->redirectToRoute('admin_main_dashboard');
    }

    #[Route('/saeiblauhjc/dashboard', name: 'admin_main_dashboard')]
    public function mainDashboard(
        CompanyRepository $companyRepository,
        FactureRepository $factureRepository,
        DevisRepository $devisRepository
    ): Response {
        $company = $companyRepository->findOneBy([]);
        $year = $company?->getAnneeFiscaleEnCours() ?? (int) date('Y');

        $now = new \DateTimeImmutable();
        $startOfMonth = $now->modify('first day of this month')->setTime(0, 0, 0);
        $endOfMonth = $now->modify('last day of this month')->setTime(23, 59, 59);
        $startOfYear = new \DateTimeImmutable($year . '-01-01 00:00:00');
        $endOfYear = new \DateTimeImmutable($year . '-12-31 23:59:59');
        $startOfPreviousMonth = $now->modify('first day of last month')->setTime(0, 0, 0);
        $endOfPreviousMonth = $now->modify('last day of last month')->setTime(23, 59, 59);

        // CA
        $caMoisEncaisse = $factureRepository->getRevenueByPeriod($startOfMonth, $endOfMonth, true);
        $caMoisPrecedentEncaisse = $factureRepository->getRevenueByPeriod($startOfPreviousMonth, $endOfPreviousMonth, true);
        $caAnneeEncaisse = $factureRepository->getRevenueByPeriod($startOfYear, $endOfYear, true);

        $variationMoisPourcent = 0;
        if ($caMoisPrecedentEncaisse > 0) {
            $variationMoisPourcent = round((($caMoisEncaisse - $caMoisPrecedentEncaisse) / $caMoisPrecedentEncaisse) * 100, 1);
        }

        // Plafond
        $plafondCa = $company?->getPlafondCaAnnuel() ? ((float) $company->getPlafondCaAnnuel() / 100) : 0;
        $progressionPlafond = $plafondCa > 0 ? round(($caAnneeEncaisse / $plafondCa) * 100, 1) : 0;

        // En attente
        $caEnAttente = $factureRepository->getPendingRevenue();

        // Factures en retard
        $facturesEnRetard = $factureRepository->findOverdueFactures();
        $nbFacturesEnRetard = count($facturesEnRetard);
        $montantEnRetard = array_reduce($facturesEnRetard, fn($sum, $f) => $sum + (float) $f->getTotalTtc(), 0);

        // Devis
        $tauxConversion = $devisRepository->getConversionRate($startOfYear, $endOfYear);
        $devisARelancer = $devisRepository->getQuotesToFollowUp(7);
        $nbDevisARelancer = count($devisARelancer);

        // Cotisations / Benefice
        $tauxCotisations = $company?->getTauxCotisationsUrssaf() ? (float) $company->getTauxCotisationsUrssaf() : 0;
        $cotisationsEstimees = round($caAnneeEncaisse * ($tauxCotisations / 100), 2);
        $depensesAnnee = 0;
        $beneficeNetAnnee = $caAnneeEncaisse - $depensesAnnee - $cotisationsEstimees;
        $margeNetAnnee = $caAnneeEncaisse > 0 ? round(($beneficeNetAnnee / $caAnneeEncaisse) * 100, 1) : 0;

        // SEO Summary
        $seoSummary = $this->dashboardSeoService->getSummaryData();

        return $this->render('admin/dashboard/main.html.twig', [
            'year' => $year,
            'demoMode' => ($_ENV['APP_DEMO_MODE'] ?? '0') === '1',

            // Finances
            'caMoisEncaisse' => $caMoisEncaisse,
            'caAnneeEncaisse' => $caAnneeEncaisse,
            'variationMoisPourcent' => $variationMoisPourcent,
            'plafondCa' => $plafondCa,
            'progressionPlafond' => $progressionPlafond,
            'caEnAttente' => $caEnAttente,

            // Alertes
            'nbFacturesEnRetard' => $nbFacturesEnRetard,
            'montantEnRetard' => $montantEnRetard,
            'nbDevisARelancer' => $nbDevisARelancer,
            'tauxConversion' => $tauxConversion,
            'beneficeNetAnnee' => $beneficeNetAnnee,
            'margeNetAnnee' => $margeNetAnnee,

            // SEO Summary
            'seoSummary' => $seoSummary,
        ]);
    }

    #[Route('/saeiblauhjc/dashboard/seo', name: 'admin_seo_dashboard')]
    public function seoDashboard(): Response
    {
        $seoData = $this->dashboardSeoService->getFullData();

        return $this->render('admin/dashboard/seo.html.twig', array_merge(
            $seoData,
            ['demoMode' => ($_ENV['APP_DEMO_MODE'] ?? '0') === '1']
        ));
    }

    #[Route('/saeiblauhjc/seo/sync-history', name: 'admin_seo_sync_history')]
    public function seoSyncHistory(\App\Repository\SeoSyncLogRepository $repository): Response
    {
        $logs = $repository->findLatest(50);

        return $this->render('admin/seo_sync_log/index.html.twig', [
            'logs' => $logs,
        ]);
    }

    #[Route('/saeiblauhjc/seo/share-card', name: 'admin_seo_share_card')]
    public function seoShareCard(): Response
    {
        $data = $this->dashboardSeoService->getFullData();
        $data['shareMode'] = true;

        return $this->render('admin/seo_sync_log/share_card.html.twig', $data);
    }

    #[Route('/saeiblauhjc/seo-keyword/{id}/mark-optimized', name: 'admin_seo_keyword_mark_optimized', methods: ['POST'])]
    public function markKeywordOptimized(SeoKeyword $keyword, Request $request): JsonResponse
    {
        if (!$this->isCsrfTokenValid('seo-optimize-' . $keyword->getId(), $request->request->get('_token'))) {
            return new JsonResponse(['error' => 'Token CSRF invalide'], 403);
        }

        $keyword->setLastOptimizedAt(new \DateTimeImmutable());
        $this->entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'date' => $keyword->getLastOptimizedAt()->format('d/m/Y'),
        ]);
    }

    #[Route('/saeiblauhjc/city/{id}/mark-optimized', name: 'admin_city_mark_optimized', methods: ['POST'])]
    public function markCityOptimized(City $city, Request $request, SeoKeywordRepository $seoKeywordRepository): JsonResponse
    {
        if (!$this->isCsrfTokenValid('city-optimize-' . $city->getId(), $request->request->get('_token'))) {
            return new JsonResponse(['error' => 'Token CSRF invalide'], 403);
        }

        $patterns = $this->cityKeywordMatcher->buildCityPatterns($city);
        $count = $seoKeywordRepository->markOptimizedByPatterns($patterns);

        $now = new \DateTimeImmutable();
        $city->setLastOptimizedAt($now);
        $this->entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'count' => $count,
            'date' => $now->format('d/m/Y'),
        ]);
    }

    #[Route('/saeiblauhjc/department/{id}/mark-optimized', name: 'admin_department_mark_optimized', methods: ['POST'])]
    public function markDepartmentOptimized(DepartmentPage $department, Request $request, SeoKeywordRepository $seoKeywordRepository): JsonResponse
    {
        if (!$this->isCsrfTokenValid('dept-optimize-' . $department->getId(), $request->request->get('_token'))) {
            return new JsonResponse(['error' => 'Token CSRF invalide'], 403);
        }

        $patterns = $this->cityKeywordMatcher->buildDepartmentPatterns($department);
        $count = $seoKeywordRepository->markOptimizedByPatterns($patterns);

        $now = new \DateTimeImmutable();
        $department->setLastOptimizedAt($now);
        $this->entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'count' => $count,
            'date' => $now->format('d/m/Y'),
        ]);
    }

    #[Route('/saeiblauhjc/page-optimization/{id}/mark-optimized', name: 'admin_page_optimization_mark_optimized', methods: ['POST'])]
    public function markPageOptimized(PageOptimization $page, Request $request, SeoKeywordRepository $seoKeywordRepository): JsonResponse
    {
        if (!$this->isCsrfTokenValid('page-optimize-' . $page->getId(), $request->request->get('_token'))) {
            return new JsonResponse(['error' => 'Token CSRF invalide'], 403);
        }

        // Build the full URL from the page path
        $fullUrl = 'https://alre-web.bzh' . $page->getUrl();
        $count = $seoKeywordRepository->markOptimizedByTargetUrl($fullUrl);

        $now = new \DateTimeImmutable();
        $page->setLastOptimizedAt($now);
        $this->entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'count' => $count,
            'date' => $now->format('d/m/Y'),
        ]);
    }

    #[Route('/saeiblauhjc/dashboard/finance', name: 'admin_finance_dashboard')]
    public function financeDashboard(
        Request $request,
        CompanyRepository $companyRepository,
        FactureRepository $factureRepository,
        DevisRepository $devisRepository,
        DashboardPeriodService $periodService
    ): Response {
        $company = $companyRepository->findOneBy([]);

        // Gestion de la période sélectionnée
        $periodType = $request->query->get('period');
        $customStartStr = $request->query->get('startDate');
        $customEndStr = $request->query->get('endDate');

        if ($periodType) {
            $periodService->savePeriodToSession($periodType, $customStartStr, $customEndStr);
        } else {
            $sessionData = $periodService->getPeriodFromSession();
            $periodType = $sessionData['type'];
            $customStartStr = $sessionData['customStart'];
            $customEndStr = $sessionData['customEnd'];
        }

        $customStart = $customStartStr ? new \DateTimeImmutable($customStartStr . ' 00:00:00') : null;
        $customEnd = $customEndStr ? new \DateTimeImmutable($customEndStr . ' 23:59:59') : null;

        $periodDates = $periodService->getPeriodDates($periodType, $customStart, $customEnd);
        $startOfPeriod = $periodDates['start'];
        $endOfPeriod = $periodDates['end'];
        $periodLabel = $periodDates['label'];

        $comparisonDates = $periodService->getComparisonPeriodDates($startOfPeriod, $endOfPeriod);
        $startOfComparison = $comparisonDates['start'];
        $endOfComparison = $comparisonDates['end'];

        $monthLabels = $periodService->getMonthLabelsForPeriod($startOfPeriod, $endOfPeriod);
        $monthKeys = $periodService->getMonthKeysForPeriod($startOfPeriod, $endOfPeriod);

        $year = $company?->getAnneeFiscaleEnCours() ?? (int) date('Y');

        $now = new \DateTimeImmutable();
        $startOfMonth = $now->modify('first day of this month')->setTime(0, 0, 0);
        $endOfMonth = $now->modify('last day of this month')->setTime(23, 59, 59);
        $startOfYear = new \DateTimeImmutable($year . '-01-01 00:00:00');
        $endOfYear = new \DateTimeImmutable($year . '-12-31 23:59:59');
        $startOfPreviousMonth = $now->modify('first day of last month')->setTime(0, 0, 0);
        $endOfPreviousMonth = $now->modify('last day of last month')->setTime(23, 59, 59);

        // ===== FINANCES =====
        $caMoisEncaisse = $factureRepository->getRevenueByPeriod($startOfMonth, $endOfMonth, true);
        $caMoisPrecedentEncaisse = $factureRepository->getRevenueByPeriod($startOfPreviousMonth, $endOfPreviousMonth, true);
        $caAnneeEncaisse = $factureRepository->getRevenueByPeriod($startOfYear, $endOfYear, true);

        $variationMoisPourcent = 0;
        if ($caMoisPrecedentEncaisse > 0) {
            $variationMoisPourcent = round((($caMoisEncaisse - $caMoisPrecedentEncaisse) / $caMoisPrecedentEncaisse) * 100, 1);
        }

        $objectifMensuel = $company?->getObjectifCaMensuel() ? ((float) $company->getObjectifCaMensuel() / 100) : 0;
        $objectifAnnuel = $company?->getObjectifCaAnnuel() ? ((float) $company->getObjectifCaAnnuel() / 100) : 0;
        $progressionObjectifAnnuel = $objectifAnnuel > 0 ? round(($caAnneeEncaisse / $objectifAnnuel) * 100, 1) : 0;

        $plafondCa = $company?->getPlafondCaAnnuel() ? ((float) $company->getPlafondCaAnnuel() / 100) : 0;
        $progressionPlafond = $plafondCa > 0 ? round(($caAnneeEncaisse / $plafondCa) * 100, 1) : 0;

        $tauxCotisations = $company?->getTauxCotisationsUrssaf() ? (float) $company->getTauxCotisationsUrssaf() : 0;
        $cotisationsEstimees = round($caAnneeEncaisse * ($tauxCotisations / 100), 2);
        $cotisationsMois = round($caMoisEncaisse * ($tauxCotisations / 100), 2);

        $caEnAttente = $factureRepository->getPendingRevenue();

        // Factures
        $facturesEnRetard = $factureRepository->findOverdueFactures();
        $nbFacturesEnRetard = count($facturesEnRetard);
        $montantEnRetard = array_reduce($facturesEnRetard, fn($sum, $f) => $sum + (float) $f->getTotalTtc(), 0);

        // Devis
        $devisPending = $devisRepository->findPendingDevis();
        $nbDevisPending = count($devisPending);
        $tauxConversion = $devisRepository->getConversionRate($startOfYear, $endOfYear);
        $devisARelancer = $devisRepository->getQuotesToFollowUp(7);
        $nbDevisARelancer = count($devisARelancer);

        // Depenses / Benefice
        $depensesMois = 0;
        $depensesAnnee = 0;
        $beneficeNetMois = $caMoisEncaisse - $depensesMois - $cotisationsMois;
        $beneficeNetAnnee = $caAnneeEncaisse - $depensesAnnee - $cotisationsEstimees;
        $margeNetAnnee = $caAnneeEncaisse > 0 ? round(($beneficeNetAnnee / $caAnneeEncaisse) * 100, 1) : 0;

        // ===== GRAPHIQUES =====
        $caParMoisData = $factureRepository->getMonthlyPaidRevenueForPeriod($startOfPeriod, $endOfPeriod);
        $caParMois = [];
        foreach ($monthKeys as $key) {
            $caParMois[] = $caParMoisData[$key] ?? 0;
        }

        $caParMoisComparisonData = $factureRepository->getMonthlyPaidRevenueForPeriod($startOfComparison, $endOfComparison);
        $caParMoisComparison = [];
        $comparisonKeys = $periodService->getMonthKeysForPeriod($startOfComparison, $endOfComparison);
        foreach ($comparisonKeys as $key) {
            $caParMoisComparison[] = $caParMoisComparisonData[$key] ?? 0;
        }

        $caPeriode = $factureRepository->getRevenueByPeriod($startOfPeriod, $endOfPeriod, true);
        $caPeriodeComparison = $factureRepository->getRevenueByPeriod($startOfComparison, $endOfComparison, true);
        $variationPeriodePourcent = 0;
        if ($caPeriodeComparison > 0) {
            $variationPeriodePourcent = round((($caPeriode - $caPeriodeComparison) / $caPeriodeComparison) * 100, 1);
        }

        $topClients = $factureRepository->getRevenueByClientForPeriod($startOfPeriod, $endOfPeriod);
        $topClients = array_slice($topClients, 0, 5);

        $cotisationsParMois = [];
        foreach ($caParMois as $ca) {
            $cotisationsParMois[] = round($ca * ($tauxCotisations / 100), 2);
        }

        // ===== PREVISIONS =====
        $currentMonth = (int) $now->format('m');
        $lastThreeMonths = [];
        for ($i = 2; $i >= 0; $i--) {
            $month = $currentMonth - $i;
            if ($month > 0 && $month <= 12) {
                $lastThreeMonths[] = $caParMois[$month] ?? 0;
            }
        }

        $avgLastThreeMonths = count($lastThreeMonths) > 0 ? array_sum($lastThreeMonths) / count($lastThreeMonths) : 0;

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

        $tendance = 0;
        if ($avgLastThreeMonths > 0 && $caMoisEncaisse > 0) {
            $tendance = round((($caMoisEncaisse - $avgLastThreeMonths) / $avgLastThreeMonths) * 100, 1);
        }

        return $this->render('admin/dashboard/finance.html.twig', [
            'company' => $company,
            'year' => $year,
            'demoMode' => ($_ENV['APP_DEMO_MODE'] ?? '0') === '1',

            // Période
            'periodType' => $periodType,
            'periodLabel' => $periodLabel,
            'periodChoices' => DashboardPeriodService::getPeriodChoices(),
            'customStartDate' => $customStartStr,
            'customEndDate' => $customEndStr,

            // CA période
            'caPeriode' => $caPeriode,
            'caPeriodeComparison' => $caPeriodeComparison,
            'variationPeriodePourcent' => $variationPeriodePourcent,

            // KPIs
            'caMoisEncaisse' => $caMoisEncaisse,
            'caAnneeEncaisse' => $caAnneeEncaisse,
            'variationMoisPourcent' => $variationMoisPourcent,
            'objectifAnnuel' => $objectifAnnuel,
            'progressionObjectifAnnuel' => $progressionObjectifAnnuel,
            'plafondCa' => $plafondCa,
            'progressionPlafond' => $progressionPlafond,
            'cotisationsEstimees' => $cotisationsEstimees,
            'tauxCotisations' => $tauxCotisations,
            'caEnAttente' => $caEnAttente,

            // Factures / Devis
            'nbFacturesEnRetard' => $nbFacturesEnRetard,
            'montantEnRetard' => $montantEnRetard,
            'nbDevisPending' => $nbDevisPending,
            'tauxConversion' => $tauxConversion,
            'nbDevisARelancer' => $nbDevisARelancer,

            // Benefice
            'beneficeNetAnnee' => $beneficeNetAnnee,
            'margeNetAnnee' => $margeNetAnnee,

            // Graphiques
            'caParMois' => $caParMois,
            'caParMoisComparison' => $caParMoisComparison,
            'monthLabels' => $monthLabels,
            'topClients' => $topClients,
            'cotisationsParMois' => $cotisationsParMois,

            // Prévisions
            'projections' => $projections,
            'tendance' => $tendance,
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

    #[Route('/saeiblauhjc/seo/export-unscored', name: 'admin_seo_export_unscored')]
    public function exportUnscoredKeywords(SeoKeywordRepository $seoKeywordRepository): Response
    {
        $keywords = $seoKeywordRepository->findUnscoredKeywords();
        $lines = array_map(fn(SeoKeyword $k) => $k->getKeyword(), $keywords);
        $content = implode("\n", $lines);

        $response = new Response($content);
        $response->headers->set('Content-Type', 'text/plain; charset=UTF-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="unscored_keywords_' . date('Y-m-d') . '.txt"');

        return $response;
    }

    #[Route('/saeiblauhjc/seo-keyword/{id}/set-score', name: 'admin_seo_keyword_set_score', methods: ['POST'])]
    public function setKeywordScore(SeoKeyword $keyword, Request $request): JsonResponse
    {
        if (!$this->isCsrfTokenValid('seo-score-' . $keyword->getId(), $request->request->get('_token'))) {
            return new JsonResponse(['error' => 'Token CSRF invalide'], 403);
        }

        $score = (int) $request->request->get('score', 0);
        $keyword->setRelevanceScore($score);
        $this->entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'score' => $keyword->getRelevanceScore(),
        ]);
    }

    #[Route('/saeiblauhjc/seo/export-csv', name: 'admin_seo_export_csv')]
    public function exportSeoCsv(
        SeoKeywordRepository $seoKeywordRepository,
        SeoPositionRepository $seoPositionRepository,
        SeoDailyTotalRepository $dailyTotalRepository
    ): Response {
        $now = new \DateTimeImmutable();
        $since = new \DateTimeImmutable('-90 days');
        $thirtyDaysAgo = new \DateTimeImmutable('-30 days');
        $sixtyDaysAgo = new \DateTimeImmutable('-60 days');

        // Collect all data
        $googleDailyTotals = $dailyTotalRepository->findByDateRange($since, $now, 'google');
        $bingDailyTotals = $dailyTotalRepository->findByDateRange($since, $now, 'bing');
        $activeKeywords = $seoKeywordRepository->findActiveKeywords();
        $avgPosCurrent = $seoPositionRepository->getAveragePositionsForAllKeywords($thirtyDaysAgo, $now);
        $avgPosPrevious = $seoPositionRepository->getAveragePositionsForAllKeywords($sixtyDaysAgo, $thirtyDaysAgo);
        $rawPositions = $seoPositionRepository->getRawPositionsForActiveKeywords($since, $now);
        $relevanceCounts = $seoKeywordRepository->getRelevanceCounts();
        $firstAppearances = $seoKeywordRepository->getKeywordFirstAppearancesAll();

        // Index first appearances by keyword ID
        $firstSeenMap = [];
        foreach ($firstAppearances as $fa) {
            $firstSeenMap[$fa['id']] = $fa['firstSeen'];
        }

        // Index daily totals by date
        $googleByDate = [];
        foreach ($googleDailyTotals as $t) {
            $googleByDate[$t->getDate()->format('Y-m-d')] = $t;
        }
        $bingByDate = [];
        foreach ($bingDailyTotals as $t) {
            $bingByDate[$t->getDate()->format('Y-m-d')] = $t;
        }

        // === 1. Totaux journaliers ===
        $dailyRows = [];
        $currentDate = $since;
        while ($currentDate <= $now) {
            $dateKey = $currentDate->format('Y-m-d');
            $g = $googleByDate[$dateKey] ?? null;
            $b = $bingByDate[$dateKey] ?? null;
            $gClicks = $g ? $g->getClicks() : 0;
            $gImpressions = $g ? $g->getImpressions() : 0;
            $gPosition = $g ? round($g->getPosition(), 1) : '';
            $bClicks = $b ? $b->getClicks() : 0;
            $bImpressions = $b ? $b->getImpressions() : 0;
            $bPosition = $b ? round($b->getPosition(), 1) : '';
            $dailyRows[] = [
                $dateKey,
                $gClicks, $gImpressions, $gPosition,
                $bClicks, $bImpressions, $bPosition,
                $gClicks + $bClicks,
                $gImpressions + $bImpressions,
            ];
            $currentDate = $currentDate->modify('+1 day');
        }

        // === 2. Totaux hebdomadaires ===
        $weeklyData = [];
        foreach ($dailyRows as $row) {
            $date = new \DateTimeImmutable($row[0]);
            $weekKey = $date->format('o-W');
            if (!isset($weeklyData[$weekKey])) {
                $weeklyData[$weekKey] = ['clicks' => 0, 'impressions' => 0, 'positions' => [], 'dates' => []];
            }
            $weeklyData[$weekKey]['clicks'] += $row[7];
            $weeklyData[$weekKey]['impressions'] += $row[8];
            if ($row[3] !== '') {
                $weeklyData[$weekKey]['positions'][] = (float) $row[3];
            }
            if ($row[6] !== '') {
                $weeklyData[$weekKey]['positions'][] = (float) $row[6];
            }
            $weeklyData[$weekKey]['dates'][] = $row[0];
        }
        $weeklyRows = [];
        foreach ($weeklyData as $weekKey => $w) {
            $avgPos = !empty($w['positions']) ? round(array_sum($w['positions']) / count($w['positions']), 1) : '';
            $ctr = $w['impressions'] > 0 ? round(($w['clicks'] / $w['impressions']) * 100, 2) : 0;
            $weeklyRows[] = [
                $weekKey,
                min($w['dates']),
                max($w['dates']),
                $w['clicks'],
                $w['impressions'],
                $avgPos,
                $ctr,
            ];
        }

        // === 3. Keywords résumé ===
        $keywordMap = [];
        foreach ($activeKeywords as $kw) {
            $keywordMap[$kw->getId()] = $kw;
        }
        $keywordRows = [];
        foreach ($activeKeywords as $kw) {
            $id = $kw->getId();
            $curr = $avgPosCurrent[$id] ?? null;
            $prev = $avgPosPrevious[$id] ?? null;
            $posCurr = $curr ? $curr['avgPosition'] : '';
            $posPrev = $prev ? $prev['avgPosition'] : '';
            $varPos = ($posCurr !== '' && $posPrev !== '') ? round($posPrev - $posCurr, 1) : '';
            $clicksCurr = $curr ? $curr['totalClicks'] : 0;
            $clicksPrev = $prev ? $prev['totalClicks'] : 0;
            $impressionsCurr = $curr ? $curr['totalImpressions'] : 0;
            $impressionsPrev = $prev ? $prev['totalImpressions'] : 0;
            $varClicks = $clicksPrev > 0 ? round((($clicksCurr - $clicksPrev) / $clicksPrev) * 100, 1) : '';
            $keywordRows[] = [
                $kw->getKeyword(),
                $kw->getTargetUrl() ?? '',
                $kw->getSourceLabel(),
                $kw->getRelevanceScore(),
                $kw->getRelevanceLevel(),
                $posCurr,
                $posPrev,
                $varPos,
                $clicksCurr,
                $impressionsCurr,
                $clicksPrev,
                $impressionsPrev,
                $varClicks,
                $firstSeenMap[$id] ?? '',
                $kw->isActive() ? 'actif' : 'inactif',
            ];
        }

        // === 4. Keywords historique journalier ===
        $histRows = [];
        foreach ($rawPositions as $keywordId => $dates) {
            $kw = $keywordMap[$keywordId] ?? null;
            $kwName = $kw ? $kw->getKeyword() : "keyword_$keywordId";
            foreach ($dates as $dateKey => $d) {
                $ctr = $d['impressions'] > 0 ? round(($d['clicks'] / $d['impressions']) * 100, 2) : 0;
                $histRows[] = [
                    $dateKey,
                    $kwName,
                    round($d['position'], 1),
                    $d['clicks'],
                    $d['impressions'],
                    $ctr,
                ];
            }
        }
        // Sort by date then keyword
        usort($histRows, fn($a, $b) => $a[0] <=> $b[0] ?: $a[1] <=> $b[1]);

        // === 5. Top movers ===
        $movers = [];
        foreach ($keywordRows as $row) {
            if ($row[5] !== '' && $row[6] !== '') {
                $movers[] = [
                    'keyword' => $row[0],
                    'posCurr' => $row[5],
                    'posPrev' => $row[6],
                    'varPos' => $row[7],
                    'clicksCurr' => $row[8],
                    'clicksPrev' => $row[10],
                ];
            }
        }
        // Sort by position variation (biggest improvement = highest positive value first)
        usort($movers, fn($a, $b) => $b['varPos'] <=> $a['varPos']);
        $topImproved = array_slice($movers, 0, 25);
        $topDeclined = array_slice(array_reverse($movers), 0, 25);
        $topMoversRows = [];
        foreach ($topImproved as $m) {
            $topMoversRows[] = [$m['keyword'], $m['posCurr'], $m['posPrev'], $m['varPos'], $m['clicksCurr'], $m['clicksPrev'], 'improved'];
        }
        foreach ($topDeclined as $m) {
            if ($m['varPos'] >= 0) {
                continue; // skip if already in improved
            }
            $topMoversRows[] = [$m['keyword'], $m['posCurr'], $m['posPrev'], $m['varPos'], $m['clicksCurr'], $m['clicksPrev'], 'declined'];
        }

        // === 6. Meta ===
        $relevanceMap = [];
        foreach ($relevanceCounts as $rc) {
            $relevanceMap[(int) $rc['relevanceScore']] = (int) $rc['cnt'];
        }
        $totalClicks30j = 0;
        $totalImpressions30j = 0;
        foreach ($avgPosCurrent as $d) {
            $totalClicks30j += $d['totalClicks'];
            $totalImpressions30j += $d['totalImpressions'];
        }
        $kwPage1 = 0;
        foreach ($avgPosCurrent as $d) {
            if ($d['avgPosition'] <= 10) {
                $kwPage1++;
            }
        }
        $totalActive = count($activeKeywords);
        $pctPage1 = $totalActive > 0 ? round(($kwPage1 / $totalActive) * 100, 1) : 0;
        $kw4plus = 0;
        $kw5 = 0;
        foreach ($relevanceMap as $score => $cnt) {
            if ($score >= 4) {
                $kw4plus += $cnt;
            }
            if ($score === 5) {
                $kw5 += $cnt;
            }
        }
        $metaRows = [
            ['Date export', $now->format('Y-m-d H:i')],
            ['Periode couverte', $since->format('Y-m-d') . ' a ' . $now->format('Y-m-d')],
            ['Nombre keywords actifs', $totalActive],
            ['Nombre keywords 4*+', $kw4plus],
            ['Nombre keywords 5*', $kw5],
            ['Total clics 30j', $totalClicks30j],
            ['Total impressions 30j', $totalImpressions30j],
            ['% keywords page 1', $pctPage1 . '%'],
        ];

        // Build ZIP
        $tmpFile = tempnam(sys_get_temp_dir(), 'seo_');
        $zip = new \ZipArchive();
        $zip->open($tmpFile, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

        $zip->addFromString('1_totaux_journaliers.csv', $this->generateCsvString(
            ['Date', 'Clics Google', 'Impressions Google', 'Position moy Google', 'Clics Bing', 'Impressions Bing', 'Position moy Bing', 'Total Clics', 'Total Impressions'],
            $dailyRows
        ));
        $zip->addFromString('2_totaux_hebdomadaires.csv', $this->generateCsvString(
            ['Semaine', 'Date debut', 'Date fin', 'Clics', 'Impressions', 'Position moy', 'CTR estime'],
            $weeklyRows
        ));
        $zip->addFromString('3_keywords_resume.csv', $this->generateCsvString(
            ['Mot-cle', 'URL cible', 'Source', 'Pertinence (0-5)', 'Niveau', 'Position moy (30j)', 'Position moy (30j prec.)', 'Variation position', 'Clics (30j)', 'Impressions (30j)', 'Clics (30j prec.)', 'Impressions (30j prec.)', 'Variation clics %', 'Premiere apparition', 'Statut'],
            $keywordRows
        ));
        $zip->addFromString('4_keywords_historique_journalier.csv', $this->generateCsvString(
            ['Date', 'Mot-cle', 'Position', 'Clics', 'Impressions', 'CTR'],
            $histRows
        ));
        $zip->addFromString('5_top_movers.csv', $this->generateCsvString(
            ['Mot-cle', 'Position actuelle', 'Position precedente', 'Variation', 'Clics actuels', 'Clics precedents', 'Direction'],
            $topMoversRows
        ));
        $zip->addFromString('6_meta.csv', $this->generateCsvString(
            ['Parametre', 'Valeur'],
            $metaRows
        ));

        $zip->close();

        $response = new BinaryFileResponse($tmpFile);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, 'seo_export_' . date('Y-m-d') . '.zip');
        $response->deleteFileAfterSend(true);

        return $response;
    }

    #[Route('/saeiblauhjc/client-seo-keyword/{id}/set-score', name: 'admin_client_seo_keyword_set_score', methods: ['POST'])]
    public function setClientKeywordScore(ClientSeoKeyword $keyword, Request $request): JsonResponse
    {
        if (!$this->isCsrfTokenValid('client-seo-score-' . $keyword->getId(), $request->request->get('_token'))) {
            return new JsonResponse(['error' => 'Token CSRF invalide'], 403);
        }

        $score = (int) $request->request->get('score', 0);
        $keyword->setRelevanceScore($score);
        $this->entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'score' => $keyword->getRelevanceScore(),
        ]);
    }

    #[Route('/saeiblauhjc/client-seo-keyword/{id}/toggle-active', name: 'admin_client_seo_keyword_toggle_active', methods: ['POST'])]
    public function toggleClientKeywordActive(ClientSeoKeyword $keyword, Request $request): JsonResponse
    {
        if (!$this->isCsrfTokenValid('client-seo-toggle-' . $keyword->getId(), $request->request->get('_token'))) {
            return new JsonResponse(['error' => 'Token CSRF invalide'], 403);
        }

        $newActive = !$keyword->isActive();
        $keyword->setIsActive($newActive);

        if ($newActive) {
            $keyword->setDeactivatedAt(null);
        } else {
            $keyword->setDeactivatedAt(new \DateTimeImmutable());
        }

        $this->entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'isActive' => $newActive,
        ]);
    }

    #[Route('/saeiblauhjc/client-seo-keyword/{id}/mark-optimized', name: 'admin_client_seo_keyword_mark_optimized', methods: ['POST'])]
    public function markClientKeywordOptimized(ClientSeoKeyword $keyword, Request $request): JsonResponse
    {
        if (!$this->isCsrfTokenValid('client-seo-optimize-' . $keyword->getId(), $request->request->get('_token'))) {
            return new JsonResponse(['error' => 'Token CSRF invalide'], 403);
        }

        $keyword->setLastOptimizedAt(new \DateTimeImmutable());
        $this->entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'date' => $keyword->getLastOptimizedAt()->format('d/m/Y'),
        ]);
    }

    // ===== BING CONFIG =====

    #[Route('/saeiblauhjc/bing/config', name: 'admin_bing_config')]
    public function bingConfig(
        Request $request,
        BingConfigRepository $bingConfigRepository,
    ): Response {
        $config = $bingConfigRepository->getOrCreate();

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('bing-config', $request->request->get('_token'))) {
                $this->addFlash('danger', 'Token CSRF invalide.');
                return $this->redirectToRoute('admin_bing_config');
            }

            $config->setSiteUrl($request->request->get('site_url') ?: null);
            $config->setGscSiteUrl($request->request->get('gsc_site_url') ?: null);
            $config->setApiKey($request->request->get('api_key') ?: null);
            $config->setIndexNowKey($request->request->get('indexnow_key') ?: null);
            $config->setUpdatedAt(new \DateTimeImmutable());
            $this->entityManager->flush();

            $this->addFlash('success', 'Configuration Bing mise a jour.');
            return $this->redirectToRoute('admin_bing_config');
        }

        return $this->render('admin/bing/config.html.twig', [
            'config' => $config,
        ]);
    }

    // ===== CLIENT SEO =====

    #[Route('/saeiblauhjc/client-seo', name: 'admin_client_seo_list')]
    public function clientSeoList(
        ClientSiteRepository $clientSiteRepository,
        ClientSeoDashboardService $clientSeoDashboardService
    ): Response {
        $sites = $clientSiteRepository->findAllActive();

        $summaries = [];
        foreach ($sites as $site) {
            $summaries[$site->getId()] = $clientSeoDashboardService->getSummaryData($site);
        }

        return $this->render('admin/client_seo/list.html.twig', [
            'sites' => $sites,
            'summaries' => $summaries,
        ]);
    }

    #[Route('/saeiblauhjc/client-seo/add-site', name: 'admin_client_seo_add_site')]
    public function clientSeoAddSite(Request $request): Response
    {
        $site = new ClientSite();
        $form = $this->createForm(ClientSiteType::class, $site);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->persist($site);
            $this->entityManager->flush();

            $this->addFlash('success', sprintf('Site "%s" ajoute avec succes.', $site->getName()));
            return $this->redirectToRoute('admin_client_seo_list');
        }

        return $this->render('admin/client_seo/add_site.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/saeiblauhjc/client-seo/{id}/dashboard', name: 'admin_client_seo_dashboard')]
    public function clientSeoDashboard(ClientSite $site, ClientSeoDashboardService $clientSeoDashboardService): Response
    {
        $data = $clientSeoDashboardService->getFullData($site);

        return $this->render('admin/client_seo/dashboard.html.twig', array_merge(
            $data,
            ['site' => $site]
        ));
    }

    #[Route('/saeiblauhjc/client-seo/{id}/share-card', name: 'admin_client_seo_share_card')]
    public function clientSeoShareCard(ClientSite $site, ClientSeoDashboardService $clientSeoDashboardService): Response
    {
        $data = $clientSeoDashboardService->getFullData($site);
        $data['shareMode'] = true;
        $data['site'] = $site;

        return $this->render('admin/client_seo/share_card.html.twig', $data);
    }

    #[Route('/saeiblauhjc/client-seo/{id}/imports', name: 'admin_client_seo_import_history')]
    public function clientSeoImportHistory(ClientSite $site, ClientSeoImportRepository $importRepository): Response
    {
        $imports = $importRepository->findByClientSite($site);

        return $this->render('admin/client_seo/import_history.html.twig', [
            'site' => $site,
            'imports' => $imports,
        ]);
    }

    #[Route('/saeiblauhjc/client-seo/{id}/edit', name: 'admin_client_seo_edit_site')]
    public function clientSeoEditSite(Request $request, ClientSite $site): Response
    {
        $form = $this->createForm(ClientSiteType::class, $site);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $site->setUpdatedAt(new \DateTimeImmutable());
            $this->entityManager->flush();

            $this->addFlash('success', sprintf('Site "%s" mis a jour.', $site->getName()));
            return $this->redirectToRoute('admin_client_seo_dashboard', ['id' => $site->getId()]);
        }

        return $this->render('admin/client_seo/add_site.html.twig', [
            'form' => $form,
            'site' => $site,
        ]);
    }

    #[Route('/saeiblauhjc/client-seo/{id}/export-csv', name: 'admin_client_seo_export_csv')]
    public function clientSeoExportCsv(
        ClientSite $site,
        ClientSeoKeywordRepository $clientKeywordRepository,
        ClientSeoPositionRepository $clientPositionRepository,
        ClientSeoDailyTotalRepository $clientDailyTotalRepository
    ): Response {
        $now = new \DateTimeImmutable();
        $since = new \DateTimeImmutable('-90 days');
        $thirtyDaysAgo = new \DateTimeImmutable('-30 days');
        $sixtyDaysAgo = new \DateTimeImmutable('-60 days');

        // Collect all data
        $googleDailyTotals = $clientDailyTotalRepository->findByDateRangeAndSource($site, $since, $now, 'google');
        $bingDailyTotals = $clientDailyTotalRepository->findByDateRangeAndSource($site, $since, $now, 'bing');
        $activeKeywords = $clientKeywordRepository->findByClientSite($site);
        $avgPosCurrent = $clientPositionRepository->getAveragePositionsForAllKeywords($site, $thirtyDaysAgo, $now);
        $avgPosPrevious = $clientPositionRepository->getAveragePositionsForAllKeywords($site, $sixtyDaysAgo, $thirtyDaysAgo);
        $rawPositions = $clientPositionRepository->getRawPositionsForActiveKeywords($site, $since, $now);
        $relevanceCounts = $clientKeywordRepository->getRelevanceCounts($site);
        $firstAppearances = $clientKeywordRepository->getKeywordFirstAppearancesAll($site);

        // Index first appearances by keyword ID
        $firstSeenMap = [];
        foreach ($firstAppearances as $fa) {
            $firstSeenMap[$fa['id']] = $fa['firstSeen'];
        }

        // Index daily totals by date
        $googleByDate = [];
        foreach ($googleDailyTotals as $t) {
            $googleByDate[$t->getDate()->format('Y-m-d')] = $t;
        }
        $bingByDate = [];
        foreach ($bingDailyTotals as $t) {
            $bingByDate[$t->getDate()->format('Y-m-d')] = $t;
        }

        // === 1. Totaux journaliers ===
        $dailyRows = [];
        $currentDate = $since;
        while ($currentDate <= $now) {
            $dateKey = $currentDate->format('Y-m-d');
            $g = $googleByDate[$dateKey] ?? null;
            $b = $bingByDate[$dateKey] ?? null;
            $gClicks = $g ? $g->getClicks() : 0;
            $gImpressions = $g ? $g->getImpressions() : 0;
            $gPosition = $g ? round($g->getPosition(), 1) : '';
            $bClicks = $b ? $b->getClicks() : 0;
            $bImpressions = $b ? $b->getImpressions() : 0;
            $bPosition = $b ? round($b->getPosition(), 1) : '';
            $dailyRows[] = [
                $dateKey,
                $gClicks, $gImpressions, $gPosition,
                $bClicks, $bImpressions, $bPosition,
                $gClicks + $bClicks,
                $gImpressions + $bImpressions,
            ];
            $currentDate = $currentDate->modify('+1 day');
        }

        // === 2. Totaux hebdomadaires ===
        $weeklyData = [];
        foreach ($dailyRows as $row) {
            $date = new \DateTimeImmutable($row[0]);
            $weekKey = $date->format('o-W');
            if (!isset($weeklyData[$weekKey])) {
                $weeklyData[$weekKey] = ['clicks' => 0, 'impressions' => 0, 'positions' => [], 'dates' => []];
            }
            $weeklyData[$weekKey]['clicks'] += $row[7];
            $weeklyData[$weekKey]['impressions'] += $row[8];
            if ($row[3] !== '') {
                $weeklyData[$weekKey]['positions'][] = (float) $row[3];
            }
            if ($row[6] !== '') {
                $weeklyData[$weekKey]['positions'][] = (float) $row[6];
            }
            $weeklyData[$weekKey]['dates'][] = $row[0];
        }
        $weeklyRows = [];
        foreach ($weeklyData as $weekKey => $w) {
            $avgPos = !empty($w['positions']) ? round(array_sum($w['positions']) / count($w['positions']), 1) : '';
            $ctr = $w['impressions'] > 0 ? round(($w['clicks'] / $w['impressions']) * 100, 2) : 0;
            $weeklyRows[] = [
                $weekKey,
                min($w['dates']),
                max($w['dates']),
                $w['clicks'],
                $w['impressions'],
                $avgPos,
                $ctr,
            ];
        }

        // === 3. Keywords résumé ===
        $keywordMap = [];
        foreach ($activeKeywords as $kw) {
            $keywordMap[$kw->getId()] = $kw;
        }
        $keywordRows = [];
        foreach ($activeKeywords as $kw) {
            $id = $kw->getId();
            $curr = $avgPosCurrent[$id] ?? null;
            $prev = $avgPosPrevious[$id] ?? null;
            $posCurr = $curr ? $curr['avgPosition'] : '';
            $posPrev = $prev ? $prev['avgPosition'] : '';
            $varPos = ($posCurr !== '' && $posPrev !== '') ? round($posPrev - $posCurr, 1) : '';
            $clicksCurr = $curr ? $curr['totalClicks'] : 0;
            $clicksPrev = $prev ? $prev['totalClicks'] : 0;
            $impressionsCurr = $curr ? $curr['totalImpressions'] : 0;
            $impressionsPrev = $prev ? $prev['totalImpressions'] : 0;
            $varClicks = $clicksPrev > 0 ? round((($clicksCurr - $clicksPrev) / $clicksPrev) * 100, 1) : '';
            $keywordRows[] = [
                $kw->getKeyword(),
                $kw->getRelevanceScore(),
                $kw->getRelevanceLevel(),
                $posCurr,
                $posPrev,
                $varPos,
                $clicksCurr,
                $impressionsCurr,
                $clicksPrev,
                $impressionsPrev,
                $varClicks,
                $firstSeenMap[$id] ?? '',
                $kw->isActive() ? 'actif' : 'inactif',
            ];
        }

        // === 4. Keywords historique journalier ===
        $histRows = [];
        foreach ($rawPositions as $keywordId => $dates) {
            $kw = $keywordMap[$keywordId] ?? null;
            $kwName = $kw ? $kw->getKeyword() : "keyword_$keywordId";
            foreach ($dates as $dateKey => $d) {
                $ctr = $d['impressions'] > 0 ? round(($d['clicks'] / $d['impressions']) * 100, 2) : 0;
                $histRows[] = [
                    $dateKey,
                    $kwName,
                    round($d['position'], 1),
                    $d['clicks'],
                    $d['impressions'],
                    $ctr,
                ];
            }
        }
        usort($histRows, fn($a, $b) => $a[0] <=> $b[0] ?: $a[1] <=> $b[1]);

        // === 5. Top movers ===
        $movers = [];
        foreach ($keywordRows as $row) {
            if ($row[3] !== '' && $row[4] !== '') {
                $movers[] = [
                    'keyword' => $row[0],
                    'posCurr' => $row[3],
                    'posPrev' => $row[4],
                    'varPos' => $row[5],
                    'clicksCurr' => $row[6],
                    'clicksPrev' => $row[8],
                ];
            }
        }
        usort($movers, fn($a, $b) => $b['varPos'] <=> $a['varPos']);
        $topImproved = array_slice($movers, 0, 25);
        $topDeclined = array_slice(array_reverse($movers), 0, 25);
        $topMoversRows = [];
        foreach ($topImproved as $m) {
            $topMoversRows[] = [$m['keyword'], $m['posCurr'], $m['posPrev'], $m['varPos'], $m['clicksCurr'], $m['clicksPrev'], 'improved'];
        }
        foreach ($topDeclined as $m) {
            if ($m['varPos'] >= 0) {
                continue;
            }
            $topMoversRows[] = [$m['keyword'], $m['posCurr'], $m['posPrev'], $m['varPos'], $m['clicksCurr'], $m['clicksPrev'], 'declined'];
        }

        // === 6. Meta ===
        $relevanceMap = [];
        foreach ($relevanceCounts as $rc) {
            $relevanceMap[(int) $rc['relevanceScore']] = (int) $rc['cnt'];
        }
        $totalClicks30j = 0;
        $totalImpressions30j = 0;
        foreach ($avgPosCurrent as $d) {
            $totalClicks30j += $d['totalClicks'];
            $totalImpressions30j += $d['totalImpressions'];
        }
        $kwPage1 = 0;
        foreach ($avgPosCurrent as $d) {
            if ($d['avgPosition'] <= 10) {
                $kwPage1++;
            }
        }
        $totalActive = count($activeKeywords);
        $pctPage1 = $totalActive > 0 ? round(($kwPage1 / $totalActive) * 100, 1) : 0;
        $kw4plus = 0;
        $kw5 = 0;
        foreach ($relevanceMap as $score => $cnt) {
            if ($score >= 4) {
                $kw4plus += $cnt;
            }
            if ($score === 5) {
                $kw5 += $cnt;
            }
        }
        $metaRows = [
            ['Site', $site->getName()],
            ['Date export', $now->format('Y-m-d H:i')],
            ['Periode couverte', $since->format('Y-m-d') . ' a ' . $now->format('Y-m-d')],
            ['Nombre keywords actifs', $totalActive],
            ['Nombre keywords 4*+', $kw4plus],
            ['Nombre keywords 5*', $kw5],
            ['Total clics 30j', $totalClicks30j],
            ['Total impressions 30j', $totalImpressions30j],
            ['% keywords page 1', $pctPage1 . '%'],
        ];

        // Build ZIP
        $tmpFile = tempnam(sys_get_temp_dir(), 'seo_client_');
        $zip = new \ZipArchive();
        $zip->open($tmpFile, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

        $zip->addFromString('1_totaux_journaliers.csv', $this->generateCsvString(
            ['Date', 'Clics Google', 'Impressions Google', 'Position moy Google', 'Clics Bing', 'Impressions Bing', 'Position moy Bing', 'Total Clics', 'Total Impressions'],
            $dailyRows
        ));
        $zip->addFromString('2_totaux_hebdomadaires.csv', $this->generateCsvString(
            ['Semaine', 'Date debut', 'Date fin', 'Clics', 'Impressions', 'Position moy', 'CTR estime'],
            $weeklyRows
        ));
        $zip->addFromString('3_keywords_resume.csv', $this->generateCsvString(
            ['Mot-cle', 'Pertinence (0-5)', 'Niveau', 'Position moy (30j)', 'Position moy (30j prec.)', 'Variation position', 'Clics (30j)', 'Impressions (30j)', 'Clics (30j prec.)', 'Impressions (30j prec.)', 'Variation clics %', 'Premiere apparition', 'Statut'],
            $keywordRows
        ));
        $zip->addFromString('4_keywords_historique_journalier.csv', $this->generateCsvString(
            ['Date', 'Mot-cle', 'Position', 'Clics', 'Impressions', 'CTR'],
            $histRows
        ));
        $zip->addFromString('5_top_movers.csv', $this->generateCsvString(
            ['Mot-cle', 'Position actuelle', 'Position precedente', 'Variation', 'Clics actuels', 'Clics precedents', 'Direction'],
            $topMoversRows
        ));
        $zip->addFromString('6_meta.csv', $this->generateCsvString(
            ['Parametre', 'Valeur'],
            $metaRows
        ));

        $zip->close();

        $siteName = preg_replace('/[^a-z0-9]/', '_', strtolower($site->getName()));
        $response = new BinaryFileResponse($tmpFile);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, "seo_client_{$siteName}_" . date('Y-m-d') . '.zip');
        $response->deleteFileAfterSend(true);

        return $response;
    }

    #[Route('/saeiblauhjc/client-seo/{id}/keywords', name: 'admin_client_seo_keywords')]
    public function clientSeoKeywords(ClientSite $site, ClientSeoKeywordRepository $keywordRepository): Response
    {
        $keywords = $keywordRepository->findAllWithLatestPosition($site);

        return $this->render('admin/client_seo/keywords.html.twig', [
            'site' => $site,
            'keywords' => $keywords,
        ]);
    }

    // ===== CLIENT SEO REPORTS =====

    #[Route('/saeiblauhjc/client-seo/{id}/report/generate', name: 'admin_client_seo_report_generate', methods: ['POST'])]
    public function clientSeoReportGenerate(ClientSite $site, Request $request, ClientSeoReportService $reportService): Response
    {
        if (!$this->isCsrfTokenValid('generate-report-' . $site->getId(), $request->request->get('_token'))) {
            $this->addFlash('danger', 'Token CSRF invalide.');
            return $this->redirectToRoute('admin_client_seo_dashboard', ['id' => $site->getId()]);
        }

        $report = $reportService->generateReport($site);

        return $this->redirectToRoute('admin_client_seo_report_view', ['id' => $report->getId()]);
    }

    #[Route('/saeiblauhjc/client-seo/report/{id}', name: 'admin_client_seo_report_view')]
    public function clientSeoReportView(ClientSeoReport $report): Response
    {
        return $this->render('admin/client_seo/report_view.html.twig', [
            'report' => $report,
            'site' => $report->getClientSite(),
        ]);
    }

    #[Route('/saeiblauhjc/client-seo/report/{id}/save', name: 'admin_client_seo_report_save', methods: ['POST'])]
    public function clientSeoReportSave(ClientSeoReport $report, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (isset($data['actionsHtml'])) {
            $report->setActionsHtml($data['actionsHtml']);
        }
        if (isset($data['nextActionsHtml'])) {
            $report->setNextActionsHtml($data['nextActionsHtml']);
        }
        if (isset($data['notesHtml'])) {
            $report->setNotesHtml($data['notesHtml']);
        }

        $this->entityManager->flush();

        return new JsonResponse(['success' => true]);
    }

    #[Route('/saeiblauhjc/client-seo/report/{id}/mark-sent', name: 'admin_client_seo_report_mark_sent', methods: ['POST'])]
    public function clientSeoReportMarkSent(ClientSeoReport $report, Request $request): JsonResponse
    {
        if (!$this->isCsrfTokenValid('mark-sent-' . $report->getId(), $request->request->get('_token'))) {
            return new JsonResponse(['error' => 'Token CSRF invalide'], 403);
        }

        $report->markSent();
        $this->entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'status' => $report->getStatus(),
            'sentAt' => $report->getSentAt()->format('d/m/Y H:i'),
        ]);
    }

    #[Route('/saeiblauhjc/client-seo/{id}/reports', name: 'admin_client_seo_report_list')]
    public function clientSeoReportList(ClientSite $site, ClientSeoReportRepository $reportRepository): Response
    {
        $reports = $reportRepository->findByClientSite($site);

        return $this->render('admin/client_seo/report_list.html.twig', [
            'site' => $site,
            'reports' => $reports,
        ]);
    }

    #[Route('/saeiblauhjc/seo/report/{id}/delete', name: 'admin_seo_report_delete', methods: ['POST'])]
    public function seoReportDelete(ClientSeoReport $report, Request $request): Response
    {
        $clientSite = $report->getClientSite();

        if (!$this->isCsrfTokenValid('delete-report-' . $report->getId(), $request->request->get('_token'))) {
            $this->addFlash('danger', 'Token CSRF invalide.');
        } else {
            $this->entityManager->remove($report);
            $this->entityManager->flush();
            $this->addFlash('success', 'Compte rendu supprime.');
        }

        if ($clientSite) {
            return $this->redirectToRoute('admin_client_seo_report_list', ['id' => $clientSite->getId()]);
        }

        return $this->redirectToRoute('admin_seo_report_list');
    }

    // ===== OWN SITE SEO REPORTS =====

    #[Route('/saeiblauhjc/seo/report/generate', name: 'admin_seo_report_generate', methods: ['POST'])]
    public function seoReportGenerate(Request $request, SeoReportService $reportService): Response
    {
        if (!$this->isCsrfTokenValid('generate-own-report', $request->request->get('_token'))) {
            $this->addFlash('danger', 'Token CSRF invalide.');
            return $this->redirectToRoute('admin_seo_dashboard');
        }

        $report = $reportService->generateReport();

        return $this->redirectToRoute('admin_client_seo_report_view', ['id' => $report->getId()]);
    }

    #[Route('/saeiblauhjc/seo/reports', name: 'admin_seo_report_list')]
    public function seoReportList(ClientSeoReportRepository $reportRepository): Response
    {
        $reports = $reportRepository->findOwnSiteReports();

        return $this->render('admin/client_seo/report_list.html.twig', [
            'site' => null,
            'reports' => $reports,
        ]);
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
            ->addJsFile('js/admin-trix-headings.js')
            ->addJsFile('js/admin-project-partners.js')
            ->addJsFile('js/admin-toggles.js')
            ->addJsFile('js/admin-char-counter.js')
            ->addJsFile('js/admin-city-optimized.js')
            ->addJsFile('js/admin-star-rating.js')
            ->addJsFile('js/admin-table-filter.js');

        // Charger le CSS de floutage si le mode démo est activé
        if ($this->params->get('app.demo_mode')) {
            $assets->addCssFile('css/admin-blur.css');
        }

        return $assets;
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::section('Tableaux de bord');
        yield MenuItem::linkToRoute('Vue d\'ensemble', 'fa fa-home', 'admin_main_dashboard');
        yield MenuItem::linkToRoute('SEO', 'fa fa-chart-line', 'admin_seo_dashboard');
        yield MenuItem::linkToRoute('Finances', 'fa fa-euro-sign', 'admin_finance_dashboard');
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
        yield MenuItem::linkToCrud('Départements (SEO)', 'fas fa-map', DepartmentPage::class);
        yield MenuItem::linkToCrud('Pages principales', 'fas fa-file-alt', PageOptimization::class);

        yield MenuItem::section('SEO Clients');
        $dueCount = $this->clientSiteRepository->countWithDueActions();
        yield MenuItem::linkToRoute('Suivi SEO', 'fa fa-users-cog', 'admin_client_seo_list')
            ->setBadge($dueCount > 0 ? (string) $dueCount : false, 'warning');

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
        yield MenuItem::linkToRoute('Config Bing', 'fab fa-microsoft', 'admin_bing_config');
        yield MenuItem::linkToCrud('Utilisateurs', 'fas fa-user', User::class);

        if ($this->isGranted('ROLE_USER')) {
            yield MenuItem::section('');
        }
        yield MenuItem::linkToRoute('Retour au site', 'fas fa-external-link-alt', 'app_home');

        if ($this->isGranted('ROLE_USER')) {
            yield MenuItem::linkToLogout('Déconnexion', 'fas fa-sign-out-alt');
        }
    }

    private function generateCsvString(array $headers, array $rows): string
    {
        $output = fopen('php://temp', 'r+');
        fwrite($output, "\xEF\xBB\xBF"); // BOM UTF-8 for Excel
        fputcsv($output, $headers, ';');
        foreach ($rows as $row) {
            fputcsv($output, $row, ';');
        }
        rewind($output);
        $content = stream_get_contents($output);
        fclose($output);

        return $content;
    }
}
