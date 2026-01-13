<?php

namespace App\Repository;

use App\Entity\SeoKeyword;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SeoKeyword>
 */
class SeoKeywordRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SeoKeyword::class);
    }

    /**
     * Retourne tous les mots-clés actifs.
     *
     * @return SeoKeyword[]
     */
    public function findActiveKeywords(): array
    {
        return $this->createQueryBuilder('k')
            ->where('k.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('k.keyword', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Retourne les mots-clés qui nécessitent une synchronisation.
     * (lastSyncAt est null ou date de plus de $hours heures)
     *
     * @return SeoKeyword[]
     */
    public function findKeywordsNeedingSync(int $hours = 12): array
    {
        $threshold = new \DateTimeImmutable("-{$hours} hours");

        return $this->createQueryBuilder('k')
            ->where('k.isActive = :active')
            ->andWhere('k.lastSyncAt IS NULL OR k.lastSyncAt < :threshold')
            ->setParameter('active', true)
            ->setParameter('threshold', $threshold)
            ->orderBy('k.lastSyncAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Retourne tous les mots-clés avec leur dernière position.
     *
     * @return SeoKeyword[]
     */
    public function findAllWithLatestPosition(): array
    {
        return $this->createQueryBuilder('k')
            ->leftJoin('k.positions', 'p')
            ->addSelect('p')
            ->orderBy('k.keyword', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Retourne tous les mots-clés (en lowercase) pour comparaison.
     *
     * @return string[]
     */
    public function findAllKeywordStrings(): array
    {
        $results = $this->createQueryBuilder('k')
            ->select('LOWER(k.keyword)')
            ->getQuery()
            ->getSingleColumnResult();

        return $results;
    }

    /**
     * Trouve un mot-clé par son texte (case-insensitive).
     */
    public function findByKeywordText(string $keyword): ?SeoKeyword
    {
        return $this->createQueryBuilder('k')
            ->where('LOWER(k.keyword) = LOWER(:keyword)')
            ->setParameter('keyword', $keyword)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Désactive les mots-clés auto-importés non vus dans GSC depuis $threshold.
     *
     * @return int Nombre de mots-clés désactivés
     */
    public function deactivateAutoKeywordsNotSeenSince(\DateTimeImmutable $threshold): int
    {
        return $this->createQueryBuilder('k')
            ->update()
            ->set('k.isActive', ':inactive')
            ->where('k.source = :source')
            ->andWhere('k.lastSeenInGsc < :threshold')
            ->andWhere('k.isActive = :active')
            ->setParameter('inactive', false)
            ->setParameter('source', SeoKeyword::SOURCE_AUTO_GSC)
            ->setParameter('threshold', $threshold)
            ->setParameter('active', true)
            ->getQuery()
            ->execute();
    }

    /**
     * Retourne les mots-clés par niveau de pertinence.
     *
     * @return SeoKeyword[]
     */
    public function findByRelevanceLevel(string $level): array
    {
        return $this->createQueryBuilder('k')
            ->where('k.isActive = :active')
            ->andWhere('k.relevanceLevel = :level')
            ->setParameter('active', true)
            ->setParameter('level', $level)
            ->orderBy('k.keyword', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
