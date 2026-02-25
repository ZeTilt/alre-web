<?php

namespace App\Service;

use App\Entity\PageOptimization;
use App\Entity\SeoKeyword;
use App\Repository\PageOptimizationRepository;
use App\Repository\SeoKeywordRepository;
use Doctrine\ORM\EntityManagerInterface;

class MainPageKeywordMatcher
{
    public function __construct(
        private SeoKeywordRepository $seoKeywordRepository,
        private PageOptimizationRepository $pageOptimizationRepository,
        private EntityManagerInterface $entityManager,
        private SeoDataImportService $seoDataImportService,
        private PagePriorityScoringService $scoringService,
    ) {
    }

    /**
     * Auto-detect main pages from non-local keyword targetUrls.
     * Creates missing PageOptimization entries, deactivates orphans.
     * On first run (no targetUrls in DB), bootstraps from GSC.
     */
    public function syncPages(): void
    {
        // Sync targetUrls from GSC if any active keyword is missing one
        if ($this->seoKeywordRepository->countActiveWithoutTargetUrl() > 0) {
            $this->seoDataImportService->syncTargetUrls();
        }

        $targetUrls = $this->seoKeywordRepository->getDistinctNonLocalTargetUrls();

        // Convert full URLs to relative paths
        $activePaths = [];
        foreach ($targetUrls as $fullUrl) {
            $path = $this->urlToPath($fullUrl);
            if ($path !== null) {
                $activePaths[$path] = $fullUrl;
            }
        }

        // First sync: if table is empty, backdate createdAt so pages appear immediately
        $isFirstSync = empty($this->pageOptimizationRepository->findAllActive());
        $backdatedCreatedAt = $isFirstSync ? new \DateTimeImmutable('-60 days') : null;

        // Create missing PageOptimization entries
        foreach ($activePaths as $path => $fullUrl) {
            $existing = $this->pageOptimizationRepository->findByUrl($path);
            if ($existing === null) {
                $page = new PageOptimization();
                $page->setUrl($path);
                $page->setLabel($this->guessLabel($path));
                if ($backdatedCreatedAt !== null) {
                    $page->setCreatedAt($backdatedCreatedAt);
                }
                $this->entityManager->persist($page);
            } elseif (!$existing->isActive()) {
                // Re-activate if it was deactivated but keywords are back
                $existing->setIsActive(true);
            }
        }

        // Deactivate orphans (active pages with no matching keywords)
        $allActive = $this->pageOptimizationRepository->findAllActive();
        foreach ($allActive as $page) {
            if (!isset($activePaths[$page->getUrl()])) {
                $page->setIsActive(false);
            }
        }

        $this->entityManager->flush();
    }

