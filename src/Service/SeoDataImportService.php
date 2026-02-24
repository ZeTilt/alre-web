<?php

namespace App\Service;

use App\Entity\SeoDailyTotal;
use App\Entity\SeoKeyword;
use App\Entity\SeoPosition;
use App\Repository\SeoDailyTotalRepository;
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
        private SeoDailyTotalRepository $dailyTotalRepository,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {}

    /**
     * Synchronise les données GSC pour tous les mots-clés actifs.
     * Requête chaque jour individuellement SANS dimension date pour obtenir
     * TOUTES les requêtes (l'API GSC filtre les requêtes faible volume avec dimension date).
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

        // Requêter chaque jour individuellement (J-1 à J-7)
        // On va jusqu'à J-1 pour avoir la date réelle de dernière impression dès le premier sync.
        // Les données J-1/J-2 sont potentiellement incomplètes (délai GSC 2-3j) mais suffisantes
        // pour déterminer lastSeenInGsc et obtenir une position approximative.
        $endDate = (new \DateTimeImmutable('-1 day'))->setTime(0, 0, 0);
        $startDate = (new \DateTimeImmutable('-7 days'))->setTime(0, 0, 0);
        $currentDate = clone $startDate;

        $dailyGscData = [];
        while ($currentDate <= $endDate) {
            // Récupérer TOUTES les requêtes pour ce jour (sans dimension date = données complètes)
            $dayData = $this->gscService->fetchAllKeywordsData($currentDate, $currentDate);
            if (!empty($dayData)) {
                $dailyGscData[$currentDate->format('Y-m-d')] = $dayData;
            }
            $currentDate = $currentDate->modify('+1 day');
            // Petit délai pour éviter le rate limiting
            usleep(50000); // 50ms
        }

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
                    $keyword->setLastSeenInGsc($date);

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
     * Synchronise les totaux journaliers (clics/impressions réels sans anonymisation).
     * Utilise la dimension date seule pour avoir les vrais chiffres.
     *
     * @return array{synced: int, skipped: int, message: string}
     */
    public function syncDailyTotals(bool $force = false): array
    {
        if (!$this->gscService->isAvailable()) {
            return [
                'synced' => 0,
                'skipped' => 0,
                'message' => 'Google Search Console non connecté',
            ];
        }

        // Récupérer les totaux journaliers (J-3 à J-30 pour avoir un bon historique)
        $endDate = (new \DateTimeImmutable('-3 days'))->setTime(0, 0, 0);
        $startDate = (new \DateTimeImmutable('-30 days'))->setTime(0, 0, 0);

        $dailyTotals = $this->gscService->fetchDailyTotals($startDate, $endDate);

        if (empty($dailyTotals)) {
            return [
                'synced' => 0,
                'skipped' => 0,
                'message' => 'Aucune donnée retournée par GSC',
            ];
        }

        $synced = 0;
        $skipped = 0;

        foreach ($dailyTotals as $dateStr => $data) {
            $date = new \DateTimeImmutable($dateStr);

            // Vérifier si on a déjà des données pour cette date
            $existing = $this->dailyTotalRepository->findByDate($date);

            if ($existing && !$force) {
                // Vérifier si les données sont différentes (mise à jour nécessaire)
                if ($existing->getClicks() === $data['clicks'] &&
                    $existing->getImpressions() === $data['impressions']) {
                    $skipped++;
                    continue;
                }
            }

            if ($existing) {
                // Mettre à jour l'entrée existante
                $existing->setClicks($data['clicks']);
                $existing->setImpressions($data['impressions']);
                $existing->setPosition($data['position']);
            } else {
                // Créer une nouvelle entrée
                $dailyTotal = new SeoDailyTotal();
                $dailyTotal->setDate($date);
                $dailyTotal->setClicks($data['clicks']);
                $dailyTotal->setImpressions($data['impressions']);
                $dailyTotal->setPosition($data['position']);
                $this->entityManager->persist($dailyTotal);
            }

            $synced++;

            $this->logger->info('Synced daily total', [
                'date' => $dateStr,
                'clicks' => $data['clicks'],
                'impressions' => $data['impressions'],
            ]);
        }

        $this->entityManager->flush();

        return [
            'synced' => $synced,
            'skipped' => $skipped,
            'message' => sprintf('%d jour(s) synchronisé(s), %d inchangé(s)', $synced, $skipped),
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
     * Importe automatiquement les nouveaux mots-clés depuis GSC
     * et réactive les mots-clés inactifs réapparus dans GSC.
     *
     * @return array{imported: int, reactivated: int, total_gsc: int, min_impressions: int, message: string}
     */
    public function importNewKeywords(): array
    {
        if (!$this->gscService->isAvailable()) {
            return [
                'imported' => 0,
                'reactivated' => 0,
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
                'reactivated' => 0,
                'total_gsc' => 0,
                'min_impressions' => 0,
                'message' => 'Aucune donnée retournée par GSC',
            ];
        }

        // Pas de filtrage : importer tous les mots-clés pour données exactes GSC
        $minImpressions = 0;

        // Récupérer les mots-clés existants en base (versions normalisées pour éviter doublons accent)
        $existingKeywords = $this->keywordRepository->findAllKeywordStrings();
        $existingNormalized = array_map(fn($k) => $this->normalizeString($k), $existingKeywords);

        $imported = 0;

        foreach ($gscData as $keyword => $data) {
            // Toujours importer si le mot-clé a des clics (important pour le tracking)
            // Sinon, filtrer par impressions
            $hasClicks = ($data['clicks'] ?? 0) > 0;
            if (!$hasClicks && $data['impressions'] < $minImpressions) {
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
            // Pertinence haute si le mot-clé a des clics
            $seoKeyword->setRelevanceLevel($hasClicks ? SeoKeyword::RELEVANCE_HIGH : SeoKeyword::RELEVANCE_MEDIUM);
            // lastSeenInGsc n'est PAS défini ici : c'est syncAllKeywords() qui le fera
            // avec la vraie date par jour (J-1 à J-7), juste après l'import.

            $this->entityManager->persist($seoKeyword);
            $imported++;

            // Ajouter à la liste des existants pour éviter d'importer les 2 variantes dans la même session
            $existingKeywords[] = strtolower($keyword);
            $existingNormalized[] = $keywordNormalized;

            $this->logger->info('Auto-imported new keyword from GSC', [
                'keyword' => $keyword,
                'clicks' => $data['clicks'] ?? 0,
                'impressions' => $data['impressions'],
            ]);
        }

        // Réactiver les mots-clés inactifs réapparus dans GSC
        $reactivated = 0;
        $inactiveKeywords = $this->keywordRepository->findBy(['isActive' => false]);

        // Indexer les données GSC par clé normalisée
        $gscNormalized = [];
        foreach ($gscData as $query => $data) {
            $gscNormalized[$this->normalizeString($query)] = $data;
        }

        foreach ($inactiveKeywords as $keyword) {
            $keywordNormalized = $this->normalizeString($keyword->getKeyword());

            if (isset($gscNormalized[$keywordNormalized])) {
                $keyword->setIsActive(true);
                $keyword->setDeactivatedAt(null);
                // Date temporaire pour éviter la re-désactivation immédiate.
                // syncAllKeywords() (J-1 à J-7) affinera avec la date exacte juste après.
                $keyword->setLastSeenInGsc((new \DateTimeImmutable('-1 day'))->setTime(0, 0, 0));
                $reactivated++;

                $this->logger->info('Reactivated keyword from GSC', [
                    'keyword' => $keyword->getKeyword(),
                    'relevanceLevel' => $keyword->getRelevanceLevel(),
                ]);
            }
        }

        if ($imported > 0 || $reactivated > 0) {
            $this->entityManager->flush();
        }

        $message = sprintf(
            '%d nouveau(x) mot(s)-clé(s) importé(s) (seuil: %d impressions, %d requêtes GSC)',
            $imported,
            $minImpressions,
            $totalKeywords
        );

        if ($reactivated > 0) {
            $message .= sprintf(', %d réactivé(s)', $reactivated);
        }

        return [
            'imported' => $imported,
            'reactivated' => $reactivated,
            'total_gsc' => $totalKeywords,
            'min_impressions' => $minImpressions,
            'message' => $message,
        ];
    }

    /**
     * Synchronise les targetUrl des mots-clés actifs depuis les données GSC (dimension page).
     * Peuple le champ targetUrl pour les mots-clés qui n'en ont pas encore.
     *
     * @return array{updated: int, message: string}
     */
    public function syncTargetUrls(): array
    {
        if (!$this->gscService->isAvailable()) {
            return [
                'updated' => 0,
                'message' => 'Google Search Console non connecté',
            ];
        }

        // Fetch keyword -> page mappings from GSC (last 7 days)
        $keywordPages = $this->gscService->fetchKeywordPages();

        if (empty($keywordPages)) {
            return [
                'updated' => 0,
                'message' => 'Aucune donnée page retournée par GSC',
            ];
        }

        $keywords = $this->keywordRepository->findActiveKeywords();
        $updated = 0;

        foreach ($keywords as $keyword) {
            // Skip keywords that already have a targetUrl
            if ($keyword->getTargetUrl() !== null && $keyword->getTargetUrl() !== '') {
                continue;
            }

            $keywordLower = strtolower($keyword->getKeyword());
            $page = $keywordPages[$keywordLower] ?? null;

            if ($page !== null) {
                $keyword->setTargetUrl($page);
                $updated++;
            }
        }

        if ($updated > 0) {
            $this->entityManager->flush();
        }

        return [
            'updated' => $updated,
            'message' => sprintf('%d mot(s)-clé(s) avec targetUrl mis à jour', $updated),
        ];
    }

    /**
     * Désactive les mots-clés absents de GSC depuis 30 jours.
     * Enregistre deactivatedAt = lastSeenInGsc + 30 jours.
     *
     * @return array{deactivated: int, message: string}
     */
    public function deactivateMissingKeywords(int $daysThreshold = 30): array
    {
        $threshold = new \DateTimeImmutable("-{$daysThreshold} days");

        $keywords = $this->keywordRepository->findKeywordsToDeactivate($threshold);
        $deactivated = count($keywords);

        foreach ($keywords as $keyword) {
            $keyword->setIsActive(false);
            $keyword->setDeactivatedAt(
                $keyword->getLastSeenInGsc()->modify("+{$daysThreshold} days")
            );
        }

        if ($deactivated > 0) {
            $this->entityManager->flush();
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
