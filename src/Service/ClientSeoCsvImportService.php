<?php

namespace App\Service;

use App\Entity\ClientSeoDailyTotal;
use App\Entity\ClientSeoImport;
use App\Entity\ClientSeoKeyword;
use App\Entity\ClientSeoPage;
use App\Entity\ClientSeoPosition;
use App\Entity\ClientSite;
use App\Repository\ClientSeoDailyTotalRepository;
use App\Repository\ClientSeoKeywordRepository;
use App\Repository\ClientSeoPageRepository;
use App\Repository\ClientSeoPositionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class ClientSeoCsvImportService
{
    private const BATCH_SIZE = 200;

    /** @var array<string, ClientSeoKeyword> Cache keywords pendant un import pour eviter les doublons */
    private array $keywordCache = [];

    public function __construct(
        private EntityManagerInterface $entityManager,
        private ClientSeoKeywordRepository $keywordRepository,
        private ClientSeoPositionRepository $positionRepository,
        private ClientSeoPageRepository $pageRepository,
        private ClientSeoDailyTotalRepository $dailyTotalRepository,
    ) {
    }

    /**
     * Importe un fichier CSV ou ZIP (export GSC) pour un site client.
     *
     * @return array{type: string, imported: int, updated: int, skipped: int, periodStart: ?string, periodEnd: ?string, message: string}
     */
    public function importFromFile(ClientSite $site, UploadedFile $file): array
    {
        $this->keywordCache = []; // Reset cache pour chaque import

        $extension = strtolower($file->getClientOriginalExtension());
        $originalFilename = $file->getClientOriginalName();

        // Si ZIP, scanner tous les CSV et importer ceux reconnus (Requetes + Pages)
        if ($extension === 'zip') {
            if (!class_exists(\ZipArchive::class)) {
                throw new \RuntimeException('L\'extension PHP "zip" n\'est pas installee. Veuillez importer un fichier CSV directement.');
            }
            return $this->importFromZip($site, $file->getPathname(), $originalFilename);
        }

        // Import CSV simple
        return $this->importSingleCsv($site, $file->getPathname(), $originalFilename);
    }

    /**
     * Importe un ZIP GSC contenant plusieurs CSV (Requetes, Pages, Appareils, Pays, etc.).
     * Identifie et importe automatiquement les CSV Requetes et Pages, ignore les autres.
     */
    private function importFromZip(ClientSite $site, string $zipPath, string $originalFilename): array
    {
        $zip = new \ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new \RuntimeException('Impossible d\'ouvrir le fichier ZIP.');
        }

        $tempDir = sys_get_temp_dir() . '/client_seo_' . uniqid();
        mkdir($tempDir, 0777, true);
        $zip->extractTo($tempDir);
        $zip->close();

        $csvFiles = glob($tempDir . '/*.csv');
        if (empty($csvFiles)) {
            $this->cleanupTempDir($tempDir);
            throw new \RuntimeException('Aucun fichier CSV trouve dans le ZIP.');
        }

        // Scanner chaque CSV pour identifier son type par ses en-tetes
        $totalImported = 0;
        $totalUpdated = 0;
        $totalSkipped = 0;
        $globalPeriodStart = null;
        $globalPeriodEnd = null;
        $importedTypes = [];
        $skippedFiles = [];

        foreach ($csvFiles as $csvFile) {
            $headers = $this->readCsvHeaders($csvFile);
            $type = $this->detectType($headers);

            if ($type === null) {
                $skippedFiles[] = basename($csvFile) . ' (' . ($headers[0] ?? '?') . ')';
                continue;
            }

            $hasDateColumn = $this->hasDateColumn($headers);

            if ($type === 'daily_chart') {
                $result = $this->importDailyChart($site, $csvFile, $headers);
            } else {
                $result = match ($type) {
                    ClientSeoImport::TYPE_PERFORMANCE_QUERIES => $this->importQueries($site, $csvFile, $headers, $hasDateColumn),
                    ClientSeoImport::TYPE_PERFORMANCE_PAGES => $this->importPages($site, $csvFile, $headers, $hasDateColumn),
                };
            }

            $totalImported += $result['imported'];
            $totalUpdated += $result['updated'];
            $totalSkipped += $result['skipped'];
            if ($type !== 'daily_chart') {
                $importedTypes[] = $type;
            } else {
                $importedTypes[] = 'daily_chart';
            }

            if ($result['periodStart']) {
                if ($globalPeriodStart === null || $result['periodStart'] < $globalPeriodStart) {
                    $globalPeriodStart = $result['periodStart'];
                }
            }
            if ($result['periodEnd']) {
                if ($globalPeriodEnd === null || $result['periodEnd'] > $globalPeriodEnd) {
                    $globalPeriodEnd = $result['periodEnd'];
                }
            }
        }

        $this->cleanupTempDir($tempDir);

        if (empty($importedTypes)) {
            $allHeaders = implode(', ', array_map(fn($f) => basename($f), $csvFiles));
            throw new \RuntimeException(
                'Aucun CSV exploitable dans le ZIP (' . count($csvFiles) . ' fichiers : ' . $allHeaders . ').'
                . ' Attendus : CSV avec colonne "Requêtes" ou "Pages" en premiere colonne.'
            );
        }

        // Enregistrer un seul import pour le ZIP
        $mainType = in_array(ClientSeoImport::TYPE_PERFORMANCE_QUERIES, $importedTypes)
            ? ClientSeoImport::TYPE_PERFORMANCE_QUERIES
            : (in_array(ClientSeoImport::TYPE_PERFORMANCE_PAGES, $importedTypes)
                ? ClientSeoImport::TYPE_PERFORMANCE_PAGES
                : ClientSeoImport::TYPE_PERFORMANCE_QUERIES);

        $import = new ClientSeoImport();
        $import->setClientSite($site);
        $import->setType($mainType);
        $import->setOriginalFilename($originalFilename);
        $import->setRowsImported($totalImported);
        $import->setRowsSkipped($totalSkipped);
        $import->setStatus(ClientSeoImport::STATUS_SUCCESS);

        if ($globalPeriodStart) {
            $import->setPeriodStart(new \DateTimeImmutable($globalPeriodStart));
        }
        if ($globalPeriodEnd) {
            $import->setPeriodEnd(new \DateTimeImmutable($globalPeriodEnd));
        }

        $this->entityManager->persist($import);
        $this->entityManager->flush();

        $labelMap = [
            ClientSeoImport::TYPE_PERFORMANCE_QUERIES => 'Requetes',
            ClientSeoImport::TYPE_PERFORMANCE_PAGES => 'Pages',
            'daily_chart' => 'Graphique',
        ];
        $typeLabels = array_map(fn($t) => $labelMap[$t] ?? $t, array_unique($importedTypes));
        $message = sprintf(
            'ZIP importe : %s. %d nouvelles, %d mises a jour, %d ignorees. Periode : %s a %s.',
            implode(' + ', $typeLabels),
            $totalImported,
            $totalUpdated,
            $totalSkipped,
            $globalPeriodStart ?? '-',
            $globalPeriodEnd ?? '-'
        );
        if (!empty($skippedFiles)) {
            $message .= ' Fichiers ignores : ' . implode(', ', $skippedFiles) . '.';
        }

        return [
            'type' => implode('+', array_unique($importedTypes)),
            'imported' => $totalImported,
            'updated' => $totalUpdated,
            'skipped' => $totalSkipped,
            'periodStart' => $globalPeriodStart,
            'periodEnd' => $globalPeriodEnd,
            'message' => $message,
        ];
    }

    /**
     * Importe un seul fichier CSV.
     */
    private function importSingleCsv(ClientSite $site, string $csvPath, string $originalFilename): array
    {
        $headers = $this->readCsvHeaders($csvPath);
        $type = $this->detectType($headers);

        if ($type === null) {
            throw new \RuntimeException(
                'Format CSV non reconnu. En-tetes trouves : ' . implode(', ', $headers)
                . '. Attendus : "Requêtes" (ou "Top queries") ou "Pages" (ou "Top pages") dans la premiere colonne.'
                . ' Assurez-vous d\'exporter depuis l\'onglet Requetes ou Pages dans Google Search Console (pas Appareil, Pays, etc.).'
            );
        }

        $hasDateColumn = $this->hasDateColumn($headers);

        $result = match ($type) {
            ClientSeoImport::TYPE_PERFORMANCE_QUERIES => $this->importQueries($site, $csvPath, $headers, $hasDateColumn),
            ClientSeoImport::TYPE_PERFORMANCE_PAGES => $this->importPages($site, $csvPath, $headers, $hasDateColumn),
        };

        // Recalculer les totaux journaliers si import queries
        if ($type === ClientSeoImport::TYPE_PERFORMANCE_QUERIES && $result['periodStart'] && $result['periodEnd']) {
            $this->recalculateDailyTotals(
                $site,
                new \DateTimeImmutable($result['periodStart']),
                new \DateTimeImmutable($result['periodEnd'])
            );
        }

        // Enregistrer l'import
        $import = new ClientSeoImport();
        $import->setClientSite($site);
        $import->setType($type);
        $import->setOriginalFilename($originalFilename);
        $import->setRowsImported($result['imported'] + $result['updated']);
        $import->setRowsSkipped($result['skipped']);
        $import->setStatus(ClientSeoImport::STATUS_SUCCESS);

        if ($result['periodStart']) {
            $import->setPeriodStart(new \DateTimeImmutable($result['periodStart']));
        }
        if ($result['periodEnd']) {
            $import->setPeriodEnd(new \DateTimeImmutable($result['periodEnd']));
        }

        $this->entityManager->persist($import);
        $this->entityManager->flush();

        return $result;
    }

    /**
     * Importe Graphique.csv : totaux journaliers (Date, Clics, Impressions, CTR, Position).
     */
    private function importDailyChart(ClientSite $site, string $csvPath, array $headers): array
    {
        $rows = $this->parseCsvRows($csvPath, $headers);

        $imported = 0;
        $updated = 0;
        $skipped = 0;
        $periodStart = null;
        $periodEnd = null;

        foreach ($rows as $row) {
            $dateStr = $this->findColumn($row, $headers, ['Date']);
            if ($dateStr === null || trim($dateStr) === '') {
                $skipped++;
                continue;
            }

            $date = $this->parseDate(trim($dateStr));
            if ($date === null) {
                $skipped++;
                continue;
            }

            $clicks = (int) ($this->findColumn($row, $headers, ['Clicks', 'Clics']) ?? 0);
            $impressions = (int) ($this->findColumn($row, $headers, ['Impressions']) ?? 0);
            $position = (float) ($this->findColumn($row, $headers, ['Position']) ?? 0);

            $dateKey = $date->format('Y-m-d');
            if ($periodStart === null || $dateKey < $periodStart) {
                $periodStart = $dateKey;
            }
            if ($periodEnd === null || $dateKey > $periodEnd) {
                $periodEnd = $dateKey;
            }

            // Upsert daily total
            $existing = $this->dailyTotalRepository->findByDateAndSite($site, $date);
            if ($existing) {
                $existing->setClicks($clicks);
                $existing->setImpressions($impressions);
                $existing->setPosition(round($position, 1));
                $updated++;
            } else {
                $total = new ClientSeoDailyTotal();
                $total->setClientSite($site);
                $total->setDate($date);
                $total->setClicks($clicks);
                $total->setImpressions($impressions);
                $total->setPosition(round($position, 1));
                $this->entityManager->persist($total);
                $imported++;
            }
        }

        $this->entityManager->flush();

        return [
            'type' => 'daily_chart',
            'imported' => $imported,
            'updated' => $updated,
            'skipped' => $skipped,
            'periodStart' => $periodStart,
            'periodEnd' => $periodEnd,
            'message' => sprintf('Graphique : %d jours importes, %d mis a jour', $imported, $updated),
        ];
    }

    private function cleanupTempDir(string $dir): void
    {
        $files = glob($dir . '/*');
        foreach ($files as $f) {
            @unlink($f);
        }
        @rmdir($dir);
    }

    private function readCsvHeaders(string $csvPath): array
    {
        $content = file_get_contents($csvPath);

        // Retirer le BOM UTF-8
        if (str_starts_with($content, "\xEF\xBB\xBF")) {
            $content = substr($content, 3);
        }

        // Ecrire le contenu sans BOM dans un fichier temp
        $tempPath = $csvPath . '.nobom';
        file_put_contents($tempPath, $content);

        $file = new \SplFileObject($tempPath, 'r');
        $file->setFlags(\SplFileObject::READ_CSV);

        // Detecter le delimiteur
        $firstLine = trim(fgets(fopen($tempPath, 'r')));
        if (substr_count($firstLine, "\t") > substr_count($firstLine, ',')) {
            $file->setCsvControl("\t");
        } else {
            $file->setCsvControl(',');
        }

        $file->rewind();
        $headers = $file->current();

        @unlink($tempPath);

        if (!is_array($headers)) {
            throw new \RuntimeException('Impossible de lire les en-tetes du fichier CSV.');
        }

        // Nettoyer les en-tetes
        return array_map(fn($h) => trim((string) $h), $headers);
    }

    private function detectType(array $headers): ?string
    {
        $first = strtolower($headers[0] ?? '');

        // EN: Top queries, Query, Queries — FR: Requêtes les plus fréquentes, Requête
        if (str_contains($first, 'top queries') || str_contains($first, 'query') || str_contains($first, 'queries')
            || str_contains($first, 'requ') // requête, requêtes les plus fréquentes
        ) {
            return ClientSeoImport::TYPE_PERFORMANCE_QUERIES;
        }

        // EN: Top pages, Page — FR: Pages les plus fréquentes, Pages les plus populaires
        if (str_contains($first, 'top pages') || str_contains($first, 'page')) {
            return ClientSeoImport::TYPE_PERFORMANCE_PAGES;
        }

        // Graphique.csv : premiere colonne = "Date" avec Clics/Impressions/CTR/Position
        if ($first === 'date' && count($headers) >= 4) {
            $hasClics = false;
            foreach ($headers as $h) {
                if (in_array(strtolower($h), ['clics', 'clicks'])) {
                    $hasClics = true;
                    break;
                }
            }
            if ($hasClics) {
                return 'daily_chart';
            }
        }

        return null;
    }

    private function hasDateColumn(array $headers): bool
    {
        foreach ($headers as $header) {
            if (strtolower(trim($header)) === 'date') {
                return true;
            }
        }
        return false;
    }

    /**
     * Importe les requetes (queries) depuis un CSV GSC.
     */
    private function importQueries(ClientSite $site, string $csvPath, array $headers, bool $hasDateColumn): array
    {
        $rows = $this->parseCsvRows($csvPath, $headers);

        $imported = 0;
        $updated = 0;
        $skipped = 0;
        $periodStart = null;
        $periodEnd = null;
        $batchCount = 0;

        foreach ($rows as $row) {
            $queryKey = $this->findColumn($row, $headers, ['Top queries', 'Query', 'Queries', 'Top Queries', 'Requêtes les plus fréquentes', 'Requête']);
            if ($queryKey === null || trim($queryKey) === '') {
                $skipped++;
                continue;
            }

            $query = trim($queryKey);
            $queryLower = mb_strtolower($query);
            $clicks = (int) ($this->findColumn($row, $headers, ['Clicks', 'Clics']) ?? 0);
            $impressions = (int) ($this->findColumn($row, $headers, ['Impressions']) ?? 0);
            $ctrRaw = $this->findColumn($row, $headers, ['CTR']);
            $position = (float) ($this->findColumn($row, $headers, ['Position']) ?? 0);

            // Trouver ou creer le mot-cle (cache partage entre tous les CSV du ZIP)
            $keyword = $this->getOrCreateKeyword($query, $queryLower, $site);

            // Gerer la date
            if ($hasDateColumn) {
                $dateStr = $this->findColumn($row, $headers, ['Date']);
                if ($dateStr === null || trim($dateStr) === '') {
                    $skipped++;
                    continue;
                }
                $date = $this->parseDate(trim($dateStr));
                if ($date === null) {
                    $skipped++;
                    continue;
                }
            } else {
                // Sans colonne date (export agrege), utiliser la date du jour
                $date = new \DateTimeImmutable('today');
            }

            // Tracking periode
            $dateKey = $date->format('Y-m-d');
            if ($periodStart === null || $dateKey < $periodStart) {
                $periodStart = $dateKey;
            }
            if ($periodEnd === null || $dateKey > $periodEnd) {
                $periodEnd = $dateKey;
            }

            // Flush necessaire pour que findByKeywordAndDate fonctionne avec les nouveaux keywords
            if ($batchCount > 0 && $batchCount % self::BATCH_SIZE === 0) {
                $this->entityManager->flush();
            }

            // Upsert position
            $existingPosition = $this->positionRepository->findByKeywordAndDate($keyword, $date);
            if ($existingPosition) {
                $existingPosition->setPosition($position);
                $existingPosition->setClicks($clicks);
                $existingPosition->setImpressions($impressions);
                $updated++;
            } else {
                $newPosition = new ClientSeoPosition();
                $newPosition->setClientSeoKeyword($keyword);
                $newPosition->setDate($date);
                $newPosition->setPosition($position);
                $newPosition->setClicks($clicks);
                $newPosition->setImpressions($impressions);
                $this->entityManager->persist($newPosition);
                $imported++;
            }

            $batchCount++;
            if ($batchCount % self::BATCH_SIZE === 0) {
                $this->entityManager->flush();
            }
        }

        $this->entityManager->flush();

        return [
            'type' => ClientSeoImport::TYPE_PERFORMANCE_QUERIES,
            'imported' => $imported,
            'updated' => $updated,
            'skipped' => $skipped,
            'periodStart' => $periodStart,
            'periodEnd' => $periodEnd,
            'message' => sprintf(
                'Import requetes : %d nouvelles, %d mises a jour, %d ignorees. Periode : %s a %s',
                $imported,
                $updated,
                $skipped,
                $periodStart ?? '-',
                $periodEnd ?? '-'
            ),
        ];
    }

    /**
     * Importe les pages depuis un CSV GSC.
     */
    private function importPages(ClientSite $site, string $csvPath, array $headers, bool $hasDateColumn): array
    {
        $rows = $this->parseCsvRows($csvPath, $headers);

        $imported = 0;
        $updated = 0;
        $skipped = 0;
        $periodStart = null;
        $periodEnd = null;
        $batchCount = 0;

        foreach ($rows as $row) {
            $pageUrl = $this->findColumn($row, $headers, ['Top pages', 'Page', 'Pages', 'Top Pages', 'Pages les plus fréquentes', 'Pages les plus populaires']);
            if ($pageUrl === null || trim($pageUrl) === '') {
                $skipped++;
                continue;
            }

            $pageUrl = trim($pageUrl);
            $clicks = (int) ($this->findColumn($row, $headers, ['Clicks', 'Clics']) ?? 0);
            $impressions = (int) ($this->findColumn($row, $headers, ['Impressions']) ?? 0);
            $ctrRaw = $this->findColumn($row, $headers, ['CTR']);
            $ctr = $this->parseCtr($ctrRaw);
            $position = (float) ($this->findColumn($row, $headers, ['Position']) ?? 0);

            // Gerer la date
            if ($hasDateColumn) {
                $dateStr = $this->findColumn($row, $headers, ['Date']);
                if ($dateStr === null || trim($dateStr) === '') {
                    $skipped++;
                    continue;
                }
                $date = $this->parseDate(trim($dateStr));
                if ($date === null) {
                    $skipped++;
                    continue;
                }
            } else {
                // Sans date, on utilise la date du jour
                $date = new \DateTimeImmutable('today');
            }

            $dateKey = $date->format('Y-m-d');
            if ($periodStart === null || $dateKey < $periodStart) {
                $periodStart = $dateKey;
            }
            if ($periodEnd === null || $dateKey > $periodEnd) {
                $periodEnd = $dateKey;
            }

            // Upsert page
            $urlHash = hash('sha256', $pageUrl);
            $existingPage = $this->pageRepository->findByUrlHashAndDate($site, $urlHash, $date);
            if ($existingPage) {
                $existingPage->setClicks($clicks);
                $existingPage->setImpressions($impressions);
                $existingPage->setCtr($ctr);
                $existingPage->setPosition($position);
                $updated++;
            } else {
                $newPage = new ClientSeoPage();
                $newPage->setClientSite($site);
                $newPage->setUrl($pageUrl);
                $newPage->setDate($date);
                $newPage->setClicks($clicks);
                $newPage->setImpressions($impressions);
                $newPage->setCtr($ctr);
                $newPage->setPosition($position);
                $this->entityManager->persist($newPage);
                $imported++;
            }

            $batchCount++;
            if ($batchCount % self::BATCH_SIZE === 0) {
                $this->entityManager->flush();
            }
        }

        $this->entityManager->flush();

        return [
            'type' => ClientSeoImport::TYPE_PERFORMANCE_PAGES,
            'imported' => $imported,
            'updated' => $updated,
            'skipped' => $skipped,
            'periodStart' => $periodStart,
            'periodEnd' => $periodEnd,
            'message' => sprintf(
                'Import pages : %d nouvelles, %d mises a jour, %d ignorees. Periode : %s a %s',
                $imported,
                $updated,
                $skipped,
                $periodStart ?? '-',
                $periodEnd ?? '-'
            ),
        ];
    }

    /**
     * Recalcule les totaux journaliers pour une periode donnee.
     */
    private function recalculateDailyTotals(ClientSite $site, \DateTimeImmutable $start, \DateTimeImmutable $end): void
    {
        // Recuperer toutes les positions de la periode
        $positions = $this->positionRepository->findAllForDateRange($site, $start, $end);

        // Agreger par jour
        $dailyData = [];
        foreach ($positions as $position) {
            $dateKey = $position->getDate()->format('Y-m-d');
            if (!isset($dailyData[$dateKey])) {
                $dailyData[$dateKey] = ['clicks' => 0, 'impressions' => 0, 'positions' => [], 'date' => $position->getDate()];
            }
            $dailyData[$dateKey]['clicks'] += $position->getClicks();
            $dailyData[$dateKey]['impressions'] += $position->getImpressions();
            if ($position->getPosition() > 0) {
                $dailyData[$dateKey]['positions'][] = $position->getPosition();
            }
        }

        // Upsert totaux
        foreach ($dailyData as $dateKey => $data) {
            $avgPosition = !empty($data['positions']) ? array_sum($data['positions']) / count($data['positions']) : 0;

            $existing = $this->dailyTotalRepository->findByDateAndSite($site, $data['date']);
            if ($existing) {
                $existing->setClicks($data['clicks']);
                $existing->setImpressions($data['impressions']);
                $existing->setPosition(round($avgPosition, 1));
            } else {
                $total = new ClientSeoDailyTotal();
                $total->setClientSite($site);
                $total->setDate($data['date']);
                $total->setClicks($data['clicks']);
                $total->setImpressions($data['impressions']);
                $total->setPosition(round($avgPosition, 1));
                $this->entityManager->persist($total);
            }
        }

        $this->entityManager->flush();
    }

    /**
     * Parse toutes les lignes du CSV (apres les en-tetes).
     */
    private function parseCsvRows(string $csvPath, array $headers): array
    {
        $content = file_get_contents($csvPath);

        // Retirer le BOM UTF-8
        if (str_starts_with($content, "\xEF\xBB\xBF")) {
            $content = substr($content, 3);
        }

        $tempPath = $csvPath . '.parse';
        file_put_contents($tempPath, $content);

        $file = new \SplFileObject($tempPath, 'r');
        $file->setFlags(\SplFileObject::READ_CSV | \SplFileObject::SKIP_EMPTY);

        // Detecter le delimiteur
        $firstLine = trim(fgets(fopen($tempPath, 'r')));
        if (substr_count($firstLine, "\t") > substr_count($firstLine, ',')) {
            $file->setCsvControl("\t");
        } else {
            $file->setCsvControl(',');
        }

        $rows = [];
        $isFirst = true;
        foreach ($file as $line) {
            if ($isFirst) {
                $isFirst = false;
                continue; // Skip header
            }
            if (!is_array($line) || count($line) < 2) {
                continue;
            }
            $rows[] = $line;
        }

        @unlink($tempPath);

        return $rows;
    }

    /**
     * Trouve la valeur d'une colonne par ses noms possibles.
     */
    private function findColumn(array $row, array $headers, array $possibleNames): ?string
    {
        foreach ($possibleNames as $name) {
            $index = $this->findHeaderIndex($headers, $name);
            if ($index !== null && isset($row[$index])) {
                return $row[$index];
            }
        }
        return null;
    }

    private function findHeaderIndex(array $headers, string $name): ?int
    {
        $nameLower = strtolower($name);
        foreach ($headers as $index => $header) {
            if (strtolower(trim($header)) === $nameLower) {
                return $index;
            }
        }
        return null;
    }

    private function parseDate(string $dateStr): ?\DateTimeImmutable
    {
        // Formats courants GSC: YYYY-MM-DD
        $formats = ['Y-m-d', 'd/m/Y', 'm/d/Y'];
        foreach ($formats as $format) {
            $date = \DateTimeImmutable::createFromFormat($format, $dateStr);
            if ($date !== false) {
                return $date->setTime(0, 0, 0);
            }
        }
        return null;
    }

    private function parseCtr(?string $ctrRaw): float
    {
        if ($ctrRaw === null) {
            return 0.0;
        }
        // GSC exporte le CTR sous forme "5.23%" ou "0.0523"
        $ctrRaw = trim($ctrRaw);
        $ctrRaw = str_replace('%', '', $ctrRaw);
        $ctrRaw = str_replace(',', '.', $ctrRaw);
        $value = (float) $ctrRaw;

        // Si la valeur est < 1, c'est probablement un ratio (0.0523 = 5.23%)
        if ($value > 0 && $value < 1) {
            $value *= 100;
        }

        return round($value, 2);
    }

    private function getOrCreateKeyword(string $query, string $queryLower, ClientSite $site): ClientSeoKeyword
    {
        if (isset($this->keywordCache[$queryLower])) {
            return $this->keywordCache[$queryLower];
        }

        $keyword = $this->keywordRepository->findByKeywordAndSite($query, $site);
        if (!$keyword) {
            $keyword = new ClientSeoKeyword();
            $keyword->setClientSite($site);
            $keyword->setKeyword($query);
            $this->entityManager->persist($keyword);
            $this->entityManager->flush();
        }

        $this->keywordCache[$queryLower] = $keyword;

        return $keyword;
    }
}