    /**
     * Build the main pages summary for the dashboard.
     *
     * @param array{top10: array, toImprove: array} $ranked
     * @param SeoKeyword[] $activeKeywords
     * @param array<int, array{position: float, clicks: int, impressions: int}> $latestPositionData
     * @param array<int, array{currentPosition: ?float, previousPosition: ?float, variation: ?float, status: string}> $positionComparisons
     * @return array<array{page: PageOptimization, toImproveCount: int, totalCount: int, avgPosition: float, priorityScore: float}>
     */
    public function buildMainPagesSummary(array $ranked, array $activeKeywords, array $latestPositionData, array $positionComparisons = []): array
    {
        $this->syncPages();

        $pages = $this->pageOptimizationRepository->findAllActive();
        if (empty($pages)) {
            return [];
        }

        $recentThreshold = (new \DateTimeImmutable())->modify('-30 days');

        // Build a map of full URL -> path for matching
        $pagesByFullUrl = [];
        foreach ($pages as $page) {
            $pagesByFullUrl[$page->getUrl()] = $page;
        }

        $result = [];
        $rawScores = [];
        foreach ($pages as $idx => $page) {
            // Skip pages optimized in the last 30 days
            $lastOpt = $page->getLastOptimizedAt();
            if ($lastOpt !== null && $lastOpt > $recentThreshold) {
                continue;
            }

            // Skip recently created pages (less than 30 days)
            if ($page->getCreatedAt() > $recentThreshold) {
                continue;
            }

            $pagePath = $page->getUrl();

            // Collect "to improve" keywords matching this page
            $toImproveKeywords = [];
            foreach ($ranked['toImprove'] as $item) {
                $kwTargetUrl = $item['keyword']->getTargetUrl();
                if ($kwTargetUrl !== null && $this->urlMatchesPage($kwTargetUrl, $pagePath)) {
                    $kw = $item['keyword'];
                    $ld = $latestPositionData[$kw->getId()] ?? null;
                    $toImproveKeywords[] = [
                        'keyword' => $kw->getKeyword(),
                        'position' => $ld ? $ld['position'] : null,
                        'clicks' => $ld ? $ld['clicks'] : 0,
                        'impressions' => $ld ? $ld['impressions'] : 0,
                        'reason' => $item['reason'],
                    ];
                }
            }

            // Collect all active keywords for this page + scoring data
            $allKeywords = [];
            $scoringKeywords = [];
            $positionSum = 0;
            foreach ($activeKeywords as $keyword) {
                $kwTargetUrl = $keyword->getTargetUrl();
                if ($kwTargetUrl !== null && $this->urlMatchesPage($kwTargetUrl, $pagePath)) {
                    $ld = $latestPositionData[$keyword->getId()] ?? null;
                    $allKeywords[] = [
                        'keyword' => $keyword->getKeyword(),
                        'position' => $ld ? $ld['position'] : null,
                        'clicks' => $ld ? $ld['clicks'] : 0,
                        'impressions' => $ld ? $ld['impressions'] : 0,
                    ];
                    if ($ld) {
                        $positionSum += $ld['position'];
                        $scoringKeywords[] = [
                            'position' => $ld['position'],
                            'impressions' => $ld['impressions'],
                            'relevanceScore' => $keyword->getRelevanceScore(),
                            'monthlyVariation' => $positionComparisons[$keyword->getId()]['variation'] ?? null,
                        ];
                    }
                }
            }

            $totalCount = count($allKeywords);
            $avgPosition = $totalCount > 0 ? round($positionSum / $totalCount, 1) : 0;

            $resultIdx = count($result);
            $rawScores[$resultIdx] = $this->scoringService->computeRawScore($scoringKeywords, $totalCount);

            $result[] = [
                'page' => $page,
                'toImproveCount' => count($toImproveKeywords),
                'toImproveKeywords' => $toImproveKeywords,
                'totalCount' => $totalCount,
                'allKeywords' => $allKeywords,
                'avgPosition' => $avgPosition,
                'priorityScore' => 0, // placeholder, filled after normalization
            ];
        }

        // Pass 2: normalize scores to 0-100
        $normalized = $this->scoringService->normalizeScores($rawScores);
        foreach ($normalized as $idx => $score) {
            $result[$idx]['priorityScore'] = $score;
        }

        usort($result, fn($a, $b) => $b['priorityScore'] <=> $a['priorityScore']);

        return $result;
    }

    /**
     * Convert a full URL to a relative path.
     */
    private function urlToPath(string $fullUrl): ?string
    {
        $parsed = parse_url($fullUrl);
        $path = $parsed['path'] ?? '/';

        // Normalize: remove trailing slash except for root
        if ($path !== '/' && str_ends_with($path, '/')) {
            $path = rtrim($path, '/');
        }

        return $path ?: '/';
    }

    /**
     * Check if a full targetUrl matches a page path.
     */
    private function urlMatchesPage(string $targetUrl, string $pagePath): bool
    {
        $urlPath = $this->urlToPath($targetUrl);
        return $urlPath === $pagePath;
    }

    /**
     * Guess a human-readable label from a URL path.
     */
    public function guessLabel(string $path): string
    {
        if ($path === '/') {
            return 'Accueil';
        }

        // Take the last segment
        $segments = explode('/', trim($path, '/'));
        $lastSegment = end($segments);

        // Replace hyphens with spaces and capitalize
        return ucfirst(str_replace('-', ' ', $lastSegment));
    }
}
