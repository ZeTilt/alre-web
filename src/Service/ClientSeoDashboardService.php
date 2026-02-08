<?php

namespace App\Service;

use App\Entity\ClientSite;
use App\Repository\ClientSeoDailyTotalRepository;
use App\Repository\ClientSeoImportRepository;
use App\Repository\ClientSeoKeywordRepository;
use App\Repository\ClientSeoPageRepository;
use App\Repository\ClientSeoPositionRepository;

class ClientSeoDashboardService
{
    public function __construct(
        private ClientSeoKeywordRepository $keywordRepository,
        private ClientSeoPositionRepository $positionRepository,
        private ClientSeoDailyTotalRepository $dailyTotalRepository,
        private ClientSeoPageRepository $pageRepository,
        private ClientSeoImportRepository $importRepository,
    ) {
    }

    /**
     * Retourne toutes les donnees SEO pour le dashboard complet d'un site client.
     */
    public function getFullData(ClientSite $site): array
    {
        $positionComparisons = $this->calculatePositionComparisons($site);

        return [
            'positionComparisons' => $positionComparisons,
            'keywordsRanked' => $this->rankKeywords($site, $positionComparisons),
            'chartData' => $this->prepareChartData($site),
            'performanceData' => $this->categorizeKeywords($site),
            'topPages' => $this->pageRepository->findTopPages($site, 20),
            'imports' => $this->importRepository->findByClientSite($site),
            'lastImportDate' => $this->importRepository->getLastImportDate($site),
        ];
    }

    /**
     * Retourne un resume des donnees SEO pour la liste des sites clients.
     */
    public function getSummaryData(ClientSite $site): array
    {
        $positionComparisons = $this->calculatePositionComparisons($site);
        $ranked = $this->rankKeywords($site, $positionComparisons);

        $totalActive = $this->keywordRepository->getActiveCount($site);

        return [
            'totalActiveKeywords' => $totalActive,
            'top3' => array_slice($ranked['top10'], 0, 3),
            'positionComparisons' => $positionComparisons,
            'lastImportDate' => $this->importRepository->getLastImportDate($site),
        ];
    }

    /**
     * Classe les mots-cles par score composite (miroir de DashboardSeoService::rankSeoKeywords).
     */
    private function rankKeywords(ClientSite $site, array $comparisons): array
    {
        $keywords = $this->keywordRepository->findAllWithLatestPosition($site);

        $totals = $this->dailyTotalRepository->getAggregatedTotals(
            $site,
            new \DateTimeImmutable('-7 days'),
            new \DateTimeImmutable('today')
        );
        $minImpressions = max(1, $totals['impressions'] * 0.001);

        $scored = [];
        foreach ($keywords as $keyword) {
            if (!$keyword->isActive()) {
                continue;
            }
            $latest = $keyword->getLatestPosition();
            if (!$latest) {
                continue;
            }
            $impressions = $latest->getImpressions();
            if ($impressions < $minImpressions) {
                continue;
            }

            $position = $latest->getPosition();
            $clicks = $latest->getClicks();
            $pageBonus = $position <= 10 ? 1.5 : ($position <= 20 ? 1.25 : 1.0);
            $score = $position > 0 ? ($impressions / $position) * (1 + 2 * $clicks) * $pageBonus : 0;

            $scored[] = ['keyword' => $keyword, 'score' => $score];
        }

        usort($scored, fn($a, $b) => $b['score'] <=> $a['score']);

        $top10 = array_map(fn($item) => $item['keyword'], array_slice($scored, 0, 10));

        $top10Ids = array_map(fn($kw) => $kw->getId(), $top10);
        $toImproveScored = [];
        foreach ($scored as $item) {
            $id = $item['keyword']->getId();
            if (in_array($id, $top10Ids)) {
                continue;
            }
            $comparison = $comparisons[$id] ?? null;
            if ($comparison === null || $comparison['status'] === 'new') {
                continue;
            }
            $variation = $comparison['variation'] ?? 0;
            $velocityFactor = $variation > 10 ? 0.5 : ($variation < -3 ? 1.5 : 1.0);
            $item['adjustedScore'] = $item['score'] * $velocityFactor;
            $toImproveScored[] = $item;
        }
        usort($toImproveScored, fn($a, $b) => $b['adjustedScore'] <=> $a['adjustedScore']);
        $toImprove = array_map(fn($item) => $item['keyword'], array_slice($toImproveScored, 0, 10));

        return ['top10' => $top10, 'toImprove' => $toImprove];
    }

