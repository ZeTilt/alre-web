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
        $seoMomentum = $this->calculate7DayMomentum();

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
            'seoKeywordsRanked' => $this->rankSeoKeywords($seoPositionComparisons, $seoDailyComparisons, $seoMomentum),

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
        $seoDailyComparisons = $this->calculateSeoDailyComparisons();
        $seoMomentum = $this->calculate7DayMomentum();
        $ranked = $this->rankSeoKeywords($seoPositionComparisons, $seoDailyComparisons, $seoMomentum);

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
     * @return array<int, array{factor: float, trend: float, status: string}> keywordId => momentum data
     */
    private function calculate7DayMomentum(): array
    {
        $latestDates = $this->seoPositionRepository->findLatestDatesWithData(7);

        if (\count($latestDates) < 4) {
            return []; // Pas assez de données
        }

        // Les 3 jours les plus récents vs les jours restants (max 4)
        $recentDates = \array_slice($latestDates, 0, 3);
        $olderDates = \array_slice($latestDates, 3);

        $recentStart = end($recentDates)->setTime(0, 0, 0);
        $recentEnd = $recentDates[0]->setTime(23, 59, 59);
        $olderStart = end($olderDates)->setTime(0, 0, 0);
        $olderEnd = $olderDates[0]->setTime(23, 59, 59);

        $recentPositions = $this->seoPositionRepository->getAveragePositionsForAllKeywords($recentStart, $recentEnd);
        $olderPositions = $this->seoPositionRepository->getAveragePositionsForAllKeywords($olderStart, $olderEnd);

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
     * Classe tous les mots-cles actifs par score composite et filtre le bruit.
     *
     * Top 10 : score = baseValue × ctrFactor × momentum × monthlyVelocity
     * A travailler : score = potentielPerdu × momentum × urgency
     *
     * @return array{top10: array<SeoKeyword>, toImprove: array<SeoKeyword>}
     */
    private function rankSeoKeywords(array $comparisons, array $dailyComparisons, array $momentum): array
    {
        $keywords = $this->seoKeywordRepository->findAllWithLatestPosition();

        $top10Scored = [];
        $improveCandidates = [];

        foreach ($keywords as $keyword) {
            if (!$keyword->isActive() || $keyword->getRelevanceLevel() !== SeoKeyword::RELEVANCE_HIGH) {
                continue;
            }
            $latest = $keyword->getLatestPosition();
            if (!$latest) {
                continue;
            }

            // Exclure les mots-clés avec 0 impressions sur l'un des 2 derniers jours
            $keywordId = $keyword->getId();
            $daily = $dailyComparisons[$keywordId] ?? null;
            if ($daily === null || $daily['latestImpressions'] === 0 || $daily['previousImpressions'] === 0) {
                continue;
            }

            $position = $latest->getPosition();
            $clicks = $latest->getClicks();
            $impressions = $latest->getImpressions();
            if ($position <= 0 || $impressions <= 0) {
                continue;
            }

            $ctr = ($clicks / $impressions) * 100;
            $expectedCtr = $this->getExpectedCtr($position);

            // --- Facteurs communs ---

            // CTR factor : actual vs benchmark, borné entre 0.75 et 1.25
            $ctrRatio = $expectedCtr > 0 ? min(2.0, max(0.5, $ctr / $expectedCtr)) : 1.0;
            $ctrFactor = 0.75 + ($ctrRatio - 0.5) * (0.5 / 1.5); // 0.75 à 1.08

            // Momentum 7 jours
            $momentumFactor = ($momentum[$keywordId] ?? ['factor' => 1.0])['factor'];

            // Vélocité mensuelle (légère pour top10)
            $monthlyVar = $comparisons[$keywordId]['variation'] ?? 0;
            $monthlyVelocity = match (true) {
                $monthlyVar >= 10 => 1.15,
                $monthlyVar >= 5 => 1.08,
                $monthlyVar <= -5 => 0.92,
                default => 1.0,
            };

            // --- Score Top 10 ---
            $pageBonus = $position <= 10 ? 1.5 : ($position <= 20 ? 1.25 : 1.0);
            $baseScore = ($impressions / $position) * (1 + 2 * $clicks) * $pageBonus;
            $top10Score = $baseScore * $ctrFactor * $momentumFactor * $monthlyVelocity;

            $top10Scored[] = ['keyword' => $keyword, 'score' => $top10Score];

            // --- Score "A travailler" ---
            // Exclure les positions > 20 sauf si en déclin significatif
            if ($position > 20 && $monthlyVar > -5) {
                continue;
            }

            // CTR gap : pertinent seulement en page 1-2
            $ctrGapScore = 0;
            if ($impressions >= 30) {
                $expectedClicks = $impressions * ($expectedCtr / 100);
                $ctrGapScore = max(0, $expectedClicks - $clicks);
                // Pondérer par proximité au top 10
                $ctrGapScore *= match (true) {
                    $position <= 10 => 3.0,   // Page 1 : snippet à optimiser, impact immédiat
                    $position <= 15 => 1.5,   // Porte du top 10
                    $position <= 20 => 0.8,   // Page 2
                    default => 0.3,           // Pages lointaines (déclin uniquement)
                };
            }

            // Urgence déclin : mot-clé qui perd des positions = prioritaire
            $declineUrgency = match (true) {
                $monthlyVar <= -10 => 2.5,
                $monthlyVar <= -5 => 1.8,
                $monthlyVar <= -2 => 1.3,
                default => 1.0,
            };

            // Momentum inversé : un mot-clé qui monte = MOINS prioritaire
            $momentumAdjust = match (true) {
                $momentumFactor >= 1.3 => 0.5,   // Monte fort → pas besoin d'agir
                $momentumFactor >= 1.15 => 0.7,  // Monte → moins urgent
                $momentumFactor <= 0.7 => 1.4,   // Chute → urgent
                $momentumFactor <= 0.9 => 1.2,   // Décline → à surveiller
                default => 1.0,
            };

            // Volume : les mots-clés à fort volume méritent plus d'attention
            $volumeMultiplier = match (true) {
                $impressions >= 500 => 1.5,
                $impressions >= 200 => 1.2,
                $impressions >= 100 => 1.0,
                default => 0.8,
            };

            $improveScore = $ctrGapScore * $declineUrgency * $momentumAdjust * $volumeMultiplier;
            $improveCandidates[] = ['keyword' => $keyword, 'score' => $improveScore];
        }

        // Top 10 : meilleurs scores de visibilité
        usort($top10Scored, fn($a, $b) => $b['score'] <=> $a['score']);
        $top10 = array_map(fn($item) => $item['keyword'], array_slice($top10Scored, 0, 10));
        $top10Ids = array_map(fn($kw) => $kw->getId(), $top10);

        // A travailler : exclure le top 10, trier par potentiel perdu
        $toImproveFiltered = array_filter(
            $improveCandidates,
            fn($item) => !\in_array($item['keyword']->getId(), $top10Ids)
        );
        usort($toImproveFiltered, fn($a, $b) => $b['score'] <=> $a['score']);
        $toImprove = array_map(fn($item) => $item['keyword'], array_slice(array_values($toImproveFiltered), 0, 10));

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
                'latestImpressions' => (int) ($latestData['totalImpressions'] ?? 0),
                'previousImpressions' => (int) ($previousData['totalImpressions'] ?? 0),
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
