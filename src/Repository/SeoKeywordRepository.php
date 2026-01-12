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
}
