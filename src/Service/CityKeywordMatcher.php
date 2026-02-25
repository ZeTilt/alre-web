<?php

namespace App\Service;

use App\Entity\City;
use App\Entity\DepartmentPage;
use App\Entity\SeoKeyword;
use App\Repository\CityRepository;
use App\Repository\DepartmentPageRepository;

class CityKeywordMatcher
{
    private ?array $citiesCache = null;

    /** @var array<string, string> Memoized normalize() results */
    private array $normalizeCache = [];

    /** @var array<int, string[]> Normalized name-only patterns per city ID */
    private array $cityNamePatternsCache = [];

    /** @var array<int, string[]> Normalized name+region patterns per city ID */
    private array $cityAllPatternsCache = [];

    public function __construct(
        private CityRepository $cityRepository,
        private DepartmentPageRepository $departmentPageRepository,
        private PagePriorityScoringService $scoringService,
    ) {
    }

    /**
     * Normalize a string: strip accents + lowercase. Memoized.
     */
    private function normalize(string $text): string
    {
        return $this->normalizeCache[$text] ??= mb_strtolower(
            transliterator_transliterate('NFD; [:Nonspacing Mark:] Remove; NFC', $text)
        );
    }

    /**
     * Get pre-normalized name patterns for a city (no region). Memoized.
     *
     * @return string[] Normalized patterns
     */
    private function getCityNamePatterns(City $city): array
    {
        $cityId = $city->getId();
        if (!isset($this->cityNamePatternsCache[$cityId])) {
            $name = $city->getName();
            $stripped = transliterator_transliterate('NFD; [:Nonspacing Mark:] Remove; NFC', $name);

            $nameVariants = array_unique([$name, $stripped]);
            $patterns = [];
            foreach ($nameVariants as $v) {
                $patterns[] = $v;
                if (str_contains($v, '-')) {
                    $patterns[] = str_replace('-', ' ', $v);
                }
                if (str_contains($v, ' ')) {
                    $patterns[] = str_replace(' ', '-', $v);
                }
            }

            $this->cityNamePatternsCache[$cityId] = array_map(
                fn($p) => $this->normalize($p),
                array_unique($patterns)
            );
        }

        return $this->cityNamePatternsCache[$cityId];
    }

    /**
     * Get pre-normalized name+region patterns for a city. Memoized.
     *
     * @return string[] Normalized patterns
     */
    private function getCityAllPatterns(City $city): array
    {
        $cityId = $city->getId();
        if (!isset($this->cityAllPatternsCache[$cityId])) {
            $patterns = $this->getCityNamePatterns($city);

            $region = $city->getRegion();
            if ($region) {
                $patterns[] = $this->normalize($region);
            }

            $this->cityAllPatternsCache[$cityId] = array_values(array_unique($patterns));
        }

        return $this->cityAllPatternsCache[$cityId];
    }

    /**
     * Generates name variants for a city (accents, hyphens/spaces) + region.
     *
     * @return string[] Lowercased patterns to match against
     */
    public function buildCityPatterns(City $city): array
    {
        $name = $city->getName();
        $stripped = transliterator_transliterate('NFD; [:Nonspacing Mark:] Remove; NFC', $name);

        $variants = array_unique([$name, $stripped]);
        $patterns = [];
        foreach ($variants as $v) {
            $patterns[] = $v;
            if (str_contains($v, '-')) {
                $patterns[] = str_replace('-', ' ', $v);
            }
            if (str_contains($v, ' ')) {
                $patterns[] = str_replace(' ', '-', $v);
            }
        }

        // Add region as pattern (e.g. "morbihan", "bretagne")
        $region = $city->getRegion();
        if ($region) {
            $regionStripped = transliterator_transliterate('NFD; [:Nonspacing Mark:] Remove; NFC', $region);
            $patterns[] = $region;
            if ($regionStripped !== $region) {
                $patterns[] = $regionStripped;
            }
        }

        return array_values(array_unique($patterns));
    }

