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
    private const CTR_BENCHMARKS = [
        1 => 28.0, 2 => 22.0, 3 => 18.0, 4 => 15.0, 5 => 12.0,
        6 => 10.0, 7 => 9.0, 8 => 8.0, 9 => 7.0, 10 => 6.0,
        15 => 3.0, 20 => 2.0, 30 => 0.8, 50 => 0.2,
    ];

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
        $seoMomentum = $this->calculate4WeekMomentum($site);

        return [
            'positionComparisons' => $positionComparisons,
            'seoMomentum' => $seoMomentum,
            'seoStability' => $this->calculateWeeklyPositionStability($site),
            'keywordsRanked' => $this->rankKeywords($site, $positionComparisons, $seoMomentum),
            'chartData' => $this->prepareChartData($site),
            'performanceData' => $this->categorizeKeywords($site),
            'topPages' => $this->pageRepository->findTopPages($site, 20),
            'imports' => $this->importRepository->findByClientSite($site),
            'lastImportDate' => $this->importRepository->getLastImportDate($site),
            'nextImportDate' => $site->getNextImportDate(),
        ];
    }

    /**
     * Retourne un resume des donnees SEO pour la liste des sites clients.
     */
    public function getSummaryData(ClientSite $site): array
    {
        $positionComparisons = $this->calculatePositionComparisons($site);
        $seoMomentum = $this->calculate4WeekMomentum($site);
        $ranked = $this->rankKeywords($site, $positionComparisons, $seoMomentum);

        $totalActive = $this->keywordRepository->getActiveCount($site);

        return [
            'totalActiveKeywords' => $totalActive,
            'top3' => array_slice($ranked['top10'], 0, 3),
            'positionComparisons' => $positionComparisons,
            'lastImportDate' => $this->importRepository->getLastImportDate($site),
            'nextImportDate' => $site->getNextImportDate(),
            'toImproveCount' => \count($ranked['toImprove']),
            'importDue' => $site->isImportDue(),
            'reportDue' => $site->isReportDue(),
        ];
    }

    /**
     * Classe les mots-cles par score composite avec CTR benchmarks, momentum 4 semaines,
     * stabilite hebdomadaire, et 4 criteres "A travailler" avec raison/action.
     *
     * @return array{top10: array, toImprove: array<array{keyword: \App\Entity\ClientSeoKeyword, reason: string, action: string}>}
     */
    private function rankKeywords(ClientSite $site, array $comparisons, array $momentum): array
    {
        $keywords = $this->keywordRepository->findAllWithLatestPosition($site);
        $stability = $this->calculateWeeklyPositionStability($site);

        // Pre-filter and collect data for relative impression thresholds
        $eligible = [];
        $maxImpressions = 0;

        foreach ($keywords as $keyword) {
            if (!$keyword->isActive()) {
                continue;
            }
            $latest = $keyword->getLatestPosition();
            if (!$latest) {
                continue;
            }
            $position = $latest->getPosition();
            $impressions = $latest->getImpressions();
            if ($position <= 0 || $impressions <= 0) {
                continue;
            }
            $maxImpressions = max($maxImpressions, $impressions);
            $eligible[] = $keyword;
        }

        // Relative impression thresholds (adapts to site size)
        $minImprC1 = max(1, (int) round($maxImpressions * 0.10));
        $minImprC2 = max(1, (int) round($maxImpressions * 0.15));
        $minImprC4 = max(1, (int) round($maxImpressions * 0.30));

        $top10Scored = [];
        $improveCandidates = [];

        foreach ($eligible as $keyword) {
            $latest = $keyword->getLatestPosition();
            $keywordId = $keyword->getId();

            $position = $latest->getPosition();
            $clicks = $latest->getClicks();
            $impressions = $latest->getImpressions();

            $ctr = $impressions > 0 ? ($clicks / $impressions) * 100 : 0;
            $expectedCtr = $this->getExpectedCtr($position);
            $ctrRatio = $expectedCtr > 0 ? $ctr / $expectedCtr : 1.0;

            // --- Common factors ---
            $ctrFactor = 0.75 + (min(2.0, max(0.5, $ctrRatio)) - 0.5) * (0.5 / 1.5);
            $momentumFactor = ($momentum[$keywordId] ?? ['factor' => 1.0])['factor'];
            $monthlyVar = $comparisons[$keywordId]['variation'] ?? 0;
            $monthlyVelocity = match (true) {
                $monthlyVar >= 10 => 1.15,
                $monthlyVar >= 5 => 1.08,
                $monthlyVar <= -5 => 0.92,
                default => 1.0,
            };
            $stddev = $stability[$keywordId] ?? 99.0;

            // --- Top 10 Score ---
            $pageBonus = $position <= 10 ? 1.5 : ($position <= 20 ? 1.25 : 1.0);
            $baseScore = ($impressions / $position) * (1 + 2 * $clicks) * $pageBonus;
            $top10Score = $baseScore * $ctrFactor * $momentumFactor * $monthlyVelocity;
            $top10Scored[] = ['keyword' => $keyword, 'score' => $top10Score];

            // --- "A travailler" criteria ---

            // Skip if optimized in last 30 days
            $lastOptimized = $keyword->getLastOptimizedAt();
            if ($lastOptimized !== null && $lastOptimized > (new \DateTimeImmutable())->modify('-30 days')) {
                continue;
            }

            // Position stability (stddev < 2 for weekly averages)
            $isStable = $stddev < 2;
            // Momentum rising = keyword is improving on its own
            $isRising = $momentumFactor >= 1.15;

            $reason = null;
            $action = null;
            $improveScore = 0;

            // Criterion 1: Low CTR on page 1, stable, not rising
            if ($position <= 10 && $ctrRatio < 0.5 && $impressions >= $minImprC1 && $isStable && !$isRising) {
                $expectedClicks = $impressions * ($expectedCtr / 100);
                $improveScore = max(0, $expectedClicks - $clicks) * 3.0;
                $reason = 'CTR faible en page 1';
                $action = 'Optimiser title et meta description';
            }
            // Criterion 2: Near top 10 (position 11-15), stable, not rising
            elseif ($position > 10 && $position <= 15 && $impressions >= $minImprC2 && $isStable && !$isRising) {
                $expectedClicks = $impressions * ($expectedCtr / 100);
                $improveScore = max(0, $expectedClicks - $clicks) * 1.5;
                $reason = 'Proche du top 10';
                $action = 'Enrichir le contenu pour passer en page 1';
            }
            // Criterion 3: Declining (M-1 <= -5), not rising
            elseif ($position <= 20 && $monthlyVar <= -5 && !$isRising) {
                $improveScore = abs($monthlyVar) * $impressions * 0.1;
                $reason = 'En declin (M-1 : ' . round($monthlyVar, 1) . ')';
                $action = 'Analyser la concurrence et rafraichir le contenu';
            }
            // Criterion 4: Page 2 (16-20) high volume, stable, not rising
            elseif ($position > 15 && $position <= 20 && $impressions >= $minImprC4 && $isStable && !$isRising) {
                $expectedClicks = $impressions * ($expectedCtr / 100);
                $improveScore = max(0, $expectedClicks - $clicks) * 0.8;
                $reason = 'Fort volume en page 2';
                $action = 'Backlinks et contenu approfondi';
            }

            if ($reason !== null && $improveScore > 0) {
                // Volume multiplier
                $volumeRatio = $maxImpressions > 0 ? $impressions / $maxImpressions : 0;
                $volumeMultiplier = match (true) {
                    $volumeRatio >= 0.75 => 1.5,
                    $volumeRatio >= 0.40 => 1.2,
                    $volumeRatio >= 0.15 => 1.0,
                    default => 0.8,
                };
                $improveScore *= $volumeMultiplier;

                $improveCandidates[] = [
                    'keyword' => $keyword,
                    'score' => $improveScore,
                    'reason' => $reason,
                    'action' => $action,
                ];
            }
        }

        // Top 10: best visibility scores
        usort($top10Scored, fn($a, $b) => $b['score'] <=> $a['score']);
        $top10 = array_map(fn($item) => $item['keyword'], array_slice($top10Scored, 0, 10));
        $top10Ids = array_map(fn($kw) => $kw->getId(), $top10);

        // To improve: exclude top 10, sort by score, keep reason + action
        $toImproveFiltered = array_filter(
            $improveCandidates,
            fn($item) => !\in_array($item['keyword']->getId(), $top10Ids)
        );
        usort($toImproveFiltered, fn($a, $b) => $b['score'] <=> $a['score']);
        $toImprove = array_map(
            fn($item) => ['keyword' => $item['keyword'], 'reason' => $item['reason'], 'action' => $item['action']],
            array_slice(array_values($toImproveFiltered), 0, 10)
        );

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

    private function getExpectedCtr(float $position): float
    {
        $pos = (int) floor($position);
        if ($pos <= 0) {
            return 0.1;
        }
        if (isset(self::CTR_BENCHMARKS[$pos])) {
            return self::CTR_BENCHMARKS[$pos];
        }
        $lower = 1;
        $upper = 50;
        foreach (array_keys(self::CTR_BENCHMARKS) as $p) {
            if ($p <= $pos && $p > $lower) {
                $lower = $p;
            }
            if ($p >= $pos && $p < $upper) {
                $upper = $p;
            }
        }
        if ($lower === $upper) {
            return self::CTR_BENCHMARKS[$lower];
        }
        $ratio = ($pos - $lower) / ($upper - $lower);
        return self::CTR_BENCHMARKS[$lower] + $ratio * (self::CTR_BENCHMARKS[$upper] - self::CTR_BENCHMARKS[$lower]);
    }

    /**
     * Calcule le momentum 4 semaines : compare la position moyenne des 2 dernieres semaines
     * vs les 2 semaines precedentes (adaptation hebdomadaire du momentum 7 jours perso).
     *
     * @return array<int, array{factor: float, trend: float, status: string}>
     */
    private function calculate4WeekMomentum(ClientSite $site): array
    {
        $weeklyHistory = $this->positionRepository->getPositionHistoryByKeyword($site, 4);

        $momentum = [];
        foreach ($weeklyHistory as $keywordId => $weeklyAverages) {
            $weeks = array_values($weeklyAverages);
            if (\count($weeks) < 3) {
                $momentum[$keywordId] = ['factor' => 1.0, 'trend' => 0.0, 'status' => 'stable'];
                continue;
            }

            // Split: recent 2 weeks vs older weeks
            $totalWeeks = \count($weeks);
            $splitAt = max(1, $totalWeeks - 2);
            $recentWeeks = \array_slice($weeks, $splitAt);
            $olderWeeks = \array_slice($weeks, 0, $splitAt);

            $recentAvg = array_sum($recentWeeks) / \count($recentWeeks);
            $olderAvg = array_sum($olderWeeks) / \count($olderWeeks);

            // Positive = improvement (position goes down)
            $trend = round($olderAvg - $recentAvg, 1);
            $factor = match (true) {
                $trend >= 5 => 1.3,
                $trend >= 2 => 1.15,
                $trend >= -1 => 1.0,
                $trend >= -4 => 0.9,
                default => 0.7,
            };
            $status = match (true) {
                $trend > 0 => 'improved',
                $trend < 0 => 'degraded',
                default => 'stable',
            };
            $momentum[$keywordId] = ['factor' => $factor, 'trend' => $trend, 'status' => $status];
        }

        return $momentum;
    }

    /**
     * Calcule l'ecart-type des positions moyennes hebdomadaires.
     * Seuil : stddev < 2 = stable (les moyennes hebdomadaires sont plus lisses que les quotidiennes).
     *
     * @return array<int, float> keywordId => stddev
     */
    private function calculateWeeklyPositionStability(ClientSite $site): array
    {
        $weeklyHistory = $this->positionRepository->getPositionHistoryByKeyword($site, 4);

        $stability = [];
        foreach ($weeklyHistory as $keywordId => $weeklyAverages) {
            $positions = array_values($weeklyAverages);
            if (\count($positions) < 3) {
                $stability[$keywordId] = 99.0;
                continue;
            }
            $mean = array_sum($positions) / \count($positions);
            $variance = array_sum(array_map(fn($p) => ($p - $mean) ** 2, $positions)) / \count($positions);
            $stability[$keywordId] = round(sqrt($variance), 1);
        }

        return $stability;
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
