<?php

namespace App\Service;

use App\Entity\SeoDailyTotal;
use App\Entity\SeoKeyword;
use App\Entity\SeoPosition;
use App\Repository\BingConfigRepository;
use App\Repository\SeoDailyTotalRepository;
use App\Repository\SeoKeywordRepository;
use App\Repository\SeoPositionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class BingDataImportService
{
    public function __construct(
        private BingWebmasterService $bingService,
        private SeoKeywordRepository $keywordRepository,
        private SeoPositionRepository $positionRepository,
        private SeoDailyTotalRepository $dailyTotalRepository,
        private BingConfigRepository $bingConfigRepository,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
        private string $googleSiteUrl,
    ) {}

    private function getMainSiteUrl(): string
    {
        $config = $this->bingConfigRepository->getOrCreate();
        return $config->getSiteUrl() ?: $this->googleSiteUrl;
    }

    /**
     * Synchronise les mots-clés Bing pour le site propre.
     * Fetch GetQueryStats, match aux keywords existants, crée SeoPosition.
     * Si keyword inconnu -> crée SeoKeyword avec source=auto_bing.
     *
     * @return array{synced: int, created: int, skipped: int, errors: int, message: string}
     */
    public function syncBingKeywords(): array
    {
        if (!$this->bingService->isAvailable()) {
            return [
                'synced' => 0,
                'created' => 0,
                'skipped' => 0,
                'errors' => 0,
                'message' => 'Bing Webmaster API non configuree',
            ];
        }

        $queryStats = $this->bingService->fetchQueryStats($this->getMainSiteUrl());

        if (empty($queryStats)) {
            return [
                'synced' => 0,
                'created' => 0,
                'skipped' => 0,
                'errors' => 0,
                'message' => 'Aucune donnee retournee par Bing',
            ];
        }

        // Load existing keywords for matching
        $existingKeywords = $this->keywordRepository->findActiveKeywords();
        $keywordMap = [];
        foreach ($existingKeywords as $kw) {
            $keywordMap[$this->normalizeString($kw->getKeyword())] = $kw;
        }

        $synced = 0;
        $created = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($queryStats as $dateStr => $keywords) {
            $date = new \DateTimeImmutable($dateStr);

            foreach ($keywords as $query => $data) {
                try {
                    $normalizedQuery = $this->normalizeString($query);

                    // Find matching keyword
                    $keyword = $keywordMap[$normalizedQuery] ?? null;

                    if ($keyword === null) {
                        // Create new keyword from Bing
                        $keyword = new SeoKeyword();
                        $keyword->setKeyword($query);
                        $keyword->setSource(SeoKeyword::SOURCE_AUTO_BING);
                        $keyword->setRelevanceLevel(SeoKeyword::RELEVANCE_LOW);
                        $this->entityManager->persist($keyword);
                        $keywordMap[$normalizedQuery] = $keyword;
                        $created++;
                    }

                    // Check for existing position
                    $existingPosition = $this->positionRepository->findByKeywordAndDate($keyword, $date);
                    if ($existingPosition) {
                        $skipped++;
                        continue;
                    }

                    // Create SeoPosition
                    $position = new SeoPosition();
                    $position->setKeyword($keyword);
                    $position->setPosition($data['position']);
                    $position->setClicks($data['clicks']);
                    $position->setImpressions($data['impressions']);
                    $position->setDate($date);
                    $this->entityManager->persist($position);
                    $synced++;

                } catch (\Exception $e) {
                    $this->logger->error('Error syncing Bing keyword', [
                        'query' => $query,
                        'error' => $e->getMessage(),
                    ]);
                    $errors++;
                }
            }
        }

        $this->entityManager->flush();

        $message = sprintf('%d position(s) Bing importee(s)', $synced);
        if ($created > 0) {
            $message .= sprintf(', %d nouveau(x) mot(s)-cle(s)', $created);
        }
        if ($skipped > 0) {
            $message .= sprintf(', %d existant(s)', $skipped);
        }
        if ($errors > 0) {
            $message .= sprintf(', %d erreur(s)', $errors);
        }

        return [
            'synced' => $synced,
            'created' => $created,
            'skipped' => $skipped,
            'errors' => $errors,
            'message' => $message,
        ];
    }

    /**
     * Synchronise les totaux journaliers Bing pour le site propre.
     *
     * @return array{synced: int, skipped: int, message: string}
     */
    public function syncBingDailyTotals(): array
    {
        if (!$this->bingService->isAvailable()) {
            return [
                'synced' => 0,
                'skipped' => 0,
                'message' => 'Bing Webmaster API non configuree',
            ];
        }

        $trafficStats = $this->bingService->fetchDailyTrafficStats($this->getMainSiteUrl());

        if (empty($trafficStats)) {
            return [
                'synced' => 0,
                'skipped' => 0,
                'message' => 'Aucune donnee retournee par Bing',
            ];
        }

        $synced = 0;
        $skipped = 0;

        foreach ($trafficStats as $dateStr => $data) {
            $date = new \DateTimeImmutable($dateStr);

            $existing = $this->dailyTotalRepository->findByDate($date, SeoDailyTotal::SOURCE_BING);

            if ($existing) {
                if ($existing->getClicks() === $data['clicks'] &&
                    $existing->getImpressions() === $data['impressions']) {
                    $skipped++;
                    continue;
                }
                $existing->setClicks($data['clicks']);
                $existing->setImpressions($data['impressions']);
            } else {
                $dailyTotal = new SeoDailyTotal();
                $dailyTotal->setDate($date);
                $dailyTotal->setClicks($data['clicks']);
                $dailyTotal->setImpressions($data['impressions']);
                $dailyTotal->setSource(SeoDailyTotal::SOURCE_BING);
                $this->entityManager->persist($dailyTotal);
            }

            $synced++;

            $this->logger->info('Synced Bing daily total', [
                'date' => $dateStr,
                'clicks' => $data['clicks'],
                'impressions' => $data['impressions'],
            ]);
        }

        $this->entityManager->flush();

        return [
            'synced' => $synced,
            'skipped' => $skipped,
            'message' => sprintf('%d jour(s) Bing synchronise(s), %d inchange(s)', $synced, $skipped),
        ];
    }

    private function normalizeString(string $str): string
    {
        $str = strtolower($str);
        $accents = ['é', 'è', 'ê', 'ë', 'à', 'â', 'ä', 'ù', 'û', 'ü', 'ô', 'ö', 'î', 'ï', 'ç'];
        $noAccents = ['e', 'e', 'e', 'e', 'a', 'a', 'a', 'u', 'u', 'u', 'o', 'o', 'i', 'i', 'c'];
        return str_replace($accents, $noAccents, $str);
    }
}
