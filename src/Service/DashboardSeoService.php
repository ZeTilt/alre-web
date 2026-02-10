<?php

namespace App\Service;

use App\Entity\SeoKeyword;
use App\Repository\GoogleReviewRepository;
use App\Repository\SeoDailyTotalRepository;
use App\Repository\SeoKeywordRepository;
use App\Repository\SeoPositionRepository;
use App\Service\GoogleOAuthService;
use App\Service\GooglePlacesService;
use App\Service\ReviewSyncService;
use App\Service\SeoDataImportService;

class DashboardSeoService
{
    public function __construct(
        private SeoKeywordRepository $seoKeywordRepository,
        private SeoPositionRepository $seoPositionRepository,
        private SeoDailyTotalRepository $seoDailyTotalRepository,
        private GoogleReviewRepository $googleReviewRepository,
        private GoogleOAuthService $googleOAuthService,
        private GooglePlacesService $googlePlacesService,
        private ReviewSyncService $reviewSyncService,
        private SeoDataImportService $seoDataImportService,
    ) {
    }

    /**
     * Returns all SEO data for the full SEO dashboard.
     */
    public function getFullData(): array
    {
        $seoPositionComparisons = $this->calculateSeoPositionComparisons();
        $seoDailyComparisons = $this->calculateSeoDailyComparisons();

        return [
            // Google OAuth
            'googleOAuthConfigured' => $this->googleOAuthService->isConfigured(),
            'googleOAuthConnected' => $this->googleOAuthService->isConnected(),
            'googleOAuthToken' => $this->googleOAuthService->getValidToken(),

            // SEO Sync
            'lastSeoSyncAt' => $this->seoDataImportService->getLastSyncDate(),

            // SEO Position comparisons (monthly + daily)
            'seoPositionComparisons' => $seoPositionComparisons,
            'seoDailyComparisons' => $seoDailyComparisons,

            // SEO Keywords ranked by score
            'seoKeywordsRanked' => $this->rankSeoKeywords($seoPositionComparisons),

            // SEO Chart data (last 30 days)
            'seoChartData' => $this->prepareSeoChartData(),

            // SEO Performance categories
            'seoPerformanceData' => $this->categorizeSeoKeywords(
                $this->seoKeywordRepository->findAllWithLatestPosition()
            ),

            // SEO Keywords Chart
            'seoKeywordsChartData' => $this->prepareSeoKeywordsChartData(),

            // Google Reviews
            'googlePlacesConfigured' => $this->googlePlacesService->isConfigured(),
            'reviewStats' => $this->googleReviewRepository->getStats(),
            'reviewsDataFresh' => $this->reviewSyncService->isDataFresh(),
            'pendingReviews' => $this->googleReviewRepository->findPending(),
            'reviewsApiError' => $this->googlePlacesService->getLastError(),
        ];
    }

    /**
     * Returns a summary of SEO data for the main dashboard overview.
     */
    public function getSummaryData(): array
    {
        $seoPositionComparisons = $this->calculateSeoPositionComparisons();
        $ranked = $this->rankSeoKeywords($seoPositionComparisons);

        // Count active keywords
        $relevanceCounts = $this->seoKeywordRepository->getRelevanceCounts();
        $totalActive = 0;
        foreach ($relevanceCounts as $row) {
            $totalActive += (int) $row['cnt'];
        }

        return [
            'totalActiveKeywords' => $totalActive,
            'top3' => array_slice($ranked['top10'], 0, 3),
            'seoPositionComparisons' => $seoPositionComparisons,

            // Google OAuth status
            'googleOAuthConnected' => $this->googleOAuthService->isConnected(),
            'googleOAuthConfigured' => $this->googleOAuthService->isConfigured(),

            // Google Reviews
            'googlePlacesConfigured' => $this->googlePlacesService->isConfigured(),
            'reviewStats' => $this->googleReviewRepository->getStats(),
            'pendingReviews' => $this->googleReviewRepository->findPending(),
        ];
    }