    /**
     * Checks if a keyword text matches a city (name or region).
     */
    public function keywordMatchesCity(string $keyword, City $city): bool
    {
        $normalizedKeyword = $this->normalize($keyword);

        foreach ($this->getCityAllPatterns($city) as $normalizedPattern) {
            if (str_contains($normalizedKeyword, $normalizedPattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Finds the most specific city matching a keyword.
     * Cities are sorted by name length descending to match "Ploemeur-Bodou" before "Ploemeur".
     */
    public function findCityForKeyword(SeoKeyword $keyword): ?City
    {
        $cities = $this->getActiveCitiesByNameLength();
        $keywordText = $keyword->getKeyword();

        foreach ($cities as $city) {
            if ($this->keywordMatchesCityName($keywordText, $city)) {
                return $city;
            }
        }

        // Fallback: match by region only
        foreach ($cities as $city) {
            if ($this->keywordMatchesCityRegion($keywordText, $city)) {
                return $city;
            }
        }

        return null;
    }

    /**
     * Counts active keywords matching a city (by name or region).
     *
     * @param SeoKeyword[] $keywords
     */
    public function countKeywordsForCity(City $city, array $keywords): int
    {
        $count = 0;
        foreach ($keywords as $keyword) {
            $text = $keyword instanceof SeoKeyword ? $keyword->getKeyword() : (string) $keyword;
            if ($this->keywordMatchesCity($text, $city)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Builds the "Pages a optimiser" summary aggregated by city.
     *
     * Excludes cities optimized in the last 30 days.
     * Requires at least 1 "to improve" keyword matching the city NAME (not just region).
     *
     * @param array{top10: array, toImprove: array} $ranked Output of rankSeoKeywords()
     * @param SeoKeyword[] $activeKeywords Active keywords
     * @param array<int, array{position: float, clicks: int, impressions: int}> $latestPositionData Latest position per keyword
     * @param array<int, array{currentPosition: ?float, previousPosition: ?float, variation: ?float, status: string}> $positionComparisons
     * @return array<array{city: City, toImproveCount: int, totalCount: int, avgPosition: float, priorityScore: float}>
     */
    public function buildCityPagesSummary(array $ranked, array $activeKeywords, array $latestPositionData = [], array $positionComparisons = []): array
    {
        $cities = $this->getActiveCitiesByNameLength();

        if (empty($cities)) {
            return [];
        }

        $recentThreshold = (new \DateTimeImmutable())->modify('-30 days');

        $cityPages = [];
        $rawScores = [];
        foreach ($cities as $city) {
            // Skip cities optimized in the last 30 days
            $lastOpt = $city->getLastOptimizedAt();
            if ($lastOpt !== null && $lastOpt > $recentThreshold) {
                continue;
            }

            // Collect "to improve" keywords matching this city (used for scoring)
            // Must have at least 1 keyword matching the city NAME (not just region)
            $toImproveCount = 0;
            $hasCityNameMatch = false;
            $scoringKeywords = [];
            foreach ($ranked['toImprove'] as $item) {
                $kw = $item['keyword'];
                $kwText = $kw->getKeyword();
                if ($this->keywordMatchesCityName($kwText, $city)) {
                    $toImproveCount++;
                    $hasCityNameMatch = true;
                } elseif ($this->keywordMatchesCityRegion($kwText, $city)) {
                    $toImproveCount++;
                } else {
                    continue;
                }
                $latestData = $latestPositionData[$kw->getId()] ?? null;
                if ($latestData) {
                    $scoringKeywords[] = [
                        'position' => $latestData['position'],
                        'impressions' => $latestData['impressions'],
                        'relevanceScore' => $kw->getRelevanceScore(),
                        'monthlyVariation' => $positionComparisons[$kw->getId()]['variation'] ?? null,
                    ];
                }
            }

            if ($toImproveCount === 0 || !$hasCityNameMatch) {
                continue;
            }

            // Count total active keywords matching this city (display + authority bonus)
            $totalCount = 0;
            $positionSum = 0;
            foreach ($activeKeywords as $keyword) {
                if ($this->keywordMatchesCity($keyword->getKeyword(), $city)) {
                    $totalCount++;
                    $latestData = $latestPositionData[$keyword->getId()] ?? null;
                    if ($latestData) {
                        $positionSum += $latestData['position'];
                    }
                }
            }

            $avgPosition = $totalCount > 0 ? round($positionSum / $totalCount, 1) : 0;

            // Score based on "to improve" keywords only, authority from total count
            $resultIdx = count($cityPages);
            $rawScores[$resultIdx] = $this->scoringService->computeRawScore($scoringKeywords, $totalCount);

            $cityPages[] = [
                'city' => $city,
                'toImproveCount' => $toImproveCount,
                'totalCount' => $totalCount,
                'avgPosition' => $avgPosition,
                'priorityScore' => 0, // placeholder, filled after normalization
            ];
        }

        // Pass 2: normalize scores to 0-100
        $normalized = $this->scoringService->normalizeScores($rawScores);
        foreach ($normalized as $idx => $score) {
            $cityPages[$idx]['priorityScore'] = $score;
        }

        // Sort by priority score descending
        usort($cityPages, fn($a, $b) => $b['priorityScore'] <=> $a['priorityScore']);

        return $cityPages;
    }

    /**
     * Generates name variants for a department.
     *
     * @return string[] Lowercased patterns to match against
     */
    public function buildDepartmentPatterns(DepartmentPage $dept): array
    {
        $name = $dept->getName();
        $stripped = transliterator_transliterate('NFD; [:Nonspacing Mark:] Remove; NFC', $name);

        $variants = array_unique([$name, $stripped]);
        $patterns = [];
        foreach ($variants as $v) {
            $patterns[] = $v;
            if (str_contains($v, '-')) {
                $patterns[] = str_replace('-', ' ', $v);
            }
            if (str_contains($v, ' ')) {
                $patterns[] = str_replace(' ', '-', $v);
            }
            if (str_contains($v, '\'')) {
                $patterns[] = str_replace('\'', '', $v);
                $patterns[] = str_replace('\'', '-', $v);
            }
        }

        return array_values(array_unique($patterns));
    }

    /**
     * Checks if a keyword text matches a department.
     */
    public function keywordMatchesDepartment(string $keyword, DepartmentPage $dept): bool
    {
        $normalizedKeyword = $this->normalize($keyword);

        foreach ($this->buildDepartmentPatterns($dept) as $pattern) {
            if (str_contains($normalizedKeyword, $this->normalize($pattern))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Builds the "Départements à optimiser" summary.
     *
     * @param array{top10: array, toImprove: array} $ranked
     * @param SeoKeyword[] $activeKeywords
     * @param array<int, array{position: float, clicks: int, impressions: int}> $latestPositionData
     * @param array<int, array{currentPosition: ?float, previousPosition: ?float, variation: ?float, status: string}> $positionComparisons
     * @return array<array{department: DepartmentPage, toImproveCount: int, totalCount: int, avgPosition: float, priorityScore: float}>
     */
    public function buildDepartmentPagesSummary(array $ranked, array $activeKeywords, array $latestPositionData = [], array $positionComparisons = []): array
    {
        $departments = $this->departmentPageRepository->findAllActive();

        if (empty($departments)) {
            return [];
        }

        $recentThreshold = (new \DateTimeImmutable())->modify('-30 days');

        $deptPages = [];
        $rawScores = [];
        foreach ($departments as $dept) {
            $lastOpt = $dept->getLastOptimizedAt();
            if ($lastOpt !== null && $lastOpt > $recentThreshold) {
                continue;
            }

            // Collect "to improve" keywords matching this department (used for scoring)
            $toImproveCount = 0;
            $scoringKeywords = [];
            foreach ($ranked['toImprove'] as $item) {
                $kw = $item['keyword'];
                if ($this->keywordMatchesDepartment($kw->getKeyword(), $dept)) {
                    $toImproveCount++;
                    $latestData = $latestPositionData[$kw->getId()] ?? null;
                    if ($latestData) {
                        $scoringKeywords[] = [
                            'position' => $latestData['position'],
                            'impressions' => $latestData['impressions'],
                            'relevanceScore' => $kw->getRelevanceScore(),
                            'monthlyVariation' => $positionComparisons[$kw->getId()]['variation'] ?? null,
                        ];
                    }
                }
            }

            if ($toImproveCount === 0) {
                continue;
            }

            // Count total active keywords matching this department (display + authority bonus)
            $totalCount = 0;
            $positionSum = 0;
            foreach ($activeKeywords as $keyword) {
                if ($this->keywordMatchesDepartment($keyword->getKeyword(), $dept)) {
                    $totalCount++;
                    $latestData = $latestPositionData[$keyword->getId()] ?? null;
                    if ($latestData) {
                        $positionSum += $latestData['position'];
                    }
                }
            }

            $avgPosition = $totalCount > 0 ? round($positionSum / $totalCount, 1) : 0;

            // Score based on "to improve" keywords only, authority from total count
            $resultIdx = count($deptPages);
            $rawScores[$resultIdx] = $this->scoringService->computeRawScore($scoringKeywords, $totalCount);

            $deptPages[] = [
                'department' => $dept,
                'toImproveCount' => $toImproveCount,
                'totalCount' => $totalCount,
                'avgPosition' => $avgPosition,
                'priorityScore' => 0, // placeholder, filled after normalization
            ];
        }

        // Pass 2: normalize scores to 0-100
        $normalized = $this->scoringService->normalizeScores($rawScores);
        foreach ($normalized as $idx => $score) {
            $deptPages[$idx]['priorityScore'] = $score;
        }

        usort($deptPages, fn($a, $b) => $b['priorityScore'] <=> $a['priorityScore']);

        return $deptPages;
    }

    /**
     * Returns active cities sorted by name length descending (most specific first).
     *
     * @return City[]
     */
    private function getActiveCitiesByNameLength(): array
    {
        if ($this->citiesCache === null) {
            $cities = $this->cityRepository->findAllActive();
            usort($cities, fn(City $a, City $b) => mb_strlen($b->getName()) <=> mb_strlen($a->getName()));
            $this->citiesCache = $cities;
        }

        return $this->citiesCache;
    }

    /**
     * Checks if a keyword matches a city by its name (not region).
     */
    private function keywordMatchesCityName(string $keyword, City $city): bool
    {
        $normalizedKeyword = $this->normalize($keyword);

        foreach ($this->getCityNamePatterns($city) as $normalizedPattern) {
            if (str_contains($normalizedKeyword, $normalizedPattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Checks if a keyword matches a city by its region only.
     */
    private function keywordMatchesCityRegion(string $keyword, City $city): bool
    {
        $region = $city->getRegion();
        if (!$region) {
            return false;
        }

        return str_contains($this->normalize($keyword), $this->normalize($region));
    }
}
