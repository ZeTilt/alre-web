<?php

namespace App\Service;

use App\Entity\SeoKeyword;
use App\Entity\SeoPosition;
use App\Repository\SeoKeywordRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class SeoDataImportService
{
    private const CACHE_TTL_HOURS = 12;

    public function __construct(
        private GoogleSearchConsoleService $gscService,
        private SeoKeywordRepository $keywordRepository,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {}

    /**
     * Synchronise les données GSC pour tous les mots-clés actifs.
     *
     * @return array{synced: int, skipped: int, errors: int, message: string}
     */
    public function syncAllKeywords(bool $force = false): array
    {
        if (!$this->gscService->isAvailable()) {
            return [
                'synced' => 0,
                'skipped' => 0,
                'errors' => 0,
                'message' => 'Google Search Console non connecté',
            ];
        }

        $keywords = $force
            ? $this->keywordRepository->findActiveKeywords()
            : $this->keywordRepository->findKeywordsNeedingSync(self::CACHE_TTL_HOURS);

        if (empty($keywords)) {
            return [
                'synced' => 0,
                'skipped' => 0,
                'errors' => 0,
                'message' => 'Toutes les données sont à jour (moins de 12h)',
            ];
        }

        // Récupérer toutes les données GSC en une seule requête
        $gscData = $this->gscService->fetchAllKeywordsData();

        if (empty($gscData)) {
            $this->logger->warning('No data returned from GSC API');
        }

        $synced = 0;
        $skipped = 0;
        $errors = 0;
        $now = new \DateTimeImmutable();
        $today = new \DateTimeImmutable('today');

        foreach ($keywords as $keyword) {
            try {
                $keywordLower = strtolower($keyword->getKeyword());
                $data = $gscData[$keywordLower] ?? null;

                if ($data === null) {
                    // Mot-clé non trouvé dans GSC (peut-être pas encore indexé)
                    $this->logger->info('Keyword not found in GSC data', [
                        'keyword' => $keyword->getKeyword(),
                    ]);
                    $skipped++;
                    // On met quand même à jour lastSyncAt pour éviter de réessayer trop vite
                    $keyword->setLastSyncAt($now);
                    continue;
                }

                // Créer une nouvelle entrée SeoPosition
                $position = new SeoPosition();
                $position->setKeyword($keyword);
                $position->setPosition($data['position']);
                $position->setClicks($data['clicks']);
                $position->setImpressions($data['impressions']);
                $position->setDate($today);

                $this->entityManager->persist($position);

                // Mettre à jour lastSyncAt
                $keyword->setLastSyncAt($now);

                $synced++;

                $this->logger->info('Synced keyword position', [
                    'keyword' => $keyword->getKeyword(),
                    'position' => $data['position'],
                    'clicks' => $data['clicks'],
                    'impressions' => $data['impressions'],
                ]);

            } catch (\Exception $e) {
                $this->logger->error('Error syncing keyword', [
                    'keyword' => $keyword->getKeyword(),
                    'error' => $e->getMessage(),
                ]);
                $errors++;
            }
        }

        $this->entityManager->flush();

        $message = sprintf(
            '%d mot(s)-clé(s) synchronisé(s)',
            $synced
        );

        if ($skipped > 0) {
            $message .= sprintf(', %d sans données', $skipped);
        }

        if ($errors > 0) {
            $message .= sprintf(', %d erreur(s)', $errors);
        }

        return [
            'synced' => $synced,
            'skipped' => $skipped,
            'errors' => $errors,
            'message' => $message,
        ];
    }

    /**
     * Vérifie si les données sont à jour (moins de 12h).
     */
    public function isDataUpToDate(): bool
    {
        $needingSync = $this->keywordRepository->findKeywordsNeedingSync(self::CACHE_TTL_HOURS);
        return empty($needingSync);
    }

    /**
     * Retourne la date de dernière synchronisation.
     */
    public function getLastSyncDate(): ?\DateTimeImmutable
    {
        $keywords = $this->keywordRepository->findActiveKeywords();

        if (empty($keywords)) {
            return null;
        }

        $lastSync = null;
        foreach ($keywords as $keyword) {
            $keywordSync = $keyword->getLastSyncAt();
            if ($keywordSync !== null && ($lastSync === null || $keywordSync > $lastSync)) {
                $lastSync = $keywordSync;
            }
        }

        return $lastSync;
    }
}
