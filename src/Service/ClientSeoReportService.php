<?php

namespace App\Service;

use App\Entity\ClientSeoReport;
use App\Entity\ClientSite;
use App\Repository\ClientSeoDailyTotalRepository;
use App\Repository\ClientSeoKeywordRepository;
use App\Repository\ClientSeoPositionRepository;
use Doctrine\ORM\EntityManagerInterface;

class ClientSeoReportService
{
    public function __construct(
        private ClientSeoDashboardService $dashboardService,
        private ClientSeoKeywordRepository $keywordRepository,
        private ClientSeoDailyTotalRepository $dailyTotalRepository,
        private ClientSeoPositionRepository $positionRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function generateReport(ClientSite $site): ClientSeoReport
    {
        $now = new \DateTimeImmutable();
        $periodEnd = $now;
        $periodStart = $now->modify('first day of last month')->setTime(0, 0, 0);

        // Get full dashboard data
        $fullData = $this->dashboardService->getFullData($site);

        // KPI Clicks: current month vs previous month
        $currentMonthStart = $now->modify('first day of this month')->setTime(0, 0, 0);
        $currentMonthEnd = $now->setTime(23, 59, 59);
        $previousMonthStart = $now->modify('first day of last month')->setTime(0, 0, 0);
        $previousMonthEnd = $now->modify('last day of last month')->setTime(23, 59, 59);

        $currentTotals = $this->dailyTotalRepository->getAggregatedTotals($site, $currentMonthStart, $currentMonthEnd);
        $previousTotals = $this->dailyTotalRepository->getAggregatedTotals($site, $previousMonthStart, $previousMonthEnd);

        $clicksCurrent = $currentTotals['clicks'];
        $clicksPrevious = $previousTotals['clicks'];
        $clicksVariation = $clicksPrevious > 0 ? round((($clicksCurrent - $clicksPrevious) / $clicksPrevious) * 100, 1) : 0;

        // KPI Average Position
        $positionCurrent = $currentTotals['avgPosition'];
        $positionPrevious = $previousTotals['avgPosition'];
        $positionVariation = $positionPrevious > 0 ? round($positionPrevious - $positionCurrent, 1) : 0;

        // KPI Keywords Page 1
        $keywords = $this->keywordRepository->findAllWithLatestPosition($site);
        $activeKeywords = array_filter($keywords, fn($kw) => $kw->isActive() && $kw->getLatestPosition());
        $keywordsPage1 = 0;
        $totalActive = 0;
        foreach ($activeKeywords as $kw) {
            $totalActive++;
            $pos = $kw->getLatestPosition()->getPosition();
            if ($pos > 0 && $pos <= 10) {
                $keywordsPage1++;
            }
        }

        // Hero metric: biggest position improvement in top 10
        $heroMetric = $this->computeHeroMetric($fullData['positionComparisons'], $fullData['keywordsRanked']['top10']);

        // Top 5 positions
        $top5 = [];
        $positionComparisons = $fullData['positionComparisons'];
        $top10Keywords = $fullData['keywordsRanked']['top10'];
        foreach (array_slice($top10Keywords, 0, 5) as $kw) {
            $latest = $kw->getLatestPosition();
            $comparison = $positionComparisons[$kw->getId()] ?? null;
            $top5[] = [
                'keyword' => $kw->getKeyword(),
                'position' => $latest ? round($latest->getPosition(), 1) : null,
                'variation' => $comparison['variation'] ?? null,
                'clicks' => $latest ? $latest->getClicks() : 0,
                'impressions' => $latest ? $latest->getImpressions() : 0,
            ];
        }

        // Actions performed (keywords optimized during period)
        $actionsPerformed = [];
        foreach ($keywords as $kw) {
            $optimizedAt = $kw->getLastOptimizedAt();
            if ($optimizedAt === null || $optimizedAt < $periodStart || $optimizedAt > $periodEnd) {
                continue;
            }
            $latest = $kw->getLatestPosition();
            $actionsPerformed[] = [
                'keyword' => $kw->getKeyword(),
                'optimizedAt' => $optimizedAt->format('d/m/Y'),
                'positionNow' => $latest ? round($latest->getPosition(), 1) : null,
            ];
        }

        // Health Score
        $healthScore = $this->computeHealthScore(
            $keywordsPage1,
            $totalActive,
            $clicksCurrent,
            $clicksPrevious,
            $positionCurrent,
            $positionPrevious
        );

        $reportData = [
            'heroMetric' => $heroMetric,
            'kpiClicks' => [
                'current' => $clicksCurrent,
                'previous' => $clicksPrevious,
                'variation' => $clicksVariation,
            ],
            'kpiAvgPosition' => [
                'current' => $positionCurrent,
                'previous' => $positionPrevious,
                'variation' => $positionVariation,
            ],
            'kpiKeywordsPage1' => [
                'count' => $keywordsPage1,
                'total' => $totalActive,
            ],
            'top5Positions' => $top5,
            'actionsPerformed' => $actionsPerformed,
        ];

        $report = new ClientSeoReport();
        $report->setClientSite($site);
        $report->setPeriodStart(\DateTimeImmutable::createFromFormat('Y-m-d', $periodStart->format('Y-m-d')));
        $report->setPeriodEnd(\DateTimeImmutable::createFromFormat('Y-m-d', $periodEnd->format('Y-m-d')));
        $report->setReportData($reportData);
        $report->setHealthScore($healthScore);

        $this->entityManager->persist($report);
        $this->entityManager->flush();

        return $report;
    }

    private function computeHeroMetric(array $positionComparisons, array $top10Keywords): array
    {
        $bestImprovement = null;
        $bestKeyword = null;

        foreach ($top10Keywords as $kw) {
            $comparison = $positionComparisons[$kw->getId()] ?? null;
            if ($comparison === null || $comparison['status'] !== 'improved') {
                continue;
            }
            $variation = $comparison['variation'];
            if ($bestImprovement === null || $variation > $bestImprovement) {
                $bestImprovement = $variation;
                $bestKeyword = $kw->getKeyword();
            }
        }

        if ($bestKeyword !== null) {
            return [
                'label' => 'Meilleure progression',
                'value' => '+' . number_format($bestImprovement, 1) . ' places',
                'comparison' => $bestKeyword,
            ];
        }

        // Fallback: total clicks
        return [
            'label' => 'Mots-cles suivis',
            'value' => (string) count($top10Keywords),
            'comparison' => 'dans le top 10',
        ];
    }

    private function computeHealthScore(
        int $keywordsPage1,
        int $totalActive,
        int $clicksCurrent,
        int $clicksPrevious,
        float $positionCurrent,
        float $positionPrevious
    ): int {
        // Page 1 percentage (max 40 points)
        $page1Pct = $totalActive > 0 ? ($keywordsPage1 / $totalActive) * 100 : 0;
        $page1Points = min(40, (int) round($page1Pct * 0.4));

        // Click trend (30 points)
        if ($clicksPrevious > 0) {
            $clickTrendPct = (($clicksCurrent - $clicksPrevious) / $clicksPrevious) * 100;
        } else {
            $clickTrendPct = $clicksCurrent > 0 ? 100 : 0;
        }
        $clickPoints = match (true) {
            $clickTrendPct > 5 => 30,
            $clickTrendPct >= -5 => 15,
            default => 0,
        };

        // Position trend (30 points) - positive variation = improvement
        $positionVariation = $positionPrevious > 0 ? $positionPrevious - $positionCurrent : 0;
        $positionPoints = match (true) {
            $positionVariation > 0.5 => 30,
            $positionVariation >= -0.5 => 15,
            default => 0,
        };

        return max(0, min(100, $page1Points + $clickPoints + $positionPoints));
    }
}
