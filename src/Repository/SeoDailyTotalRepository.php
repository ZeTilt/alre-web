<?php

namespace App\Repository;

use App\Entity\SeoDailyTotal;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SeoDailyTotal>
 */
class SeoDailyTotalRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SeoDailyTotal::class);
    }

    public function findByDate(\DateTimeImmutable $date, ?string $source = null): ?SeoDailyTotal
    {
        $qb = $this->createQueryBuilder('t')
            ->where('t.date = :date')
            ->setParameter('date', $date, Types::DATE_IMMUTABLE);

        if ($source !== null) {
            $qb->andWhere('t.source = :source')->setParameter('source', $source);
        } else {
            $qb->andWhere('t.source = :source')->setParameter('source', SeoDailyTotal::SOURCE_GOOGLE);
        }

        return $qb->getQuery()->getOneOrNullResult();
    }

    /**
     * Retourne les totaux pour une période donnée, triés par date.
     *
     * @return SeoDailyTotal[]
     */
    public function findByDateRange(\DateTimeImmutable $startDate, \DateTimeImmutable $endDate, ?string $source = null): array
    {
        $qb = $this->createQueryBuilder('t')
            ->where('t.date >= :start')
            ->andWhere('t.date <= :end')
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate)
            ->orderBy('t.date', 'ASC');

        if ($source !== null) {
            $qb->andWhere('t.source = :source')->setParameter('source', $source);
        } else {
            $qb->andWhere('t.source = :source')->setParameter('source', SeoDailyTotal::SOURCE_GOOGLE);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Retourne les totaux des N derniers jours.
     *
     * @return SeoDailyTotal[]
     */
    public function findLastDays(int $days = 30, ?string $source = null): array
    {
        $startDate = new \DateTimeImmutable("-{$days} days");

        $qb = $this->createQueryBuilder('t')
            ->where('t.date >= :start')
            ->setParameter('start', $startDate)
            ->orderBy('t.date', 'ASC');

        if ($source !== null) {
            $qb->andWhere('t.source = :source')->setParameter('source', $source);
        } else {
            $qb->andWhere('t.source = :source')->setParameter('source', SeoDailyTotal::SOURCE_GOOGLE);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Calcule les totaux agrégés pour une période.
     *
     * @return array{clicks: int, impressions: int, avgPosition: float, days: int}
     */
    public function getAggregatedTotals(\DateTimeImmutable $startDate, \DateTimeImmutable $endDate, ?string $source = null): array
    {
        $qb = $this->createQueryBuilder('t')
            ->select('SUM(t.clicks) as clicks, SUM(t.impressions) as impressions, AVG(t.position) as avgPosition, COUNT(t.id) as days')
            ->where('t.date >= :start')
            ->andWhere('t.date <= :end')
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate);

        if ($source !== null) {
            $qb->andWhere('t.source = :source')->setParameter('source', $source);
        } else {
            $qb->andWhere('t.source = :source')->setParameter('source', SeoDailyTotal::SOURCE_GOOGLE);
        }

        $result = $qb->getQuery()->getSingleResult();

        return [
            'clicks' => (int) ($result['clicks'] ?? 0),
            'impressions' => (int) ($result['impressions'] ?? 0),
            'avgPosition' => round((float) ($result['avgPosition'] ?? 0), 1),
            'days' => (int) ($result['days'] ?? 0),
        ];
    }

    /**
     * Retourne la date du dernier enregistrement.
     */
    public function getLastDate(?string $source = null): ?\DateTimeImmutable
    {
        $qb = $this->createQueryBuilder('t')
            ->select('MAX(t.date) as lastDate');

        if ($source !== null) {
            $qb->andWhere('t.source = :source')->setParameter('source', $source);
        }

        $result = $qb->getQuery()->getSingleScalarResult();

        return $result ? new \DateTimeImmutable($result) : null;
    }
}
