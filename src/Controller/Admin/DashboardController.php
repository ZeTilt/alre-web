<?php

namespace App\Controller\Admin;

use App\Entity\Client;
use App\Entity\Company;
use App\Entity\ContactMessage;
use App\Entity\Devis;
use App\Entity\Event;
use App\Entity\Expense;
use App\Entity\Facture;
use App\Entity\User;
use App\Entity\Project;
use App\Entity\Partner;
use App\Entity\Testimonial;
use App\Repository\CompanyRepository;
use App\Repository\DevisRepository;
use App\Repository\EventRepository;
use App\Repository\ExpenseRepository;
use App\Repository\FactureRepository;
use App\Service\ExpenseGenerationService;
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
        private ParameterBagInterface $params
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
        CompanyRepository $companyRepository,
        FactureRepository $factureRepository,
        DevisRepository $devisRepository,
        ExpenseRepository $expenseRepository,
        ExpenseGenerationService $expenseGenerationService
    ): Response {
        // Générer automatiquement les dépenses récurrentes manquantes
        $expenseGenerationService->generateRecurringExpenses();

        // Récupérer les informations de l'entreprise
        $company = $companyRepository->findOneBy([]);

        // Année fiscale en cours
        $year = $company?->getAnneeFiscaleEnCours() ?? (int) date('Y');

        // Période en cours (mois et année)
        $now = new \DateTimeImmutable();
        $startOfMonth = $now->modify('first day of this month')->setTime(0, 0, 0);
        $endOfMonth = $now->modify('last day of this month')->setTime(23, 59, 59);
        $startOfYear = new \DateTimeImmutable($year . '-01-01 00:00:00');
        $endOfYear = new \DateTimeImmutable($year . '-12-31 23:59:59');

        // Période précédente pour comparaison
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

        // ===== DÉPENSES =====

        $depensesMois = $expenseRepository->getTotalExpensesByPeriod($startOfMonth, $endOfMonth);
        $depensesAnnee = $expenseRepository->getTotalExpensesByPeriod($startOfYear, $endOfYear);

        // Bénéfice net (CA - dépenses - cotisations URSSAF)
        $beneficeNetMois = $caMoisEncaisse - $depensesMois - $cotisationsMois;
        $beneficeNetAnnee = $caAnneeEncaisse - $depensesAnnee - $cotisationsEstimees;

        // Marge bénéficiaire (en pourcentage)
        $margeNetMois = $caMoisEncaisse > 0 ? round(($beneficeNetMois / $caMoisEncaisse) * 100, 1) : 0;
        $margeNetAnnee = $caAnneeEncaisse > 0 ? round(($beneficeNetAnnee / $caAnneeEncaisse) * 100, 1) : 0;

        // ===== DONNÉES POUR GRAPHIQUES =====

        // CA mensuel encaissé (pour graphique ligne)
        $caParMois = $factureRepository->getMonthlyPaidRevenueForYear($year);

        // CA facturé par mois (pour comparaison)
        $caFactureParMois = $factureRepository->getMonthlyRevenueForYear($year);

        // Top clients (pour camembert)
        $topClients = $factureRepository->getRevenueByClient($year);
        $topClients = array_slice($topClients, 0, 5); // Top 5 clients

        // Dépenses mensuelles (pour graphique)
        $depensesParMois = $expenseRepository->getMonthlyExpensesForYear($year);

        // Cotisations URSSAF mensuelles (pour graphique)
        $cotisationsParMois = [];
        foreach ($caParMois as $month => $ca) {
            $cotisationsParMois[$month] = round($ca * ($tauxCotisations / 100), 2);
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

            // CA
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
            'caFactureParMois' => $caFactureParMois,
            'topClients' => $topClients,
            'depensesParMois' => $depensesParMois,
            'cotisationsParMois' => $cotisationsParMois,

            // Prévisions
            'projections' => $projections,
            'tendance' => $tendance,

            // Mode démo
            'demoMode' => ($_ENV['APP_DEMO_MODE'] ?? '0') === '1',
        ]);
    }

    #[Route('/saeiblauhjc/calendar', name: 'admin_calendar')]
    public function calendar(EventRepository $eventRepository): Response
    {
        $upcomingEvents = $eventRepository->findUpcoming(7);

        return $this->render('admin/calendar/index.html.twig', [
            'upcomingEvents' => $upcomingEvents,
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
            $data[] = [
                'id' => $event->getId(),
                'title' => $event->getTitle(),
                'start' => $event->getStartAt()->format('c'),
                'end' => $event->getEndAt()?->format('c'),
                'allDay' => $event->isAllDay(),
                'color' => $event->getColor(),
                'extendedProps' => [
                    'type' => $event->getType(),
                    'typeLabel' => $event->getTypeLabel(),
                    'description' => $event->getDescription(),
                    'location' => $event->getLocation(),
                    'clientId' => $event->getClient()?->getId(),
                    'clientName' => $event->getClient()?->getName(),
                ],
            ];
        }

        return new JsonResponse($data);
    }

    #[Route('/saeiblauhjc/dashboard/export-csv', name: 'admin_dashboard_export_csv')]
    public function exportCsv(
        CompanyRepository $companyRepository,
        FactureRepository $factureRepository,
        ExpenseRepository $expenseRepository
    ): Response {
        $company = $companyRepository->findOneBy([]);
        $year = $company?->getAnneeFiscaleEnCours() ?? (int) date('Y');

        // Données mensuelles
        $caParMois = $factureRepository->getMonthlyPaidRevenueForYear($year);
        $depensesParMois = $expenseRepository->getMonthlyExpensesForYear($year);

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
        yield MenuItem::linkToCrud('Messages de contact', 'fas fa-envelope', ContactMessage::class);

        yield MenuItem::section('Gestion commerciale');
        yield MenuItem::linkToCrud('Devis', 'fas fa-file-invoice', Devis::class);
        yield MenuItem::linkToCrud('Factures', 'fas fa-file-invoice-dollar', Facture::class);
        yield MenuItem::linkToCrud('Dépenses', 'fas fa-receipt', Expense::class);
        yield MenuItem::linkToCrud('Événements', 'fas fa-calendar-check', Event::class);

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