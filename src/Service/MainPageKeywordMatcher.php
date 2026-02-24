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
    ) {
    }

    /**
     * Auto-detect main pages from non-local keyword targetUrls.
     * Creates missing PageOptimization entries, deactivates orphans.
     */
    public function syncPages(): void
    {
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
     * @return array<array{page: PageOptimization, toImproveCount: int, totalCount: int, avgPosition: float, priorityScore: float}>
     */
    public function buildMainPagesSummary(array $ranked, array $activeKeywords, array $latestPositionData): array
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
            // Build full URLs that might match targetUrl
            $pagesByFullUrl[$page->getUrl()] = $page;
        }

        $result = [];
        foreach ($pages as $page) {
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

            // Count "to improve" keywords matching this page by targetUrl
            $toImproveCount = 0;
            foreach ($ranked['toImprove'] as $item) {
                $kwTargetUrl = $item['keyword']->getTargetUrl();
                if ($kwTargetUrl !== null && $this->urlMatchesPage($kwTargetUrl, $pagePath)) {
                    $toImproveCount++;
                }
            }

            if ($toImproveCount === 0) {
                continue;
            }

            // Count total active keywords + average position
            $totalCount = 0;
            $positionSum = 0;
            foreach ($activeKeywords as $keyword) {
                $kwTargetUrl = $keyword->getTargetUrl();
                if ($kwTargetUrl !== null && $this->urlMatchesPage($kwTargetUrl, $pagePath)) {
                    $totalCount++;
                    $latestData = $latestPositionData[$keyword->getId()] ?? null;
                    if ($latestData) {
                        $positionSum += $latestData['position'];
                    }
                }
            }

            $avgPosition = $totalCount > 0 ? round($positionSum / $totalCount, 1) : 0;

            // Priority score: same formula as cities/departments
            $priorityScore = 0;
            if ($avgPosition > 0 && $totalCount > 0) {
                $priorityScore = round(
                    $toImproveCount * (1 + log($totalCount, 2)) * (1 / $avgPosition),
                    2
                );
            }

            $result[] = [
                'page' => $page,
                'toImproveCount' => $toImproveCount,
                'totalCount' => $totalCount,
                'avgPosition' => $avgPosition,
                'priorityScore' => $priorityScore,
            ];
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
