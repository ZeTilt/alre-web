<?php

namespace App\Repository;

use App\Entity\Prospect;
use App\Entity\ProspectInteraction;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProspectInteraction>
 */
class ProspectInteractionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProspectInteraction::class);
    }

    /**
     * @return ProspectInteraction[]
     */
    public function findByProspect(Prospect $prospect, int $limit = null): array
    {
        $qb = $this->createQueryBuilder('i')
            ->andWhere('i.prospect = :prospect')
            ->setParameter('prospect', $prospect)
            ->orderBy('i.createdAt', 'DESC');

        if ($limit) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @return ProspectInteraction[]
     */
    public function findRecent(int $limit = 10): array
    {
        return $this->createQueryBuilder('i')
            ->orderBy('i.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
