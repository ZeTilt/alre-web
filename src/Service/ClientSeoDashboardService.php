<?php

namespace App\Service;

use App\Entity\ClientSite;
use App\Repository\ClientBingDailyTotalRepository;
use App\Repository\ClientBingKeywordRepository;
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
        private ClientBingDailyTotalRepository $bingDailyTotalRepository,
        private ClientBingKeywordRepository $bingKeywordRepository,
    ) {
    }

    /**
     * Retourne toutes les donnees SEO pour le dashboard complet d'un site client.
     */
    public function getFullData(ClientSite $site): array
    {
        $latestDates7 = $this->positionRepository->findLatestDatesWithData($site, 7);
        $now = new \DateTimeImmutable();
        $rawPositions = $this->positionRepository->getRawPositionsForActiveKeywords(
            $site,
            $now->modify('first day of last month')->setTime(0, 0, 0),
            $now
        );

        $activeKeywords = $this->keywordRepository->findByClientSite($site);
        $positionComparisons = $this->calculatePositionComparisons($activeKeywords, $rawPositions);
        $dailyComparisons = $this->calculateDailyComparisons($activeKeywords, $latestDates7, $rawPositions);
        $seoMomentum = $this->calculate7DayMomentum($latestDates7, $rawPositions);
        $seoStability = $this->calculatePositionStability($latestDates7, $rawPositions);

        // Extract latest position per keyword from rawPositions
        $latestPositionData = $this->extractLatestPositions($rawPositions);

        return [
            'positionComparisons' => $positionComparisons,
            'seoMomentum' => $seoMomentum,
            'seoStability' => $seoStability,
            'keywordsRanked' => $this->rankKeywords($site, $positionComparisons, $dailyComparisons, $seoMomentum, $seoStability, $activeKeywords, $latestPositionData),
            'chartData' => $this->prepareChartData($site),
            'keywordsChartData' => $this->prepareKeywordsChartData($site),
            'performanceData' => $this->categorizeKeywords($site),
            'topPages' => $this->pageRepository->findTopPages($site, 20),
            'imports' => $this->importRepository->findByClientSite($site),
            'bingSummary' => $site->isBingEnabled() ? $this->prepareBingSummary($site) : ['hasData' => false],
        ];
    }

    /**
     * Retourne un resume des donnees SEO pour la liste des sites clients.
     */
    public function getSummaryData(ClientSite $site): array
    {
        $latestDates7 = $this->positionRepository->findLatestDatesWithData($site, 7);
        $now = new \DateTimeImmutable();
        $rawPositions = $this->positionRepository->getRawPositionsForActiveKeywords(
            $site,
            $now->modify('first day of last month')->setTime(0, 0, 0),
            $now
        );

        $activeKeywords = $this->keywordRepository->findByClientSite($site);
        $positionComparisons = $this->calculatePositionComparisons($activeKeywords, $rawPositions);
        $dailyComparisons = $this->calculateDailyComparisons($activeKeywords, $latestDates7, $rawPositions);
        $seoMomentum = $this->calculate7DayMomentum($latestDates7, $rawPositions);
        $seoStability = $this->calculatePositionStability($latestDates7, $rawPositions);
        $latestPositionData = $this->extractLatestPositions($rawPositions);
        $ranked = $this->rankKeywords($site, $positionComparisons, $dailyComparisons, $seoMomentum, $seoStability, $activeKeywords, $latestPositionData);

        $totalActive = $this->keywordRepository->getActiveCount($site);

        return [
            'totalActiveKeywords' => $totalActive,
            'top3' => array_slice($ranked['top10'], 0, 3),
            'positionComparisons' => $positionComparisons,
            'toImproveCount' => \count($ranked['toImprove']),
            'reportDue' => $site->isReportDue(),
            'bingEnabled' => $site->isBingEnabled(),
            'bingKeywordCount' => $site->isBingEnabled() ? $this->bingKeywordRepository->getActiveCount($site) : 0,
        ];
    }

    /**
     * Classe les mots-cles par score composite avec CTR benchmarks, momentum 7 jours,
     * stabilite quotidienne, et 4 criteres "A travailler" avec raison/action.
     *
     * @param \App\Entity\ClientSeoKeyword[] $activeKeywords Pre-fetched active keywords
     * @param array<int, array{position: float, clicks: int, impressions: int}> $latestPositionData Latest position per keyword
     * @return array{top10: array, toImprove: array<array{keyword: \App\Entity\ClientSeoKeyword, reason: string, action: string}>}
     */
    private function rankKeywords(ClientSite $site, array $comparisons, array $dailyComparisons, array $momentum, array $stability, array $activeKeywords, array $latestPositionData): array
    {
        // Pre-filter and collect data for relative impression thresholds
        $eligible = [];
        $maxImpressions = 0;

        foreach ($activeKeywords as $keyword) {
            if ($keyword->getRelevanceScore() < 4) {
                continue;
            }
            $keywordId = $keyword->getId();
            $latestData = $latestPositionData[$keywordId] ?? null;
            if (!$latestData) {
                continue;
            }
            $daily = $dailyComparisons[$keywordId] ?? null;
            if ($daily === null || $daily['latestImpressions'] === 0 || $daily['previousImpressions'] === 0) {
                continue;
            }
            $position = $latestData['position'];
            $impressions = $latestData['impressions'];
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
            $keywordId = $keyword->getId();
            $latestData = $latestPositionData[$keywordId];

            $position = $latestData['position'];
            $clicks = $latestData['clicks'];
            $impressions = $latestData['impressions'];

            $ctr = ($clicks / $impressions) * 100;
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

            // Position stability (stddev < 3 for daily data)
            $isStable = $stddev < 3;
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

                // Relevance boost: 5★ gets ×1.15 vs 4★ baseline
                if ($keyword->getRelevanceScore() >= 5) {
                    $improveScore *= 1.15;
                }

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

        $googleTotals = $this->dailyTotalRepository->findByDateRange($site, $dataStartDate, $now);
        $bingTotals = $site->isBingEnabled()
            ? $this->bingDailyTotalRepository->findByDateRange($site, $dataStartDate, $now)
            : [];

        if (empty($googleTotals) && empty($bingTotals)) {
            return [
                'labels' => [],
                'clicks' => [], 'impressions' => [], 'ctr' => [], 'position' => [],
                'googleClicks' => [], 'googleImpressions' => [],
                'bingClicks' => [], 'bingImpressions' => [],
                'clicks7d' => [], 'impressions7d' => [], 'ctr7d' => [], 'position7d' => [],
                'hasEnoughData' => false,
                'daysWithData' => 0,
                'hasBingData' => false,
            ];
        }

        // Build daily maps
        $googleData = [];
        foreach ($googleTotals as $total) {
            $dateKey = $total->getDate()->format('Y-m-d');
            $googleData[$dateKey] = [
                'clicks' => $total->getClicks(),
                'impressions' => $total->getImpressions(),
                'ctr' => $total->getCtr(),
                'position' => $total->getPosition(),
            ];
        }

        $bingData = [];
        foreach ($bingTotals as $total) {
            $dateKey = $total->getDate()->format('Y-m-d');
            $bingData[$dateKey] = [
                'clicks' => $total->getClicks(),
                'impressions' => $total->getImpressions(),
            ];
        }

        // Find date range across both sources
        $allDates = array_unique(array_merge(array_keys($googleData), array_keys($bingData)));
        sort($allDates);

        if (empty($allDates)) {
            return [
                'labels' => [],
                'clicks' => [], 'impressions' => [], 'ctr' => [], 'position' => [],
                'googleClicks' => [], 'googleImpressions' => [],
                'bingClicks' => [], 'bingImpressions' => [],
                'clicks7d' => [], 'impressions7d' => [], 'ctr7d' => [], 'position7d' => [],
                'hasEnoughData' => false,
                'daysWithData' => 0,
                'hasBingData' => false,
            ];
        }

        $firstDataDate = new \DateTimeImmutable(reset($allDates));
        $lastDataDate = new \DateTimeImmutable(end($allDates));

        $daysWithData = count($googleTotals) + count($bingTotals);
        $hasEnoughData = count($googleTotals) >= 7 || count($bingTotals) >= 7;
        $hasBingData = !empty($bingTotals);

        $allLabels = [];
        $allClicks = [];
        $allImpressions = [];
        $allCtr = [];
        $allPosition = [];
        $allGoogleClicks = [];
        $allGoogleImpressions = [];
        $allBingClicks = [];
        $allBingImpressions = [];

        $currentDate = $firstDataDate;
        $endDate = $lastDataDate;

        while ($currentDate <= $endDate) {
            $dateKey = $currentDate->format('Y-m-d');
            $allLabels[] = $currentDate->format('d/m');

            $gClicks = $googleData[$dateKey]['clicks'] ?? 0;
            $gImpressions = $googleData[$dateKey]['impressions'] ?? 0;
            $bClicks = $bingData[$dateKey]['clicks'] ?? 0;
            $bImpressions = $bingData[$dateKey]['impressions'] ?? 0;

            // Combined totals
            $totalClicks = $gClicks + $bClicks;
            $totalImpressions = $gImpressions + $bImpressions;

            $allClicks[] = $totalClicks;
            $allImpressions[] = $totalImpressions;
            $allCtr[] = isset($googleData[$dateKey]) ? $googleData[$dateKey]['ctr'] : ($totalImpressions > 0 ? round(($totalClicks / $totalImpressions) * 100, 2) : null);
            $allPosition[] = $googleData[$dateKey]['position'] ?? null; // Position = Google only (Bing positions are not comparable)

            $allGoogleClicks[] = $gClicks;
            $allGoogleImpressions[] = $gImpressions;
            $allBingClicks[] = $bClicks;
            $allBingImpressions[] = $bImpressions;

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

        // Trim trailing days where no data was collected (all sources = 0)
        while (!empty($allClicks) && end($allClicks) === 0 && end($allImpressions) === 0) {
            array_pop($allLabels);
            array_pop($allClicks);
            array_pop($allImpressions);
            array_pop($allCtr);
            array_pop($allPosition);
            array_pop($allGoogleClicks);
            array_pop($allGoogleImpressions);
            array_pop($allBingClicks);
            array_pop($allBingImpressions);
            array_pop($allClicks7d);
            array_pop($allImpressions7d);
            array_pop($allCtr7d);
            array_pop($allPosition7d);
        }

        $totalDays = \count($allLabels);
        $displayDays = min(30, $totalDays);
        $startIndex = $totalDays - $displayDays;

        return [
            'labels' => array_slice($allLabels, $startIndex),
            'clicks' => array_slice($allClicks, $startIndex),
            'impressions' => array_slice($allImpressions, $startIndex),
            'ctr' => array_slice($allCtr, $startIndex),
            'position' => array_slice($allPosition, $startIndex),
            'googleClicks' => array_slice($allGoogleClicks, $startIndex),
            'googleImpressions' => array_slice($allGoogleImpressions, $startIndex),
            'bingClicks' => array_slice($allBingClicks, $startIndex),
            'bingImpressions' => array_slice($allBingImpressions, $startIndex),
            'clicks7d' => array_slice($allClicks7d, $startIndex),
            'impressions7d' => array_slice($allImpressions7d, $startIndex),
            'ctr7d' => array_slice($allCtr7d, $startIndex),
            'position7d' => array_slice($allPosition7d, $startIndex),
            'hasEnoughData' => $hasEnoughData,
            'daysWithData' => $daysWithData,
            'hasBingData' => $hasBingData,
        ];
    }

    /**
     * Prepare les donnees pour le graphique d'evolution des mots-cles.
     * Categories par score de pertinence (0-5 etoiles) + desactivations.
     */
    private function prepareKeywordsChartData(ClientSite $site): array
    {
        $days = 30;
        $firstAppearances = $this->keywordRepository->getKeywordFirstAppearancesAll($site);
        $relevanceCounts = $this->keywordRepository->getRelevanceCounts($site);
        $deactivations = $this->keywordRepository->getKeywordDeactivations($site);
        $inactiveCount = $this->keywordRepository->countInactive($site);

        $currentTotal = 0;
        $scoreMap = [0 => 0, 1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
        foreach ($relevanceCounts as $row) {
            $score = (int) $row['relevanceScore'];
            $scoreMap[$score] = (int) $row['cnt'];
            $currentTotal += (int) $row['cnt'];
        }

        // Use the last daily total date as end date
        $now = new \DateTimeImmutable();
        $dailyTotals = $this->dailyTotalRepository->findByDateRange($site, $now->modify('-35 days'), $now);
        $lastDataDate = null;
        foreach ($dailyTotals as $total) {
            $date = $total->getDate();
            if ($lastDataDate === null || $date > $lastDataDate) {
                $lastDataDate = $date;
            }
        }

        $endDate = $lastDataDate ?? $now;
        $startDate = $endDate->modify('-' . ($days - 1) . ' days');
        $sinceDate = $startDate->format('Y-m-d');

        $emptyDay = [0 => 0, 1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];

        $baseCount = 0;
        $newByDay = [];
        foreach ($firstAppearances as $row) {
            $firstSeen = $row['firstSeen'];
            $score = (int) $row['relevanceScore'];

            if ($firstSeen < $sinceDate) {
                $baseCount++;
            } else {
                if (!isset($newByDay[$firstSeen])) {
                    $newByDay[$firstSeen] = $emptyDay;
                }
                $newByDay[$firstSeen][$score]++;
            }
        }

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

        $labels = [];
        $new5 = []; $new4 = []; $new3 = []; $new2 = []; $new1 = []; $new0 = [];
        $deactivated = [];
        $totalKeywords = [];
        $cumulative = $baseCount - $baseDeactivated;

        $currentDate = $startDate;
        while ($currentDate <= $endDate) {
            $dateKey = $currentDate->format('Y-m-d');
            $dayData = $newByDay[$dateKey] ?? $emptyDay;
            $dayDeactivated = $deactivatedByDay[$dateKey] ?? 0;
            $dayTotal = array_sum($dayData);

            $cumulative += $dayTotal - $dayDeactivated;

            $labels[] = $currentDate->format('d/m');
            $new5[] = $dayData[5];
            $new4[] = $dayData[4];
            $new3[] = $dayData[3];
            $new2[] = $dayData[2];
            $new1[] = $dayData[1];
            $new0[] = $dayData[0];
            $deactivated[] = $dayDeactivated > 0 ? -$dayDeactivated : 0;
            $totalKeywords[] = $cumulative;

            $currentDate = $currentDate->modify('+1 day');
        }

        return [
            'labels' => $labels,
            'totalKeywords' => $totalKeywords,
            'new5' => $new5,
            'new4' => $new4,
            'new3' => $new3,
            'new2' => $new2,
            'new1' => $new1,
            'new0' => $new0,
            'deactivated' => $deactivated,
            'currentTotal' => $currentTotal,
            'inactiveCount' => $inactiveCount,
            'relevanceCounts' => $scoreMap,
        ];
    }

    /**
     * Prepare un resume des donnees Bing pour un site client (30 derniers jours).
     */
    private function prepareBingSummary(ClientSite $site): array
    {
        $now = new \DateTimeImmutable();
        $startDate = $now->modify('-30 days')->setTime(0, 0, 0);

        $dailyTotals = $this->bingDailyTotalRepository->findByDateRange($site, $startDate, $now);

        if (empty($dailyTotals)) {
            return ['hasData' => false, 'totalClicks' => 0, 'totalImpressions' => 0, 'avgCtr' => 0, 'chartLabels' => [], 'chartClicks' => []];
        }

        $totalClicks = 0;
        $totalImpressions = 0;
        $chartLabels = [];
        $chartClicks = [];

        foreach ($dailyTotals as $total) {
            $totalClicks += $total->getClicks();
            $totalImpressions += $total->getImpressions();
            $chartLabels[] = $total->getDate()->format('d/m');
            $chartClicks[] = $total->getClicks();
        }

        $avgCtr = $totalImpressions > 0 ? round(($totalClicks / $totalImpressions) * 100, 2) : 0;

        return [
            'hasData' => true,
            'totalClicks' => $totalClicks,
            'totalImpressions' => $totalImpressions,
            'avgCtr' => $avgCtr,
            'chartLabels' => $chartLabels,
            'chartClicks' => $chartClicks,
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
     * Calcule le momentum 7 jours : compare la position moyenne des 3 derniers jours
     * avec donnees vs les jours restants (max 4).
     *
     * @param \DateTimeImmutable[] $latestDates7 Pre-fetched latest 7 dates with data
     * @param array<int, array<string, array{position: float, clicks: int, impressions: int}>> $rawPositions
     * @return array<int, array{factor: float, trend: float, status: string}>
     */
    private function calculate7DayMomentum(array $latestDates7, array $rawPositions): array
    {
        if (\count($latestDates7) < 4) {
            return [];
        }

        $recentDates = \array_slice($latestDates7, 0, 3);
        $olderDates = \array_slice($latestDates7, 3);

        $recentStart = end($recentDates)->setTime(0, 0, 0);
        $recentEnd = $recentDates[0]->setTime(23, 59, 59);
        $olderStart = end($olderDates)->setTime(0, 0, 0);
        $olderEnd = $olderDates[0]->setTime(23, 59, 59);

        $recentPositions = $this->aggregatePositions($rawPositions, $recentStart, $recentEnd);
        $olderPositions = $this->aggregatePositions($rawPositions, $olderStart, $olderEnd);

        $momentum = [];
        foreach ($recentPositions as $keywordId => $recentData) {
            $olderData = $olderPositions[$keywordId] ?? null;
            if ($olderData === null) {
                $momentum[$keywordId] = ['factor' => 1.0, 'trend' => 0.0, 'status' => 'new'];
                continue;
            }
            $trend = round($olderData['avgPosition'] - $recentData['avgPosition'], 1);
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
     * Calcule l'ecart-type de position sur les 7 derniers jours avec donnees.
     * Seuil : stddev < 3 = stable (donnees quotidiennes).
     *
     * @param \DateTimeImmutable[] $latestDates7 Pre-fetched latest 7 dates with data
     * @param array<int, array<string, array{position: float, clicks: int, impressions: int}>> $rawPositions
     * @return array<int, float> keywordId => stddev
     */
    private function calculatePositionStability(array $latestDates7, array $rawPositions): array
    {
        if (empty($latestDates7)) {
            return [];
        }

        $startDate = end($latestDates7)->setTime(0, 0, 0);
        $endDate = $latestDates7[0]->setTime(23, 59, 59);

        $history = $this->extractPositionHistory($rawPositions, $startDate, $endDate);

        $stability = [];
        foreach ($history as $keywordId => $positions) {
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
     * Agrege les positions brutes pour une sous-periode.
     *
     * @param array<int, array<string, array{position: float, clicks: int, impressions: int}>> $rawPositions
     * @return array<int, array{keywordId: int, avgPosition: float, totalClicks: int, totalImpressions: int}>
     */
    private function aggregatePositions(array $rawPositions, \DateTimeImmutable $start, \DateTimeImmutable $end): array
    {
        $startKey = $start->format('Y-m-d');
        $endKey = $end->format('Y-m-d');

        $data = [];
        foreach ($rawPositions as $keywordId => $dates) {
            $positions = [];
            $totalClicks = 0;
            $totalImpressions = 0;

            foreach ($dates as $dateKey => $values) {
                if ($dateKey >= $startKey && $dateKey <= $endKey) {
                    $positions[] = $values['position'];
                    $totalClicks += $values['clicks'];
                    $totalImpressions += $values['impressions'];
                }
            }

            if (!empty($positions)) {
                $data[$keywordId] = [
                    'keywordId' => $keywordId,
                    'avgPosition' => round(array_sum($positions) / \count($positions), 1),
                    'totalClicks' => $totalClicks,
                    'totalImpressions' => $totalImpressions,
                ];
            }
        }

        return $data;
    }

    /**
     * Extrait l'historique de positions pour une sous-periode.
     *
     * @param array<int, array<string, array{position: float, clicks: int, impressions: int}>> $rawPositions
     * @return array<int, float[]> keywordId => [position1, position2, ...]
     */
    private function extractPositionHistory(array $rawPositions, \DateTimeImmutable $start, \DateTimeImmutable $end): array
    {
        $startKey = $start->format('Y-m-d');
        $endKey = $end->format('Y-m-d');

        $data = [];
        foreach ($rawPositions as $keywordId => $dates) {
            foreach ($dates as $dateKey => $values) {
                if ($dateKey >= $startKey && $dateKey <= $endKey) {
                    $data[$keywordId][] = $values['position'];
                }
            }
        }

        return $data;
    }

    /**
     * Calcule les comparaisons de positions mois courant vs mois precedent.
     * Utilise les rawPositions pre-fetches (0 requete DB supplementaire).
     *
     * @param \App\Entity\ClientSeoKeyword[] $activeKeywords Pre-fetched active keywords
     * @param array<int, array<string, array{position: float, clicks: int, impressions: int}>> $rawPositions
     * @return array<int, array{currentPosition: ?float, previousPosition: ?float, variation: ?float, status: string}>
     */
    private function calculatePositionComparisons(array $activeKeywords, array $rawPositions): array
    {
        $now = new \DateTimeImmutable();

        $currentMonthStart = $now->modify('first day of this month')->setTime(0, 0, 0);
        $currentMonthEnd = $now->setTime(23, 59, 59);

        $previousMonthStart = $now->modify('first day of last month')->setTime(0, 0, 0);
        $previousMonthEnd = $now->modify('last day of last month')->setTime(23, 59, 59);

        $currentPositions = $this->aggregatePositions($rawPositions, $currentMonthStart, $currentMonthEnd);
        $previousPositions = $this->aggregatePositions($rawPositions, $previousMonthStart, $previousMonthEnd);

        $comparisons = [];
        foreach ($activeKeywords as $keyword) {
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

    /**
     * Calcule les comparaisons de positions entre les 2 derniers jours avec des donnees.
     *
     * @param \App\Entity\ClientSeoKeyword[] $activeKeywords Pre-fetched active keywords
     * @param \DateTimeImmutable[] $latestDates7 Pre-fetched latest 7 dates (first 2 used)
     * @param array<int, array<string, array{position: float, clicks: int, impressions: int}>> $rawPositions
     * @return array<int, array{latestPosition: ?float, previousPosition: ?float, variation: ?float, status: string, latestImpressions: int, previousImpressions: int}>
     */
    private function calculateDailyComparisons(array $activeKeywords, array $latestDates7, array $rawPositions): array
    {
        $latestDates = \array_slice($latestDates7, 0, 2);

        if (\count($latestDates) < 2) {
            $comparisons = [];
            foreach ($activeKeywords as $keyword) {
                $comparisons[$keyword->getId()] = [
                    'latestPosition' => null,
                    'previousPosition' => null,
                    'variation' => null,
                    'status' => 'no_data',
                    'latestImpressions' => 0,
                    'previousImpressions' => 0,
                ];
            }
            return $comparisons;
        }

        $latestDate = $latestDates[0];
        $previousDate = $latestDates[1];

        $latestStart = $latestDate->setTime(0, 0, 0);
        $latestEnd = $latestDate->setTime(23, 59, 59);
        $previousStart = $previousDate->setTime(0, 0, 0);
        $previousEnd = $previousDate->setTime(23, 59, 59);

        $latestPositions = $this->aggregatePositions($rawPositions, $latestStart, $latestEnd);
        $previousPositions = $this->aggregatePositions($rawPositions, $previousStart, $previousEnd);

        $comparisons = [];
        foreach ($activeKeywords as $keyword) {
            $keywordId = $keyword->getId();
            $latestData = $latestPositions[$keywordId] ?? null;
            $previousData = $previousPositions[$keywordId] ?? null;

            $latestPos = $latestData['avgPosition'] ?? null;
            $previousPos = $previousData['avgPosition'] ?? null;

            $variation = null;
            $status = 'no_data';

            if ($latestPos !== null && $previousPos !== null) {
                $variation = round($previousPos - $latestPos, 1);

                if ($variation > 0) {
                    $status = 'improved';
                } elseif ($variation < 0) {
                    $status = 'degraded';
                } else {
                    $status = 'stable';
                }
            } elseif ($latestPos !== null && $previousPos === null) {
                $status = 'new';
            }

            $comparisons[$keywordId] = [
                'latestPosition' => $latestPos,
                'previousPosition' => $previousPos,
                'variation' => $variation,
                'status' => $status,
                'latestImpressions' => (int) ($latestData['totalImpressions'] ?? 0),
                'previousImpressions' => (int) ($previousData['totalImpressions'] ?? 0),
            ];
        }

        return $comparisons;
    }

    /**
     * Extrait la derniere position connue par mot-cle depuis les rawPositions.
     *
     * @param array<int, array<string, array{position: float, clicks: int, impressions: int}>> $rawPositions
     * @return array<int, array{position: float, clicks: int, impressions: int}>
     */
    private function extractLatestPositions(array $rawPositions): array
    {
        $latest = [];
        foreach ($rawPositions as $keywordId => $dates) {
            if (!empty($dates)) {
                $latest[$keywordId] = $dates[array_key_last($dates)];
            }
        }

        return $latest;
    }

}
