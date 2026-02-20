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

class ClientGscImportService
{
    public function __construct(
        private GoogleSearchConsoleService $gscService,
        private ClientSiteRepository $clientSiteRepository,
        private ClientSeoKeywordRepository $keywordRepository,
        private ClientSeoPositionRepository $positionRepository,
        private ClientSeoDailyTotalRepository $dailyTotalRepository,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {}

    /**
     * Purge toutes les positions et totaux journaliers d'un site client.
     */
    public function resetSite(ClientSite $site): int
    {
        $conn = $this->entityManager->getConnection();
        $siteId = $site->getId();

        $deleted = 0;
        $deleted += (int) $conn->executeStatement('DELETE p FROM client_seo_position p INNER JOIN client_seo_keyword k ON p.client_seo_keyword_id = k.id WHERE k.client_site_id = ?', [$siteId]);
        $deleted += (int) $conn->executeStatement('DELETE FROM client_seo_daily_total WHERE client_site_id = ?', [$siteId]);
        $deleted += (int) $conn->executeStatement('DELETE FROM client_seo_import WHERE client_site_id = ?', [$siteId]);

        return $deleted;
    }

    /**
     * Importe les données GSC pour un site client via l'API.
     *
     * @param bool $full Si true, remonte a 16 mois (limite GSC) au lieu de 7 jours
     * @return array{keywords: int, positions: int, dailyTotals: int, message: string}
     */
    public function importForSite(ClientSite $site, bool $full = false): array
    {
        if (!$this->gscService->isAvailable()) {
            return ['keywords' => 0, 'positions' => 0, 'dailyTotals' => 0, 'message' => 'GSC non connecte'];
        }

        $siteUrl = $site->getGscSiteUrl();
        $keywordsCreated = 0;
        $positionsCreated = 0;
        $dailyTotalsCreated = 0;

        $endDate = (new \DateTimeImmutable('-1 day'))->setTime(0, 0, 0);
        $startDate = $full
            ? (new \DateTimeImmutable('-16 months'))->setTime(0, 0, 0)
            : (new \DateTimeImmutable('-7 days'))->setTime(0, 0, 0);

        // Fetch keywords data in 30-day chunks using daily dimensions
        $chunkStart = clone $startDate;
        $processedPositions = []; // Track (keyword_id, date) to avoid duplicates from accent collisions

        while ($chunkStart <= $endDate) {
            $chunkEnd = min($chunkStart->modify('+29 days'), $endDate);

            $dailyData = $this->gscService->fetchDailyKeywordsData($chunkStart, $chunkEnd, $siteUrl);

            foreach ($dailyData as $dateStr => $keywords) {
                $date = new \DateTimeImmutable($dateStr);

                foreach ($keywords as $query => $data) {
                    $keyword = $this->keywordRepository->findByKeywordAndSite($query, $site);
                    if ($keyword === null) {
                        $keyword = new ClientSeoKeyword();
                        $keyword->setClientSite($site);
                        $keyword->setKeyword($query);
                        $this->entityManager->persist($keyword);
                        $this->entityManager->flush();
                        $keywordsCreated++;
                    }
                    $keyword->setLastSeenInGsc(new \DateTimeImmutable());

                    // Deduplicate (accent collisions in MySQL collation)
                    $posKey = $keyword->getId() . '-' . $dateStr;
                    if (isset($processedPositions[$posKey])) {
                        continue;
                    }
                    $processedPositions[$posKey] = true;

                    // Upsert position
                    $existing = $keyword->getId() ? $this->positionRepository->findByKeywordAndDate($keyword, $date) : null;
                    if ($existing !== null) {
                        $existing->setPosition($data['position']);
                        $existing->setClicks($data['clicks']);
                        $existing->setImpressions($data['impressions']);
                    } else {
                        $position = new ClientSeoPosition();
                        $position->setClientSeoKeyword($keyword);
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
            $processedPositions = []; // Reset per chunk to limit memory
            $chunkStart = $chunkEnd->modify('+1 day');
            usleep(100000); // 100ms rate limit between chunks
        }

        // Fetch daily totals
        $totalsEnd = (new \DateTimeImmutable('-1 day'))->setTime(0, 0, 0);
        $totalsStart = $full
            ? (new \DateTimeImmutable('-16 months'))->setTime(0, 0, 0)
            : (new \DateTimeImmutable('-30 days'))->setTime(0, 0, 0);

        // Fetch daily totals in 30-day chunks
        $chunkStart = clone $totalsStart;
        $dailyTotals = [];
        while ($chunkStart <= $totalsEnd) {
            $chunkEnd = min($chunkStart->modify('+29 days'), $totalsEnd);
            $chunk = $this->gscService->fetchDailyTotals($chunkStart, $chunkEnd, $siteUrl);
            $dailyTotals = array_merge($dailyTotals, $chunk);
            $chunkStart = $chunkEnd->modify('+1 day');
            usleep(100000);
        }

        foreach ($dailyTotals as $dateStr => $data) {
            $date = new \DateTimeImmutable($dateStr);

            $existing = $this->dailyTotalRepository->findByDateAndSite($site, $date);
            if ($existing !== null) {
                // Update if data changed
                if ($existing->getClicks() !== $data['clicks'] || $existing->getImpressions() !== $data['impressions']) {
                    $existing->setClicks($data['clicks']);
                    $existing->setImpressions($data['impressions']);
                    $existing->setPosition($data['position']);
                    $dailyTotalsCreated++;
                }
                continue;
            }

            $dailyTotal = new ClientSeoDailyTotal();
            $dailyTotal->setClientSite($site);
            $dailyTotal->setDate($date);
            $dailyTotal->setClicks($data['clicks']);
            $dailyTotal->setImpressions($data['impressions']);
            $dailyTotal->setPosition($data['position']);
            $this->entityManager->persist($dailyTotal);
            $dailyTotalsCreated++;
        }

        // Auto-deactivate keywords not seen in GSC for 30 days
        $deactivationThreshold = (new \DateTimeImmutable('-30 days'));
        $toDeactivate = $this->keywordRepository->findKeywordsToDeactivate($site, $deactivationThreshold);
        $deactivatedCount = 0;
        foreach ($toDeactivate as $kw) {
            $kw->setIsActive(false);
            $kw->setDeactivatedAt(new \DateTimeImmutable());
            $deactivatedCount++;
        }
        if ($deactivatedCount > 0) {
            $this->entityManager->flush();
            $this->logger->info(sprintf('Auto-deactivated %d keywords for %s', $deactivatedCount, $site->getName()));
        }

        // Create import record
        $import = new ClientSeoImport();
        $import->setClientSite($site);
        $import->setType(ClientSeoImport::TYPE_GSC_API);
        $import->setOriginalFilename('gsc_api_auto');
        $import->setRowsImported($positionsCreated + $dailyTotalsCreated);
        $import->setRowsSkipped(0);
        if (!empty($dailyTotals)) {
            $dates = array_keys($dailyTotals);
            sort($dates);
            $import->setPeriodStart(new \DateTimeImmutable(reset($dates)));
            $import->setPeriodEnd(new \DateTimeImmutable(end($dates)));
        }
        $this->entityManager->persist($import);

        $this->entityManager->flush();

        $message = sprintf(
            'GSC API: %d mot(s)-cle(s), %d position(s), %d total(aux) journalier(s)',
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
     * Importe les données GSC pour tous les sites actifs.
     *
     * @return array<int, array{site: string, result: array}>
     */
    public function importForAllSites(bool $full = false): array
    {
        $sites = $this->clientSiteRepository->findBy(['isActive' => true]);
        $results = [];

        foreach ($sites as $site) {
            try {
                $result = $this->importForSite($site, $full);
                $results[] = ['site' => $site->getName(), 'result' => $result];
            } catch (\Exception $e) {
                $this->logger->error('Error importing GSC data for client site', [
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
