<?php

namespace App\Repository;

use App\Entity\ClientBingDailyTotal;
use App\Entity\ClientSite;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ClientBingDailyTotal>
 */
class ClientBingDailyTotalRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ClientBingDailyTotal::class);
    }

    public function findByDateAndSite(ClientSite $site, \DateTimeImmutable $date): ?ClientBingDailyTotal
    {
        return $this->createQueryBuilder('t')
            ->where('t.clientSite = :site')
            ->andWhere('t.date = :date')
            ->setParameter('site', $site)
            ->setParameter('date', $date, Types::DATE_IMMUTABLE)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return ClientBingDailyTotal[]
     */
    public function findByDateRange(ClientSite $site, \DateTimeImmutable $startDate, \DateTimeImmutable $endDate): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.clientSite = :site')
            ->andWhere('t.date >= :start')
            ->andWhere('t.date <= :end')
            ->setParameter('site', $site)
            ->setParameter('start', $startDate, Types::DATE_IMMUTABLE)
            ->setParameter('end', $endDate, Types::DATE_IMMUTABLE)
            ->orderBy('t.date', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return array{clicks: int, impressions: int, avgPosition: float, days: int}
     */
    public function getAggregatedTotals(ClientSite $site, \DateTimeImmutable $startDate, \DateTimeImmutable $endDate): array
    {
        $result = $this->createQueryBuilder('t')
            ->select('SUM(t.clicks) as clicks, SUM(t.impressions) as impressions, AVG(t.position) as avgPosition, COUNT(t.id) as days')
            ->where('t.clientSite = :site')
            ->andWhere('t.date >= :start')
            ->andWhere('t.date <= :end')
            ->setParameter('site', $site)
            ->setParameter('start', $startDate, Types::DATE_IMMUTABLE)
            ->setParameter('end', $endDate, Types::DATE_IMMUTABLE)
            ->getQuery()
            ->getSingleResult();

        return [
            'clicks' => (int) ($result['clicks'] ?? 0),
            'impressions' => (int) ($result['impressions'] ?? 0),
            'avgPosition' => round((float) ($result['avgPosition'] ?? 0), 1),
            'days' => (int) ($result['days'] ?? 0),
        ];
    }
}