    /**
     * Classe tous les mots-cles actifs par score composite et filtre le bruit.
     *
     * @return array{top10: array<SeoKeyword>, toImprove: array<SeoKeyword>}
     */
    private function rankSeoKeywords(array $comparisons): array
    {
        $keywords = $this->seoKeywordRepository->findAllWithLatestPosition();

        $totals = $this->seoDailyTotalRepository->getAggregatedTotals(
            new \DateTimeImmutable('-7 days'),
            new \DateTimeImmutable('today')
        );
        $minImpressions = max(1, $totals['impressions'] * 0.001);

        $scored = [];
        foreach ($keywords as $keyword) {
            if (!$keyword->isActive() || $keyword->getRelevanceLevel() !== SeoKeyword::RELEVANCE_HIGH) {
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
     * Categorise les mots-cles SEO en Top Performers, A ameliorer, et Opportunites CTR.
     *
     * @return array{topPerformers: array, toImprove: array, ctrOpportunities: array}
     */
    private function categorizeSeoKeywords(array $keywords): array
    {
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
                'relevanceLevel' => $keyword->getRelevanceLevel(),
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
     * Prepare les donnees pour le graphique SEO (clics/impressions sur 30 jours).
     *
     * @return array{labels: array, clicks: array, impressions: array, hasEnoughData: bool}
     */
    private function prepareSeoChartData(): array
    {
        $now = new \DateTimeImmutable();
        $chartStartDate = $now->modify('-29 days')->setTime(0, 0, 0);
        $dataStartDate = $now->modify('-35 days')->setTime(0, 0, 0);

        $dailyTotals = $this->seoDailyTotalRepository->findByDateRange($dataStartDate, $now);

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
     * Calcule les comparaisons de positions SEO entre le mois courant et le mois precedent.
     *
     * @return array<int, array{currentPosition: ?float, previousPosition: ?float, variation: ?float, status: string}>
     */
    private function calculateSeoPositionComparisons(): array
    {
        $now = new \DateTimeImmutable();

        $currentMonthStart = $now->modify('first day of this month')->setTime(0, 0, 0);
        $currentMonthEnd = $now->setTime(23, 59, 59);

        $previousMonthStart = $now->modify('first day of last month')->setTime(0, 0, 0);
        $previousMonthEnd = $now->modify('last day of last month')->setTime(23, 59, 59);

        $currentPositions = $this->seoPositionRepository->getAveragePositionsForAllKeywords(
            $currentMonthStart,
            $currentMonthEnd
        );
        $previousPositions = $this->seoPositionRepository->getAveragePositionsForAllKeywords(
            $previousMonthStart,
            $previousMonthEnd
        );

        $keywords = $this->seoKeywordRepository->findActiveKeywords();

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

    /**
     * Calcule les comparaisons de positions SEO entre les 2 derniers jours avec des données.
     *
     * @return array<int, array{latestPosition: ?float, previousPosition: ?float, variation: ?float, status: string}>
     */
    private function calculateSeoDailyComparisons(): array
    {
        $latestDates = $this->seoPositionRepository->findLatestDatesWithData(2);

        if (\count($latestDates) < 2) {
            // Pas assez de données pour comparer
            $keywords = $this->seoKeywordRepository->findActiveKeywords();
            $comparisons = [];
            foreach ($keywords as $keyword) {
                $comparisons[$keyword->getId()] = [
                    'latestPosition' => null,
                    'previousPosition' => null,
                    'variation' => null,
                    'status' => 'no_data',
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

        $latestPositions = $this->seoPositionRepository->getAveragePositionsForAllKeywords(
            $latestStart,
            $latestEnd
        );
        $previousPositions = $this->seoPositionRepository->getAveragePositionsForAllKeywords(
            $previousStart,
            $previousEnd
        );

        $keywords = $this->seoKeywordRepository->findActiveKeywords();

        $comparisons = [];
        foreach ($keywords as $keyword) {
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
            ];
        }

        return $comparisons;
    }

    /**
     * Prepare les donnees pour le graphique d'evolution des mots-cles SEO.
     */
    private function prepareSeoKeywordsChartData(): array
    {
        $days = 30;
        $firstAppearances = $this->seoKeywordRepository->getKeywordFirstAppearancesAll();
        $relevanceCounts = $this->seoKeywordRepository->getRelevanceCounts();
        $deactivations = $this->seoKeywordRepository->getKeywordDeactivations();
        $inactiveCount = $this->seoKeywordRepository->countInactive();

        $currentTotal = 0;
        $relevanceMap = ['high' => 0, 'medium' => 0, 'low' => 0];
        foreach ($relevanceCounts as $row) {
            $relevanceMap[$row['relevanceLevel']] = (int) $row['cnt'];
            $currentTotal += (int) $row['cnt'];
        }

        $now = new \DateTimeImmutable();
        $dataStartDate = $now->modify("-{$days} days")->setTime(0, 0, 0);
        $dailyTotals = $this->seoDailyTotalRepository->findByDateRange($dataStartDate, $now);

        $lastDataDate = null;
        foreach ($dailyTotals as $total) {
            $date = $total->getDate();
            if ($lastDataDate === null || $date > $lastDataDate) {
                $lastDataDate = $date;
            }
        }

        $endDate = $lastDataDate ?? $now;
        $startDate = $endDate->modify("-" . ($days - 1) . " days");
        $sinceDate = $startDate->format('Y-m-d');

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
}
