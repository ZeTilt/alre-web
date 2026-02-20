<?php

namespace App\Service;

use App\Entity\ClientSeoDailyTotal;
use App\Entity\ClientSeoImport;
use App\Entity\ClientSeoKeyword;
use App\Entity\ClientSeoPosition;
use App\Entity\ClientSite;
use App\Repository\ClientSeoDailyTotalRepository;
use App\Repository\ClientSeoKeywordRepository;
use App\Repository\ClientSeoPositionRepository;
use App\Repository\ClientSiteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class ClientBingImportService
{
    public function __construct(
        private BingWebmasterService $bingService,
        private ClientSiteRepository $clientSiteRepository,
        private ClientSeoKeywordRepository $keywordRepository,
        private ClientSeoPositionRepository $positionRepository,
        private ClientSeoDailyTotalRepository $dailyTotalRepository,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {}

    /**
     * Purge toutes les positions et totaux Bing d'un site client.
     */
    public function resetSite(ClientSite $site): int
    {
        $conn = $this->entityManager->getConnection();
        $siteId = $site->getId();

        $deleted = 0;
        $deleted += (int) $conn->executeStatement(
            'DELETE p FROM client_seo_position p INNER JOIN client_seo_keyword k ON p.client_seo_keyword_id = k.id WHERE k.client_site_id = ? AND p.source = ?',
            [$siteId, 'bing']
        );
        $deleted += (int) $conn->executeStatement(
            'DELETE FROM client_seo_daily_total WHERE client_site_id = ? AND source = ?',
            [$siteId, 'bing']
        );

        // Reset Bing tracking on keywords
        $conn->executeStatement('UPDATE client_seo_keyword SET last_seen_in_bing = NULL WHERE client_site_id = ?', [$siteId]);

        return $deleted;
    }

    /**
     * Importe les données Bing pour un site client.
     * Utilise les mêmes entités que GSC (ClientSeoKeyword/Position/DailyTotal) avec source='bing'.
     *
     * @return array{keywords: int, positions: int, dailyTotals: int, message: string}
     */
    public function importForSite(ClientSite $site): array
    {
        if (!$this->bingService->isAvailable()) {
            return ['keywords' => 0, 'positions' => 0, 'dailyTotals' => 0, 'message' => 'Bing API non configuree'];
        }

        if (!$site->isBingEnabled()) {
            return ['keywords' => 0, 'positions' => 0, 'dailyTotals' => 0, 'message' => 'Bing non active pour ce site'];
        }

        $siteUrl = $site->getUrl();
        $keywordsCreated = 0;
        $positionsCreated = 0;
        $dailyTotalsCreated = 0;

        // Import query stats (keywords + positions)
        $queryStats = $this->bingService->fetchQueryStats($siteUrl);
        foreach ($queryStats as $dateStr => $keywords) {
            $date = new \DateTimeImmutable($dateStr);

            foreach ($keywords as $query => $data) {
                // Find or create unified keyword (shared with GSC)
                $keyword = $this->keywordRepository->findByKeywordAndSite($query, $site);
                if ($keyword === null) {
                    $keyword = new ClientSeoKeyword();
                    $keyword->setClientSite($site);
                    $keyword->setKeyword($query);
                    $this->entityManager->persist($keyword);
                    $this->entityManager->flush();
                    $keywordsCreated++;
                }

                // Track last seen in Bing
                if ($keyword->getLastSeenInBing() === null || $date > $keyword->getLastSeenInBing()) {
                    $keyword->setLastSeenInBing($date);
                }

                // Upsert position with source=bing
                $existing = $this->positionRepository->findByKeywordDateAndSource($keyword, $date, ClientSeoPosition::SOURCE_BING);
                if ($existing !== null) {
                    $existing->setPosition($data['position']);
                    $existing->setClicks($data['clicks']);
                    $existing->setImpressions($data['impressions']);
                } else {
                    $position = new ClientSeoPosition();
                    $position->setClientSeoKeyword($keyword);
                    $position->setSource(ClientSeoPosition::SOURCE_BING);
                    $position->setPosition($data['position']);
                    $position->setClicks($data['clicks']);
                    $position->setImpressions($data['impressions']);
                    $position->setDate($date);
                    $this->entityManager->persist($position);
                }
                $positionsCreated++;
            }
        }

        $this->entityManager->flush();

        // Import daily traffic stats
        $trafficStats = $this->bingService->fetchDailyTrafficStats($siteUrl);
        foreach ($trafficStats as $dateStr => $data) {
            $date = new \DateTimeImmutable($dateStr);

            $existing = $this->dailyTotalRepository->findByDateSiteAndSource($site, $date, ClientSeoDailyTotal::SOURCE_BING);
            if ($existing !== null) {
                if ($existing->getClicks() !== $data['clicks'] || $existing->getImpressions() !== $data['impressions']) {
                    $existing->setClicks($data['clicks']);
                    $existing->setImpressions($data['impressions']);
                    $dailyTotalsCreated++;
                }
                continue;
            }

            $dailyTotal = new ClientSeoDailyTotal();
            $dailyTotal->setClientSite($site);
            $dailyTotal->setSource(ClientSeoDailyTotal::SOURCE_BING);
            $dailyTotal->setDate($date);
            $dailyTotal->setClicks($data['clicks']);
            $dailyTotal->setImpressions($data['impressions']);
            $this->entityManager->persist($dailyTotal);
            $dailyTotalsCreated++;
        }

        // Create import record
        $import = new ClientSeoImport();
        $import->setClientSite($site);
        $import->setType(ClientSeoImport::TYPE_BING_API);
        $import->setOriginalFilename('bing_api_auto');
        $import->setRowsImported($positionsCreated + $dailyTotalsCreated);
        $import->setRowsSkipped(0);
        $this->entityManager->persist($import);

        $this->entityManager->flush();

        $message = sprintf(
            'Bing: %d mot(s)-cle(s), %d position(s), %d total(aux) journalier(s)',
            $keywordsCreated,
            $positionsCreated,
            $dailyTotalsCreated
        );

        return [
            'keywords' => $keywordsCreated,
            'positions' => $positionsCreated,
            'dailyTotals' => $dailyTotalsCreated,
            'message' => $message,
        ];
    }

    /**
     * Importe les données Bing pour tous les sites éligibles.
     *
     * @return array<int, array{site: string, result: array}>
     */
    public function importForAllSites(): array
    {
        $sites = $this->clientSiteRepository->findBy(['isActive' => true, 'bingEnabled' => true]);
        $results = [];

        foreach ($sites as $site) {
            try {
                $result = $this->importForSite($site);
                $results[] = ['site' => $site->getName(), 'result' => $result];
            } catch (\Exception $e) {
                $this->logger->error('Error importing Bing data for client site', [
                    'site' => $site->getName(),
                    'error' => $e->getMessage(),
                ]);
                $results[] = [
                    'site' => $site->getName(),
                    'result' => ['keywords' => 0, 'positions' => 0, 'dailyTotals' => 0, 'message' => 'Erreur: ' . $e->getMessage()],
                ];
            }
        }

        return $results;
    }
}
