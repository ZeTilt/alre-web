<?php

namespace App\Repository;

use App\Entity\ClientSeoDailyTotal;
use App\Entity\ClientSite;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Types\Types;
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

    public function findByDateAndSite(ClientSite $clientSite, \DateTimeImmutable $date, string $source = ClientSeoDailyTotal::SOURCE_GOOGLE): ?ClientSeoDailyTotal
    {
        return $this->createQueryBuilder('t')
            ->where('t.clientSite = :site')
            ->andWhere('t.date = :date')
            ->andWhere('t.source = :source')
            ->setParameter('site', $clientSite)
            ->setParameter('date', $date, Types::DATE_IMMUTABLE)
            ->setParameter('source', $source)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findByDateSiteAndSource(ClientSite $clientSite, \DateTimeImmutable $date, string $source): ?ClientSeoDailyTotal
    {
        return $this->findByDateAndSite($clientSite, $date, $source);
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
            ->setParameter('start', $startDate, Types::DATE_IMMUTABLE)
            ->setParameter('end', $endDate, Types::DATE_IMMUTABLE)
            ->orderBy('t.date', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return ClientSeoDailyTotal[]
     */
    public function findByDateRangeAndSource(ClientSite $clientSite, \DateTimeImmutable $startDate, \DateTimeImmutable $endDate, string $source): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.clientSite = :site')
            ->andWhere('t.date >= :start')
            ->andWhere('t.date <= :end')
            ->andWhere('t.source = :source')
            ->setParameter('site', $clientSite)
            ->setParameter('start', $startDate, Types::DATE_IMMUTABLE)
            ->setParameter('end', $endDate, Types::DATE_IMMUTABLE)
            ->setParameter('source', $source)
            ->orderBy('t.date', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return array{clicks: int, impressions: int, avgPosition: float, days: int}
     */
    public function getAggregatedTotals(ClientSite $clientSite, \DateTimeImmutable $startDate, \DateTimeImmutable $endDate, ?string $source = null): array
    {
        $qb = $this->createQueryBuilder('t')
            ->select('SUM(t.clicks) as clicks, SUM(t.impressions) as impressions, AVG(t.position) as avgPosition, COUNT(t.id) as days')
            ->where('t.clientSite = :site')
            ->andWhere('t.date >= :start')
            ->andWhere('t.date <= :end')
            ->setParameter('site', $clientSite)
            ->setParameter('start', $startDate, Types::DATE_IMMUTABLE)
            ->setParameter('end', $endDate, Types::DATE_IMMUTABLE);

        if ($source !== null) {
            $qb->andWhere('t.source = :source')
               ->setParameter('source', $source);
        }

        $result = $qb->getQuery()->getSingleResult();

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
