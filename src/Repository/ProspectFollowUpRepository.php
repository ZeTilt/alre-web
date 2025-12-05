<?php

namespace App\Repository;

use App\Entity\Prospect;
use App\Entity\ProspectFollowUp;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProspectFollowUp>
 */
class ProspectFollowUpRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProspectFollowUp::class);
    }

    /**
     * Find overdue + due within N days (urgent follow-ups)
     * @return ProspectFollowUp[]
     */
    public function findUrgent(int $daysAhead = 2): array
    {
        $futureDate = new \DateTime("+{$daysAhead} days");
        $futureDate->setTime(23, 59, 59);

        return $this->createQueryBuilder('f')
            ->andWhere('f.isCompleted = false')
            ->andWhere('f.dueAt <= :futureDate')
            ->setParameter('futureDate', $futureDate)
            ->orderBy('f.dueAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return ProspectFollowUp[]
     */
    public function findOverdue(): array
    {
        $today = new \DateTime();
        $today->setTime(0, 0, 0);

        return $this->createQueryBuilder('f')
            ->andWhere('f.isCompleted = false')
            ->andWhere('f.dueAt < :today')
            ->setParameter('today', $today)
            ->orderBy('f.dueAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return ProspectFollowUp[]
     */
    public function findDueSoon(int $days = 2): array
    {
        $today = new \DateTime();
        $today->setTime(0, 0, 0);

        $futureDate = new \DateTime("+{$days} days");
        $futureDate->setTime(23, 59, 59);

        return $this->createQueryBuilder('f')
            ->andWhere('f.isCompleted = false')
            ->andWhere('f.dueAt >= :today')
            ->andWhere('f.dueAt <= :futureDate')
            ->setParameter('today', $today)
            ->setParameter('futureDate', $futureDate)
            ->orderBy('f.dueAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return ProspectFollowUp[]
     */
    public function findPendingForProspect(Prospect $prospect): array
    {
        return $this->createQueryBuilder('f')
            ->andWhere('f.prospect = :prospect')
            ->andWhere('f.isCompleted = false')
            ->setParameter('prospect', $prospect)
            ->orderBy('f.dueAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return ProspectFollowUp[]
     */
    public function findPending(): array
    {
        return $this->createQueryBuilder('f')
            ->andWhere('f.isCompleted = false')
            ->orderBy('f.dueAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function countUrgent(int $daysAhead = 2): int
    {
        $futureDate = new \DateTime("+{$daysAhead} days");
        $futureDate->setTime(23, 59, 59);

        return $this->createQueryBuilder('f')
            ->select('COUNT(f.id)')
            ->andWhere('f.isCompleted = false')
            ->andWhere('f.dueAt <= :futureDate')
            ->setParameter('futureDate', $futureDate)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
