<?php

namespace App\Repository;

use App\Entity\ClientSeoDailyTotal;
use App\Entity\ClientSite;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ClientSeoDailyTotal>
 */
class ClientSeoDailyTotalRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ClientSeoDailyTotal::class);
    }

    public function findByDateAndSite(ClientSite $clientSite, \DateTimeImmutable $date): ?ClientSeoDailyTotal
    {
        return $this->createQueryBuilder('t')
            ->where('t.clientSite = :site')
            ->andWhere('t.date = :date')
            ->setParameter('site', $clientSite)
            ->setParameter('date', $date)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return ClientSeoDailyTotal[]
     */
    public function findByDateRange(ClientSite $clientSite, \DateTimeImmutable $startDate, \DateTimeImmutable $endDate): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.clientSite = :site')
            ->andWhere('t.date >= :start')
            ->andWhere('t.date <= :end')
            ->setParameter('site', $clientSite)
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate)
            ->orderBy('t.date', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return array{clicks: int, impressions: int, avgPosition: float, days: int}
     */
    public function getAggregatedTotals(ClientSite $clientSite, \DateTimeImmutable $startDate, \DateTimeImmutable $endDate): array
    {
        $result = $this->createQueryBuilder('t')
            ->select('SUM(t.clicks) as clicks, SUM(t.impressions) as impressions, AVG(t.position) as avgPosition, COUNT(t.id) as days')
            ->where('t.clientSite = :site')
            ->andWhere('t.date >= :start')
            ->andWhere('t.date <= :end')
            ->setParameter('site', $clientSite)
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate)
            ->getQuery()
            ->getSingleResult();

        return [
            'clicks' => (int) ($result['clicks'] ?? 0),
            'impressions' => (int) ($result['impressions'] ?? 0),
            'avgPosition' => round((float) ($result['avgPosition'] ?? 0), 1),
            'days' => (int) ($result['days'] ?? 0),
        ];
    }

    public function getLastDate(ClientSite $clientSite): ?\DateTimeImmutable
    {
        $result = $this->createQueryBuilder('t')
            ->select('MAX(t.date) as lastDate')
            ->where('t.clientSite = :site')
            ->setParameter('site', $clientSite)
            ->getQuery()
            ->getSingleScalarResult();

        return $result ? new \DateTimeImmutable($result) : null;
    }
}
