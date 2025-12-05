<?php

namespace App\Repository;

use App\Entity\Prospect;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Prospect>
 */
class ProspectRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Prospect::class);
    }

    /**
     * @return Prospect[]
     */
    public function findByStatus(string $status): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.status = :status')
            ->setParameter('status', $status)
            ->orderBy('p.lastContactAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Prospect[]
     */
    public function findActiveProspects(): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.status NOT IN (:closedStatuses)')
            ->setParameter('closedStatuses', [Prospect::STATUS_WON, Prospect::STATUS_LOST])
            ->orderBy('p.lastContactAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get prospects grouped by status for pipeline view
     * @return array<string, Prospect[]>
     */
    public function findGroupedByStatus(): array
    {
        $prospects = $this->createQueryBuilder('p')
            ->andWhere('p.status NOT IN (:closedStatuses)')
            ->setParameter('closedStatuses', [Prospect::STATUS_WON, Prospect::STATUS_LOST])
            ->orderBy('p.lastContactAt', 'DESC')
            ->getQuery()
            ->getResult();

        $grouped = [
            Prospect::STATUS_IDENTIFIED => [],
            Prospect::STATUS_CONTACTED => [],
            Prospect::STATUS_IN_DISCUSSION => [],
            Prospect::STATUS_QUOTE_SENT => [],
        ];

        foreach ($prospects as $prospect) {
            $status = $prospect->getStatus();
            if (isset($grouped[$status])) {
                $grouped[$status][] = $prospect;
            }
        }

        return $grouped;
    }

    public function countByStatus(string $status): int
    {
        return $this->count(['status' => $status]);
    }

    public function countWonThisMonth(): int
    {
        $firstDayOfMonth = new \DateTime('first day of this month');
        $firstDayOfMonth->setTime(0, 0, 0);

        return $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.status = :status')
            ->andWhere('p.updatedAt >= :firstDay')
            ->setParameter('status', Prospect::STATUS_WON)
            ->setParameter('firstDay', $firstDayOfMonth)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countLostThisMonth(): int
    {
        $firstDayOfMonth = new \DateTime('first day of this month');
        $firstDayOfMonth->setTime(0, 0, 0);

        return $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.status = :status')
            ->andWhere('p.updatedAt >= :firstDay')
            ->setParameter('status', Prospect::STATUS_LOST)
            ->setParameter('firstDay', $firstDayOfMonth)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function getConversionRate(): float
    {
        $total = $this->count([]);
        if ($total === 0) {
            return 0.0;
        }

        $won = $this->count(['status' => Prospect::STATUS_WON]);
        return round(($won / $total) * 100, 1);
    }

    public function getTotalEstimatedValue(): float
    {
        $result = $this->createQueryBuilder('p')
            ->select('SUM(p.estimatedValue)')
            ->andWhere('p.status NOT IN (:closedStatuses)')
            ->setParameter('closedStatuses', [Prospect::STATUS_WON, Prospect::STATUS_LOST])
            ->getQuery()
            ->getSingleScalarResult();

        return (float) ($result ?? 0);
    }

    /**
     * Find prospects without recent contact
     * @return Prospect[]
     */
    public function findToFollowUp(int $daysSinceLastContact = 7): array
    {
        $cutoffDate = new \DateTime("-{$daysSinceLastContact} days");

        return $this->createQueryBuilder('p')
            ->andWhere('p.status NOT IN (:closedStatuses)')
            ->andWhere('(p.lastContactAt IS NULL OR p.lastContactAt < :cutoff)')
            ->setParameter('closedStatuses', [Prospect::STATUS_WON, Prospect::STATUS_LOST])
            ->setParameter('cutoff', $cutoffDate)
            ->orderBy('p.lastContactAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
