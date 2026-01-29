<?php

namespace App\Service;

use App\Entity\SeoKeyword;
use App\Entity\SeoPosition;
use App\Repository\SeoKeywordRepository;
use App\Repository\SeoPositionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class SeoDataImportService
{
    private const CACHE_TTL_HOURS = 12;

    public function __construct(
        private GoogleSearchConsoleService $gscService,
        private SeoKeywordRepository $keywordRepository,
        private SeoPositionRepository $positionRepository,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {}

    /**
     * Synchronise les données GSC pour tous les mots-clés actifs.
     * Utilise la dimension date pour récupérer les clics journaliers.
     * (GSC a un délai de 2-3 jours, donc on récupère J-3 à J-7)
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

        $synced = 0;
        $skipped = 0;
        $errors = 0;
        $now = new \DateTimeImmutable();

        // Récupérer toutes les données GSC avec dimension date (J-3 à J-7)
        $startDate = (new \DateTimeImmutable('-7 days'))->setTime(0, 0, 0);
        $endDate = (new \DateTimeImmutable('-3 days'))->setTime(0, 0, 0);

        $dailyGscData = $this->gscService->fetchDailyKeywordsData($startDate, $endDate);

        if (empty($dailyGscData)) {
            return [
                'synced' => 0,
                'skipped' => 0,
                'errors' => 0,
                'message' => 'Aucune donnée retournée par GSC',
            ];
        }

        // Traiter chaque jour
        foreach ($dailyGscData as $dateStr => $gscData) {
            $date = new \DateTimeImmutable($dateStr);

            // Vérifier si on a déjà des données pour cette date
            $existingCount = count($this->positionRepository->findAllForDate($date));
            if ($existingCount >= count($keywords) && !$force) {
                continue; // Déjà complet pour cette date
            }

            foreach ($keywords as $keyword) {
                try {
                    $keywordLower = strtolower($keyword->getKeyword());
                    $data = $this->findBestMatchingData($keywordLower, $gscData);

                    if ($data === null) {
                        $skipped++;
                        continue;
                    }

                    // Chercher une entrée existante pour cette date
                    $position = $this->positionRepository->findByKeywordAndDate($keyword, $date);

                    if ($position) {
                        if ($force) {
                            // Mettre à jour l'entrée existante
                            $position->setPosition($data['position']);
                            $position->setClicks($data['clicks']);
                            $position->setImpressions($data['impressions']);
                            $synced++;
                        }
                    } else {
                        // Créer une nouvelle entrée SeoPosition
                        $position = new SeoPosition();
                        $position->setKeyword($keyword);
                        $position->setPosition($data['position']);
                        $position->setClicks($data['clicks']);
                        $position->setImpressions($data['impressions']);
                        $position->setDate($date);
                        $this->entityManager->persist($position);
                        $synced++;
                    }

                    // Mettre à jour lastSyncAt et lastSeenInGsc
                    $keyword->setLastSyncAt($now);
                    $keyword->setLastSeenInGsc($now);

                    $this->logger->info('Synced keyword position', [
                        'keyword' => $keyword->getKeyword(),
                        'date' => $date->format('Y-m-d'),
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
     * Trouve la meilleure correspondance pour un mot-clé dans les données GSC.
     * Utilise un matching flexible : exact > contains > partial.
     *
     * @param array<string, array{position: float, clicks: int, impressions: int}> $gscData
     * @return array{position: float, clicks: int, impressions: int}|null
     */
    private function findBestMatchingData(string $keyword, array $gscData): ?array
    {
        // Normaliser le mot-clé (enlever accents pour comparaison)
        $keywordNormalized = $this->normalizeString($keyword);

        // 1. Correspondance exacte
        if (isset($gscData[$keyword])) {
            return $gscData[$keyword];
        }

        // 2. Correspondance exacte normalisée
        foreach ($gscData as $query => $data) {
            if ($this->normalizeString($query) === $keywordNormalized) {
                return $data;
            }
        }

        // 3. La requête GSC contient le mot-clé (ou l'inverse)
        $bestMatch = null;
        $bestImpressions = 0;

        foreach ($gscData as $query => $data) {
            $queryNormalized = $this->normalizeString($query);

            // Le mot-clé est contenu dans la requête GSC
            if (str_contains($queryNormalized, $keywordNormalized) ||
                str_contains($keywordNormalized, $queryNormalized)) {
                // Prendre celui avec le plus d'impressions
                if ($data['impressions'] > $bestImpressions) {
                    $bestMatch = $data;
                    $bestImpressions = $data['impressions'];
                }
            }
        }

        return $bestMatch;
    }

    /**
     * Normalise une chaîne (minuscules, sans accents).
     */
    private function normalizeString(string $str): string
    {
        $str = strtolower($str);
        // Remplacer les accents courants
        $accents = ['é', 'è', 'ê', 'ë', 'à', 'â', 'ä', 'ù', 'û', 'ü', 'ô', 'ö', 'î', 'ï', 'ç'];
        $noAccents = ['e', 'e', 'e', 'e', 'a', 'a', 'a', 'u', 'u', 'u', 'o', 'o', 'i', 'i', 'c'];
        return str_replace($accents, $noAccents, $str);
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

    /**
     * Importe automatiquement les nouveaux mots-clés depuis GSC.
     * Filtrage progressif selon le volume :
     * - < 100 keywords : tout importer
     * - 100-1000 keywords : >= 10 impressions
     * - > 1000 keywords : >= 100 impressions
     *
     * Évite les doublons accent/non-accent en normalisant la comparaison.
     *
     * @return array{imported: int, total_gsc: int, min_impressions: int, message: string}
     */
    public function importNewKeywords(): array
    {
        if (!$this->gscService->isAvailable()) {
            return [
                'imported' => 0,
                'total_gsc' => 0,
                'min_impressions' => 0,
                'message' => 'Google Search Console non connecté',
            ];
        }

        $gscData = $this->gscService->fetchAllKeywordsData();
        $totalKeywords = count($gscData);

        if ($totalKeywords === 0) {
            return [
                'imported' => 0,
                'total_gsc' => 0,
                'min_impressions' => 0,
                'message' => 'Aucune donnée retournée par GSC',
            ];
        }

        // Filtrage progressif selon le volume
        $minImpressions = match (true) {
            $totalKeywords < 100 => 0,
            $totalKeywords < 1000 => 10,
            default => 100,
        };

        // Récupérer les mots-clés existants en base (versions normalisées pour éviter doublons accent)
        $existingKeywords = $this->keywordRepository->findAllKeywordStrings();
        $existingNormalized = array_map(fn($k) => $this->normalizeString($k), $existingKeywords);

        $imported = 0;
        $now = new \DateTimeImmutable();

        foreach ($gscData as $keyword => $data) {
            // Filtrage par impressions
            if ($data['impressions'] < $minImpressions) {
                continue;
            }

            // Skip si déjà en base (comparaison exacte)
            if (in_array(strtolower($keyword), $existingKeywords, true)) {
                continue;
            }

            // Skip si une variante accent/non-accent existe déjà
            $keywordNormalized = $this->normalizeString($keyword);
            if (in_array($keywordNormalized, $existingNormalized, true)) {
                continue;
            }

            // Créer le nouveau mot-clé
            $seoKeyword = new SeoKeyword();
            $seoKeyword->setKeyword($keyword);
            $seoKeyword->setSource(SeoKeyword::SOURCE_AUTO_GSC);
            $seoKeyword->setRelevanceLevel(SeoKeyword::RELEVANCE_MEDIUM);
            $seoKeyword->setLastSeenInGsc($now);

            $this->entityManager->persist($seoKeyword);
            $imported++;

            // Ajouter à la liste des existants pour éviter d'importer les 2 variantes dans la même session
            $existingKeywords[] = strtolower($keyword);
            $existingNormalized[] = $keywordNormalized;

            $this->logger->info('Auto-imported new keyword from GSC', [
                'keyword' => $keyword,
                'impressions' => $data['impressions'],
            ]);
        }

        if ($imported > 0) {
            $this->entityManager->flush();
        }

        return [
            'imported' => $imported,
            'total_gsc' => $totalKeywords,
            'min_impressions' => $minImpressions,
            'message' => sprintf(
                '%d nouveau(x) mot(s)-clé(s) importé(s) (seuil: %d impressions, %d requêtes GSC)',
                $imported,
                $minImpressions,
                $totalKeywords
            ),
        ];
    }

    /**
     * Désactive les mots-clés auto-importés absents de GSC depuis 30 jours.
     *
     * @return array{deactivated: int, message: string}
     */
    public function deactivateMissingKeywords(int $daysThreshold = 30): array
    {
        $threshold = new \DateTimeImmutable("-{$daysThreshold} days");

        $deactivated = $this->keywordRepository->deactivateAutoKeywordsNotSeenSince($threshold);

        if ($deactivated > 0) {
            $this->logger->info('Deactivated missing keywords', [
                'count' => $deactivated,
                'threshold_days' => $daysThreshold,
            ]);
        }

        return [
            'deactivated' => $deactivated,
            'message' => sprintf('%d mot(s)-clé(s) désactivé(s) (absents depuis %d jours)', $deactivated, $daysThreshold),
        ];
    }
}
