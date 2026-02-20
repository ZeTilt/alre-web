<?php

namespace App\Service;

use App\Entity\ClientBingDailyTotal;
use App\Entity\ClientBingKeyword;
use App\Entity\ClientBingPosition;
use App\Entity\ClientSeoImport;
use App\Entity\ClientSite;
use App\Repository\ClientBingDailyTotalRepository;
use App\Repository\ClientBingKeywordRepository;
use App\Repository\ClientBingPositionRepository;
use App\Repository\ClientSiteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class ClientBingImportService
{
    public function __construct(
        private BingWebmasterService $bingService,
        private ClientSiteRepository $clientSiteRepository,
        private ClientBingKeywordRepository $keywordRepository,
        private ClientBingPositionRepository $positionRepository,
        private ClientBingDailyTotalRepository $dailyTotalRepository,
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
        $deleted += (int) $conn->executeStatement('DELETE p FROM client_bing_position p INNER JOIN client_bing_keyword k ON p.client_bing_keyword_id = k.id WHERE k.client_site_id = ?', [$siteId]);
        $deleted += (int) $conn->executeStatement('DELETE FROM client_bing_daily_total WHERE client_site_id = ?', [$siteId]);

        return $deleted;
    }

    /**
     * Importe les données Bing pour un site client.
     * Note: l'API Bing renvoie tout l'historique disponible (~6 mois), pas de filtre par date.
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
                // Find or create keyword
                $keyword = $this->keywordRepository->findByClientSiteAndKeyword($site, $query);
                if ($keyword === null) {
                    $keyword = new ClientBingKeyword();
                    $keyword->setClientSite($site);
                    $keyword->setKeyword($query);
                    $this->entityManager->persist($keyword);
                    $this->entityManager->flush();
                    $keywordsCreated++;
                }

                // Upsert position
                $existing = $keyword->getId() ? $this->positionRepository->findByKeywordAndDate($keyword, $date) : null;
                if ($existing !== null) {
                    $existing->setPosition($data['position']);
                    $existing->setClicks($data['clicks']);
                    $existing->setImpressions($data['impressions']);
                } else {
                    $position = new ClientBingPosition();
                    $position->setClientBingKeyword($keyword);
                    $position->setPosition($data['position']);
                    $position->setClicks($data['clicks']);
                    $position->setImpressions($data['impressions']);
                    $position->setDate($date);
                    $this->entityManager->persist($position);
                }
                $positionsCreated++;
            }
        }

        // Import daily traffic stats
        $trafficStats = $this->bingService->fetchDailyTrafficStats($siteUrl);
        foreach ($trafficStats as $dateStr => $data) {
            $date = new \DateTimeImmutable($dateStr);

            $existing = $this->dailyTotalRepository->findByDateAndSite($site, $date);
            if ($existing !== null) {
                continue;
            }

            $dailyTotal = new ClientBingDailyTotal();
            $dailyTotal->setClientSite($site);
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
