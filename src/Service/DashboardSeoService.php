<?php

namespace App\Service;

use App\Entity\SeoKeyword;
use App\Repository\GoogleReviewRepository;
use App\Repository\SeoDailyTotalRepository;
use App\Repository\SeoKeywordRepository;
use App\Repository\SeoPositionRepository;
use App\Service\CityKeywordMatcher;
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
        private CityKeywordMatcher $cityKeywordMatcher,
    ) {
    }

    /**
     * Returns all SEO data for the full SEO dashboard.
     */
    public function getFullData(): array
    {
        // Pre-fetch shared data to avoid duplicate queries
        $activeKeywords = $this->seoKeywordRepository->findActiveKeywords();
        $latestDates7 = $this->seoPositionRepository->findLatestDatesWithData(7);
        $dailyTotals = $this->seoDailyTotalRepository->findByDateRange(
            (new \DateTimeImmutable())->modify('-35 days')->setTime(0, 0, 0),
            new \DateTimeImmutable()
        );

        // Single query for all position data (replaces 7 individual queries)
        $now = new \DateTimeImmutable();
        $rawPositions = $this->seoPositionRepository->getRawPositionsForActiveKeywords(
            $now->modify('first day of last month')->setTime(0, 0, 0),
            $now
        );

        // Extract latest position per keyword from rawPositions (eliminates findAllWithLatestPosition query)
        $latestPositionData = $this->extractLatestPositions($rawPositions);

        $seoPositionComparisons = $this->calculateSeoPositionComparisons($activeKeywords, $rawPositions);
        $seoDailyComparisons = $this->calculateSeoDailyComparisons($activeKeywords, $latestDates7, $rawPositions);
        $seoMomentum = $this->calculate7DayMomentum($latestDates7, $rawPositions);
        $seoKeywordsRanked = $this->rankSeoKeywords($seoPositionComparisons, $seoDailyComparisons, $seoMomentum, $activeKeywords, $latestPositionData, $latestDates7, $rawPositions);

        return [
            // Google OAuth
            'googleOAuthConfigured' => $this->googleOAuthService->isConfigured(),
            'googleOAuthConnected' => $this->googleOAuthService->isConnected(),
            'googleOAuthToken' => $this->googleOAuthService->getValidToken(),

            // SEO Sync
            'lastSeoSyncAt' => $this->seoDataImportService->getLastSyncDate(),

            // SEO Position comparisons (monthly + momentum)
            'seoPositionComparisons' => $seoPositionComparisons,
            'seoMomentum' => $seoMomentum,

            // SEO Keywords ranked by score
            'seoKeywordsRanked' => $seoKeywordsRanked,

            // SEO City pages summary (aggregated "to improve" by city)
            'seoCityPages' => $this->cityKeywordMatcher->buildCityPagesSummary($seoKeywordsRanked, $activeKeywords, $latestPositionData),

            // SEO Department pages summary
            'seoDepartmentPages' => $this->cityKeywordMatcher->buildDepartmentPagesSummary($seoKeywordsRanked, $activeKeywords, $latestPositionData),

            // SEO Chart data (last 30 days)
            'seoChartData' => $this->prepareSeoChartData($dailyTotals),

            // SEO Performance categories
            'seoPerformanceData' => $this->categorizeSeoKeywords($activeKeywords, $latestPositionData),

            // SEO Keywords Chart
            'seoKeywordsChartData' => $this->prepareSeoKeywordsChartData($dailyTotals),

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
        // Pre-fetch shared data
        $activeKeywords = $this->seoKeywordRepository->findActiveKeywords();
        $latestDates7 = $this->seoPositionRepository->findLatestDatesWithData(7);

        // Single query for all position data (replaces 7 individual queries)
        $now = new \DateTimeImmutable();
        $rawPositions = $this->seoPositionRepository->getRawPositionsForActiveKeywords(
            $now->modify('first day of last month')->setTime(0, 0, 0),
            $now
        );

        // Extract latest position per keyword from rawPositions (eliminates findAllWithLatestPosition query)
        $latestPositionData = $this->extractLatestPositions($rawPositions);

        $seoPositionComparisons = $this->calculateSeoPositionComparisons($activeKeywords, $rawPositions);
        $seoDailyComparisons = $this->calculateSeoDailyComparisons($activeKeywords, $latestDates7, $rawPositions);
        $seoMomentum = $this->calculate7DayMomentum($latestDates7, $rawPositions);
        $ranked = $this->rankSeoKeywords($seoPositionComparisons, $seoDailyComparisons, $seoMomentum, $activeKeywords, $latestPositionData, $latestDates7, $rawPositions);

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
     * Benchmarks CTR par position (moyennes sectorielles).
     */
    private const CTR_BENCHMARKS = [
        1 => 28.0, 2 => 22.0, 3 => 18.0, 4 => 15.0, 5 => 12.0,
        6 => 10.0, 7 => 9.0, 8 => 8.0, 9 => 7.0, 10 => 6.0,
        15 => 3.0, 20 => 2.0, 30 => 0.8, 50 => 0.2,
    ];

    private function getExpectedCtr(float $position): float
    {
        $pos = (int) floor($position);
        if ($pos <= 0) {
            return 0.1;
        }
        // Cherche la valeur exacte ou interpole entre les bornes connues
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
     * avec données vs les 3 jours précédents.
     *
     * @param \DateTimeImmutable[] $latestDates7 Pre-fetched latest 7 dates with data
     * @return array<int, array{factor: float, trend: float, status: string}> keywordId => momentum data
     */
    private function calculate7DayMomentum(array $latestDates7, array $rawPositions): array
    {
        if (\count($latestDates7) < 4) {
            return []; // Pas assez de données
        }

        // Les 3 jours les plus récents vs les jours restants (max 4)
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
            // Positif = amélioration (position descend)
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
     * Calcule l'écart-type de position sur les 7 derniers jours avec données.
     *
     * @param \DateTimeImmutable[] $latestDates7 Pre-fetched latest 7 dates with data
     * @return array<int, float> keywordId => stddev (bas = stable)
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
                $stability[$keywordId] = 99.0; // Pas assez de données = instable
                continue;
            }
            $mean = array_sum($positions) / \count($positions);
            $variance = array_sum(array_map(fn($p) => ($p - $mean) ** 2, $positions)) / \count($positions);
            $stability[$keywordId] = round(sqrt($variance), 1);
        }

        return $stability;
    }

    /**
     * Classe tous les mots-cles actifs par score composite et filtre le bruit.
     *
     * Top 10 : score = baseValue × ctrFactor × momentum × monthlyVelocity
     * A travailler : score basé sur le critère déclencheur + raison + action
     *
     * @param SeoKeyword[] $activeKeywords Pre-fetched active keywords
     * @param array<int, array{position: float, clicks: int, impressions: int}> $latestPositionData Latest position per keyword
     * @param \DateTimeImmutable[] $latestDates7 Pre-fetched latest 7 dates
     * @return array{top10: array<SeoKeyword>, toImprove: array<array{keyword: SeoKeyword, reason: string, action: string}>}
     */
    private function rankSeoKeywords(array $comparisons, array $dailyComparisons, array $momentum, array $activeKeywords, array $latestPositionData, array $latestDates7, array $rawPositions): array
    {
        $stability = $this->calculatePositionStability($latestDates7, $rawPositions);

        // Pré-filtrer et collecter les données pour calculer le seuil d'impressions relatif
        $eligible = [];
        $maxImpressions = 0;
        $cityCountCache = [];

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

        // Seuils d'impressions relatifs au max (s'adapte à la taille du site)
        // Critère 1 (page 1, CTR) : 10% du max
        // Critère 2 (porte top 10) : 15% du max
        // Critère 4 (fort volume page 2) : 30% du max
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

            // --- Facteurs communs ---
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

            // --- Score Top 10 ---
            $pageBonus = $position <= 10 ? 1.5 : ($position <= 20 ? 1.25 : 1.0);
            $baseScore = ($impressions / $position) * (1 + 2 * $clicks) * $pageBonus;
            $top10Score = $baseScore * $ctrFactor * $momentumFactor * $monthlyVelocity;
            $top10Scored[] = ['keyword' => $keyword, 'score' => $top10Score];

            // --- "A travailler" : identification du critère déclencheur ---

            // Mot-clé optimisé dans les 30 derniers jours = on attend
            $lastOptimized = $keyword->getLastOptimizedAt();
            if ($lastOptimized !== null && $lastOptimized > (new \DateTimeImmutable())->modify('-30 days')) {
                continue;
            }

            // Position instable (stddev >= 3) = mot-clé en mouvement, on attend
            $isStable = $stddev < 3;

            // Momentum en hausse = le mot-clé se débrouille
            $isRising = $momentumFactor >= 1.15;

            $reason = null;
            $action = null;
            $improveScore = 0;

            // Critère 1 : CTR faible en page 1, position stable, pas en hausse
            if ($position <= 10 && $ctrRatio < 0.5 && $impressions >= $minImprC1 && $isStable && !$isRising) {
                $expectedClicks = $impressions * ($expectedCtr / 100);
                $improveScore = max(0, $expectedClicks - $clicks) * 3.0;
                $reason = 'CTR faible en page 1';
                $action = 'Optimiser title et meta description';
            }
            // Critère 2 : Porte du top 10 (position 11-15), stable, pas en hausse
            elseif ($position > 10 && $position <= 15 && $impressions >= $minImprC2 && $isStable && !$isRising) {
                $expectedClicks = $impressions * ($expectedCtr / 100);
                $improveScore = max(0, $expectedClicks - $clicks) * 1.5;
                $reason = 'Proche du top 10';
                $action = 'Enrichir le contenu pour passer en page 1';
            }
            // Critère 3 : En déclin significatif (M-1 <= -5) ET pas en train de remonter
            elseif ($position <= 20 && $monthlyVar <= -5 && !$isRising) {
                $improveScore = abs($monthlyVar) * $impressions * 0.1;
                $reason = 'En déclin (M-1 : ' . round($monthlyVar, 1) . ')';
                $action = 'Analyser la concurrence et rafraichir le contenu';
            }
            // Critère 4 : Page 2 (16-20) fort volume relatif, stable, pas en hausse
            elseif ($position > 15 && $position <= 20 && $impressions >= $minImprC4 && $isStable && !$isRising) {
                $expectedClicks = $impressions * ($expectedCtr / 100);
                $improveScore = max(0, $expectedClicks - $clicks) * 0.8;
                $reason = 'Fort volume en page 2';
                $action = 'Backlinks et contenu approfondi';
            }

            if ($reason !== null && $improveScore > 0) {
                // Pondération volume (ratio par rapport au max)
                $volumeRatio = $maxImpressions > 0 ? $impressions / $maxImpressions : 0;
                $volumeMultiplier = match (true) {
                    $volumeRatio >= 0.75 => 1.5,
                    $volumeRatio >= 0.40 => 1.2,
                    $volumeRatio >= 0.15 => 1.0,
                    default => 0.8,
                };
                $improveScore *= $volumeMultiplier;

                // City leverage: keywords on cities with more tracked keywords get a slight boost
                $city = $this->cityKeywordMatcher->findCityForKeyword($keyword);
                if ($city !== null) {
                    $cityId = $city->getId();
                    if (!isset($cityCountCache[$cityId])) {
                        $cityCountCache[$cityId] = $this->cityKeywordMatcher->countKeywordsForCity($city, $eligible);
                    }
                    $cityKeywordCount = $cityCountCache[$cityId];
                    $cityLeverageMultiplier = min(1.3, 1.0 + 0.1 * log(max(1, $cityKeywordCount), 2));
                    $improveScore *= $cityLeverageMultiplier;
                }

                // Relevance boost: 5★ gets ×1.15 vs 4★ baseline
                $score = $keyword->getRelevanceScore();
                if ($score >= 5) {
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

        // Top 10 : meilleurs scores de visibilité
        usort($top10Scored, fn($a, $b) => $b['score'] <=> $a['score']);
        $top10 = array_map(fn($item) => $item['keyword'], array_slice($top10Scored, 0, 10));
        $top10Ids = array_map(fn($kw) => $kw->getId(), $top10);

        // A travailler : exclure le top 10, trier par score, garder raison + action
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
     * Categorise les mots-cles SEO en Top Performers, A ameliorer, et Opportunites CTR.
     *
     * @param SeoKeyword[] $activeKeywords Pre-fetched active keywords
     * @param array<int, array{position: float, clicks: int, impressions: int}> $latestPositionData Latest position per keyword
     * @return array{topPerformers: array, toImprove: array, ctrOpportunities: array}
     */
    private function categorizeSeoKeywords(array $activeKeywords, array $latestPositionData): array
    {
        $allScored = [];
        $ctrOpportunities = [];

        foreach ($activeKeywords as $keyword) {
            $latestData = $latestPositionData[$keyword->getId()] ?? null;
            if (!$latestData) {
                continue;
            }

            $position = $latestData['position'];
            $clicks = $latestData['clicks'];
            $impressions = $latestData['impressions'];
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
     * @param \App\Entity\SeoDailyTotal[] $dailyTotals Pre-fetched daily totals
     * @return array{labels: array, clicks: array, impressions: array, hasEnoughData: bool}
     */
    private function prepareSeoChartData(array $dailyTotals): array
    {
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
     * @param SeoKeyword[] $activeKeywords Pre-fetched active keywords
     * @return array<int, array{currentPosition: ?float, previousPosition: ?float, variation: ?float, status: string}>
     */
    private function calculateSeoPositionComparisons(array $activeKeywords, array $rawPositions): array
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
     * Calcule les comparaisons de positions SEO entre les 2 derniers jours avec des données.
     *
     * @param SeoKeyword[] $activeKeywords Pre-fetched active keywords
     * @param \DateTimeImmutable[] $latestDates7 Pre-fetched latest 7 dates (first 2 used)
     * @return array<int, array{latestPosition: ?float, previousPosition: ?float, variation: ?float, status: string}>
     */
    private function calculateSeoDailyComparisons(array $activeKeywords, array $latestDates7, array $rawPositions): array
    {
        // Derive the 2 most recent dates from the 7-dates array
        $latestDates = \array_slice($latestDates7, 0, 2);

        if (\count($latestDates) < 2) {
            // Pas assez de données pour comparer
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
     * Prepare les donnees pour le graphique d'evolution des mots-cles SEO.
     * Catégories par score de pertinence (0-5 étoiles).
     *
     * @param \App\Entity\SeoDailyTotal[] $dailyTotals Pre-fetched daily totals
     */
    /**
     * Agrège les positions brutes pour une sous-période (équivalent PHP de getAveragePositionsForAllKeywords).
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
     * Extrait l'historique de positions pour une sous-période (équivalent PHP de getPositionHistoryForRange).
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
     * Extract the latest position data per keyword from raw positions.
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

    private function prepareSeoKeywordsChartData(array $dailyTotals): array
    {
        $days = 30;
        $firstAppearances = $this->seoKeywordRepository->getKeywordFirstAppearancesAll();
        $relevanceCounts = $this->seoKeywordRepository->getRelevanceCounts();
        $deactivations = $this->seoKeywordRepository->getKeywordDeactivations();
        $inactiveCount = $this->seoKeywordRepository->countInactive();

        $currentTotal = 0;
        $scoreMap = [0 => 0, 1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
        foreach ($relevanceCounts as $row) {
            $score = (int) $row['relevanceScore'];
            $scoreMap[$score] = (int) $row['cnt'];
            $currentTotal += (int) $row['cnt'];
        }

        $lastDataDate = null;
        foreach ($dailyTotals as $total) {
            $date = $total->getDate();
            if ($lastDataDate === null || $date > $lastDataDate) {
                $lastDataDate = $date;
            }
        }

        $now = new \DateTimeImmutable();
        $endDate = $lastDataDate ?? $now;
        $startDate = $endDate->modify("-" . ($days - 1) . " days");
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
}
