<?php

namespace App\Command;

use App\Repository\SeoKeywordRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:seo-merge-accent-duplicates',
    description: 'Fusionne les mots-clés doublons avec/sans accents (garde la version accentuée)',
)]
class SeoMergeAccentDuplicatesCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private SeoKeywordRepository $keywordRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Affiche ce qui serait fusionné sans rien faire');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = $input->getOption('dry-run');

        $io->title('Fusion des mots-clés doublons (variantes accent/non-accent)');

        if ($dryRun) {
            $io->warning('Mode dry-run activé');
        }

        $conn = $this->entityManager->getConnection();

        // Récupérer tous les mots-clés via SQL brut pour éviter les problèmes de buffer
        $allKeywords = $conn->fetchAllAssociative('SELECT id, keyword, created_at FROM seo_keyword');

        // Grouper par version normalisée
        $groups = [];
        foreach ($allKeywords as $row) {
            $normalized = $this->normalizeString($row['keyword']);
            if (!isset($groups[$normalized])) {
                $groups[$normalized] = [];
            }
            $groups[$normalized][] = $row;
        }

        // Trouver les groupes avec plus d'un mot-clé (doublons)
        $duplicateGroups = array_filter($groups, fn($g) => count($g) > 1);

        if (empty($duplicateGroups)) {
            $io->success('Aucun doublon accent/non-accent trouvé !');
            return Command::SUCCESS;
        }

        $io->text(sprintf('%d groupe(s) de doublons trouvé(s)', count($duplicateGroups)));
        $io->newLine();

        // Préparer toutes les opérations à effectuer
        $operations = [];

        foreach ($duplicateGroups as $normalized => $keywordGroup) {
            // Choisir le mot-clé à garder : préférer celui avec accents
            usort($keywordGroup, function ($a, $b) {
                $aHasAccents = $this->hasAccents($a['keyword']);
                $bHasAccents = $this->hasAccents($b['keyword']);

                // Préférer celui avec accents
                if ($aHasAccents && !$bHasAccents) return -1;
                if (!$aHasAccents && $bHasAccents) return 1;

                // Si égalité, préférer le plus ancien (créé en premier)
                return $a['created_at'] <=> $b['created_at'];
            });

            $keepRow = array_shift($keywordGroup);
            $keepId = (int) $keepRow['id'];

            $io->section(sprintf('Groupe "%s"', $normalized));
            $io->text(sprintf('  Garde : #%d "%s"', $keepId, $keepRow['keyword']));

            foreach ($keywordGroup as $duplicateRow) {
                $dupId = (int) $duplicateRow['id'];
                $io->text(sprintf('  Fusionne : #%d "%s"', $dupId, $duplicateRow['keyword']));

                $operations[] = [
                    'keepId' => $keepId,
                    'dupId' => $dupId,
                ];
            }
        }

        $totalMerged = count($operations);
        $totalPositionsMoved = 0;

        // Exécuter les opérations si pas en dry-run
        if (!$dryRun) {
            foreach ($operations as $op) {
                $keepId = $op['keepId'];
                $dupId = $op['dupId'];

                // Compter les positions à déplacer
                $positionsCount = (int) $conn->fetchOne(
                    'SELECT COUNT(*) FROM seo_position WHERE keyword_id = ?',
                    [$dupId]
                );

                // Déplacer les positions vers le mot-clé à garder
                // (seulement celles qui n'existent pas déjà pour cette date)
                $conn->executeStatement('
                    UPDATE seo_position sp1
                    SET keyword_id = ?
                    WHERE keyword_id = ?
                    AND NOT EXISTS (
                        SELECT 1 FROM (
                            SELECT date FROM seo_position WHERE keyword_id = ?
                        ) sp2
                        WHERE sp2.date = sp1.date
                    )
                ', [$keepId, $dupId, $keepId]);

                // Supprimer les positions restantes (doublons de date)
                $conn->executeStatement(
                    'DELETE FROM seo_position WHERE keyword_id = ?',
                    [$dupId]
                );

                // Supprimer le mot-clé doublon
                $conn->executeStatement(
                    'DELETE FROM seo_keyword WHERE id = ?',
                    [$dupId]
                );

                $totalPositionsMoved += $positionsCount;
            }
        }

        $io->newLine();

        if ($dryRun) {
            $io->success(sprintf(
                '%d mot(s)-clé(s) seraient fusionné(s)',
                $totalMerged
            ));
        } else {
            $io->success(sprintf(
                '%d mot(s)-clé(s) fusionné(s), ~%d position(s) traitée(s)',
                $totalMerged,
                $totalPositionsMoved
            ));
        }

        return Command::SUCCESS;
    }

    /**
     * Normalise une chaîne (minuscules, sans accents).
     */
    private function normalizeString(string $str): string
    {
        $str = strtolower($str);
        $accents = ['é', 'è', 'ê', 'ë', 'à', 'â', 'ä', 'ù', 'û', 'ü', 'ô', 'ö', 'î', 'ï', 'ç', 'œ', 'æ'];
        $noAccents = ['e', 'e', 'e', 'e', 'a', 'a', 'a', 'u', 'u', 'u', 'o', 'o', 'i', 'i', 'c', 'oe', 'ae'];
        return str_replace($accents, $noAccents, $str);
    }

    /**
     * Vérifie si une chaîne contient des accents.
     */
    private function hasAccents(string $str): bool
    {
        return $str !== $this->normalizeString($str);
    }
}
