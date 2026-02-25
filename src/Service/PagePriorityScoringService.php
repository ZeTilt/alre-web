<?php

namespace App\Service;

/**
 * Effort-Adjusted Expected Value scoring for SEO page prioritization.
 *
 * Computes a 0-100 priority score based on:
 * - Expected click gain (impressions × CTR gap to realistic target position)
 * - Position effort multiplier (striking distance 11-15 = max ROI)
 * - Relevance weight (5★ keywords worth more than 3★)
 * - Authority bonus (more keywords on page = optimization lifts all)
 * - Urgency bonus (declining keywords = act now)
 *
 * Two-pass algorithm: raw scores per page, then log-normalized to 0-100.
 */
class PagePriorityScoringService
{
    /**
     * CTR benchmarks par position (moyennes sectorielles B2B local).
     */
    private const CTR_BENCHMARKS = [
        1 => 28.0, 2 => 22.0, 3 => 18.0, 4 => 15.0, 5 => 12.0,
        6 => 10.0, 7 => 9.0, 8 => 8.0, 9 => 7.0, 10 => 6.0,
        15 => 3.0, 20 => 2.0, 30 => 0.8, 50 => 0.2,
    ];

    /**
     * Compute the raw (un-normalized) priority value for a single page.
     *
     * @param array<array{position: float, impressions: int, relevanceScore: int, monthlyVariation: ?float}> $keywords
     */
    public function computeRawScore(array $keywords, int $totalKeywordCount): float
    {
        $pageValue = 0.0;
        $declineCount = 0;

        foreach ($keywords as $kw) {
            $pos = $kw['position'] ?? 0;
            $impr = $kw['impressions'] ?? 0;
            $relScore = $kw['relevanceScore'] ?? 0;
            $monthlyVar = $kw['monthlyVariation'] ?? 0;

            if ($pos <= 0 || $impr <= 0) {
                continue;
            }

            $target = $this->getTargetPosition($pos);
            $currentCtr = $this->getExpectedCtr($pos);
            $targetCtr = $this->getExpectedCtr($target);
            $ctrGain = max(0, $targetCtr - $currentCtr);
            $expectedClickGain = $impr * $ctrGain / 100;

            $effort = $this->getPositionEffort($pos);
            $relevance = $this->getRelevanceWeight($relScore);

            $pageValue += $expectedClickGain * $effort * $relevance;

            if ($monthlyVar <= -5) {
                $declineCount++;
            }
        }

        // Authority bonus: pages with more keywords get a multiplier (optimization lifts all)
        $authorityBonus = 1 + 0.15 * log(max(1, $totalKeywordCount), 2);

        // Urgency bonus: declining keywords add urgency (capped at 5)
        $urgencyBonus = 1 + 0.1 * min(5, $declineCount);

        return $pageValue * $authorityBonus * $urgencyBonus;
    }

    /**
     * Normalize raw scores to 0-100 scale using log compression.
     *
     * @param array<string|int, float> $rawScores
     * @return array<string|int, float>
     */
    public function normalizeScores(array $rawScores): array
    {
        if (empty($rawScores)) {
            return [];
        }

        $globalMax = max($rawScores);
        $logMax = log1p($globalMax);

        $normalized = [];
        foreach ($rawScores as $key => $raw) {
            if ($logMax > 0) {
                $normalized[$key] = round(min(100, (log1p($raw) / $logMax) * 100), 1);
            } else {
                $normalized[$key] = 0.0;
            }
        }

        return $normalized;
    }

    /**
     * Realistic target position based on current ranking.
     * Striking distance (11-20) targets page 1. Far positions target moderate gains.
     */
    private function getTargetPosition(float $position): float
    {
        return match (true) {
            $position <= 10 => max(1, $position - 2),
            $position <= 20 => max(5, $position - 7),
            $position <= 30 => max(10, $position - 10),
            default => max(15, $position - 15),
        };
    }

    /**
     * Effort multiplier: how likely is optimization to succeed at this position?
     * Position 11-15 = sweet spot (content tweak → page 1). Position 50+ = unrealistic short-term.
     */
    private function getPositionEffort(float $position): float
    {
        return match (true) {
            $position <= 10 => 0.8,   // Already page 1, marginal gains
            $position <= 15 => 1.0,   // Prime striking distance, max ROI
            $position <= 20 => 0.7,   // Content enrichment needed
            $position <= 30 => 0.35,  // Significant work (backlinks)
            $position <= 50 => 0.1,   // Major project
            default => 0.02,          // Unrealistic short-term
        };
    }

    /**
     * Relevance weight based on keyword star rating.
     */
    private function getRelevanceWeight(int $score): float
    {
        return match (true) {
            $score >= 5 => 1.15,
            $score >= 4 => 1.0,
            $score >= 3 => 0.6,
            default => 0.3,
        };
    }

    /**
     * Interpolate expected CTR for a given position using benchmarks.
     */
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
}
