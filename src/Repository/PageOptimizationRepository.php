<?php

namespace App\Repository;

use App\Entity\PageOptimization;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PageOptimization>
 */
class PageOptimizationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PageOptimization::class);
    }

    /**
     * @return PageOptimization[]
     */
    public function findAllActive(): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('p.url', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findByUrl(string $url): ?PageOptimization
    {
        return $this->createQueryBuilder('p')
            ->where('p.url = :url')
            ->setParameter('url', $url)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
