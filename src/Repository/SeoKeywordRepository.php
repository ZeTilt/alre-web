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
     * Retourne les mots-clés actifs non vus dans GSC depuis $threshold.
     *
     * @return SeoKeyword[]
     */
    public function findKeywordsToDeactivate(\DateTimeImmutable $threshold): array
    {
        return $this->createQueryBuilder('k')
            ->where('k.isActive = :active')
            ->andWhere('k.lastSeenInGsc IS NOT NULL')
            ->andWhere('k.lastSeenInGsc < :threshold')
            ->setParameter('active', true)
            ->setParameter('threshold', $threshold)
            ->getQuery()
            ->getResult();
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

    /**
     * Retourne la date de première apparition GSC (MIN de SeoPosition.date) par mot-clé actif,
     * avec le niveau de pertinence.
     *
     * @return array<array{id: int, relevanceLevel: string, firstSeen: string}>
     */
    public function getKeywordFirstAppearances(): array
    {
        return $this->createQueryBuilder('k')
            ->select('k.id, k.relevanceLevel, SUBSTRING(MIN(p.date), 1, 10) as firstSeen')
            ->join('k.positions', 'p')
            ->where('k.isActive = :active')
            ->setParameter('active', true)
            ->groupBy('k.id, k.relevanceLevel')
            ->orderBy('firstSeen', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Retourne la date de première apparition GSC pour TOUS les mots-clés (actifs + inactifs).
     *
     * @return array<array{id: int, relevanceLevel: string, firstSeen: string}>
     */
    public function getKeywordFirstAppearancesAll(): array
    {
        return $this->createQueryBuilder('k')
            ->select('k.id, k.relevanceLevel, SUBSTRING(MIN(p.date), 1, 10) as firstSeen')
            ->join('k.positions', 'p')
            ->groupBy('k.id, k.relevanceLevel')
            ->orderBy('firstSeen', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Retourne les mots-clés désactivés avec leur date de désactivation.
     *
     * @return array<array{id: int, deactivatedDate: string}>
     */
    public function getKeywordDeactivations(): array
    {
        return $this->createQueryBuilder('k')
            ->select('k.id, SUBSTRING(k.deactivatedAt, 1, 10) as deactivatedDate')
            ->where('k.isActive = :active')
            ->andWhere('k.deactivatedAt IS NOT NULL')
            ->setParameter('active', false)
            ->getQuery()
            ->getResult();
    }

    /**
     * Retourne le nombre de mots-clés inactifs.
     */
    public function countInactive(): int
    {
        return (int) $this->createQueryBuilder('k')
            ->select('COUNT(k.id)')
            ->where('k.isActive = :active')
            ->setParameter('active', false)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Retourne le décompte des mots-clés actifs par niveau de pertinence.
     *
     * @return array<array{relevanceLevel: string, cnt: int}>
     */
    public function getRelevanceCounts(): array
    {
        return $this->createQueryBuilder('k')
            ->select("k.relevanceLevel, COUNT(k.id) as cnt")
            ->where('k.isActive = :active')
            ->setParameter('active', true)
            ->groupBy('k.relevanceLevel')
            ->getQuery()
            ->getResult();
    }
}