    /**
     * Categorise les mots-cles (miroir de DashboardSeoService::categorizeSeoKeywords).
     */
    private function categorizeKeywords(ClientSite $site): array
    {
        $keywords = $this->keywordRepository->findAllWithLatestPosition($site);

        $allScored = [];
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
            ];

            $pageBonus = $position <= 10 ? 1.5 : ($position <= 20 ? 1.25 : 1.0);
            $score = $position > 0 ? ($impressions / $position) * (1 + 2 * $clicks) * $pageBonus : 0;
            $keywordData['score'] = round($score, 2);
            $allScored[] = $keywordData;

            if ($impressions >= 100 && $ctr < 2) {
                $ctrOpportunities[] = $keywordData;
            }
        }

        $totalImpressions = array_sum(array_column($allScored, 'impressions'));
        $minImpressions = max(1, $totalImpressions * 0.001);
        $significant = array_values(array_filter($allScored, fn($kw) => $kw['impressions'] >= $minImpressions));

        usort($significant, fn($a, $b) => $b['score'] <=> $a['score']);
        usort($ctrOpportunities, fn($a, $b) => $b['impressions'] <=> $a['impressions']);

        return [
            'topPerformers' => array_slice($significant, 0, 5),
            'toImprove' => array_slice($significant, 5, 5),
            'ctrOpportunities' => array_slice($ctrOpportunities, 0, 5),
        ];
    }

    /**
     * Prepare les donnees pour le graphique SEO (miroir de DashboardSeoService::prepareSeoChartData).
     */
    private function prepareChartData(ClientSite $site): array
    {
        $now = new \DateTimeImmutable();
        $dataStartDate = $now->modify('-35 days')->setTime(0, 0, 0);

        $dailyTotals = $this->dailyTotalRepository->findByDateRange($site, $dataStartDate, $now);

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

        // 7-day rolling averages
        $allClicks7d = [];
        $allImpressions7d = [];
        $allCtr7d = [];
        $allPosition7d = [];

        for ($i = 0; $i < count($allClicks); $i++) {
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

        $totalDays = count($allLabels);
        $displayDays = min(30, $totalDays);
        $startIndex = $totalDays - $displayDays;

        return [
            'labels' => array_slice($allLabels, $startIndex),
            'clicks' => array_slice($allClicks, $startIndex),
            'impressions' => array_slice($allImpressions, $startIndex),
            'ctr' => array_slice($allCtr, $startIndex),
            'position' => array_slice($allPosition, $startIndex),
            'clicks7d' => array_slice($allClicks7d, $startIndex),
            'impressions7d' => array_slice($allImpressions7d, $startIndex),
            'ctr7d' => array_slice($allCtr7d, $startIndex),
            'position7d' => array_slice($allPosition7d, $startIndex),
            'hasEnoughData' => $hasEnoughData,
            'daysWithData' => $daysWithData,
        ];
    }

    /**
     * Calcule les comparaisons de positions mois courant vs mois precedent
     * (miroir de DashboardSeoService::calculateSeoPositionComparisons).
     */
    private function calculatePositionComparisons(ClientSite $site): array
    {
        $now = new \DateTimeImmutable();

        $currentMonthStart = $now->modify('first day of this month')->setTime(0, 0, 0);
        $currentMonthEnd = $now->setTime(23, 59, 59);

        $previousMonthStart = $now->modify('first day of last month')->setTime(0, 0, 0);
        $previousMonthEnd = $now->modify('last day of last month')->setTime(23, 59, 59);

        $currentPositions = $this->positionRepository->getAveragePositionsForAllKeywords(
            $site,
            $currentMonthStart,
            $currentMonthEnd
        );
        $previousPositions = $this->positionRepository->getAveragePositionsForAllKeywords(
            $site,
            $previousMonthStart,
            $previousMonthEnd
        );

        $keywords = $this->keywordRepository->findByClientSite($site);

        $comparisons = [];
        foreach ($keywords as $keyword) {
            $keywordId = $keyword->getId();
            $currentData = $currentPositions[$keywordId] ?? null;
            $previousData = $previousPositions[$keywordId] ?? null;

            $currentPosition = $currentData['avgPosition'] ?? null;
            $previousPosition = $previousData['avgPosition'] ?? null;

            $variation = null;
            $status = 'no_data';

            if ($currentPosition !== null && $previousPosition !== null) {
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

}
