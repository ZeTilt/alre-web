<?php

namespace App\Repository;

use App\Entity\ClientSeoKeyword;
use App\Entity\ClientSeoPosition;
use App\Entity\ClientSite;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ClientSeoPosition>
 */
class ClientSeoPositionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ClientSeoPosition::class);
    }

    public function findByKeywordAndDate(ClientSeoKeyword $keyword, \DateTimeImmutable $date): ?ClientSeoPosition
    {
        return $this->createQueryBuilder('p')
            ->where('p.clientSeoKeyword = :keyword')
            ->andWhere('p.date = :date')
            ->setParameter('keyword', $keyword)
            ->setParameter('date', $date)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return array<int, array{keywordId: int, avgPosition: float, totalClicks: int, totalImpressions: int}>
     */
    public function getAveragePositionsForAllKeywords(
        ClientSite $clientSite,
        \DateTimeImmutable $startDate,
        \DateTimeImmutable $endDate
    ): array {
        $results = $this->createQueryBuilder('p')
            ->select(
                'IDENTITY(p.clientSeoKeyword) as keywordId',
                'AVG(p.position) as avgPosition',
                'SUM(p.clicks) as totalClicks',
                'SUM(p.impressions) as totalImpressions'
            )
            ->join('p.clientSeoKeyword', 'k')
            ->where('k.clientSite = :site')
            ->andWhere('k.isActive = :active')
            ->andWhere('p.date >= :startDate')
            ->andWhere('p.date <= :endDate')
            ->setParameter('site', $clientSite)
            ->setParameter('active', true)
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->groupBy('p.clientSeoKeyword')
            ->getQuery()
            ->getResult();

        $data = [];
        foreach ($results as $row) {
            $data[(int) $row['keywordId']] = [
                'keywordId' => (int) $row['keywordId'],
                'avgPosition' => round((float) $row['avgPosition'], 1),
                'totalClicks' => (int) $row['totalClicks'],
                'totalImpressions' => (int) $row['totalImpressions'],
            ];
        }

        return $data;
    }

    /**
     * @return ClientSeoPosition[]
     */
    public function findAllForDateRange(
        ClientSite $clientSite,
        \DateTimeImmutable $startDate,
        \DateTimeImmutable $endDate
    ): array {
        return $this->createQueryBuilder('p')
            ->join('p.clientSeoKeyword', 'k')
            ->where('k.clientSite = :site')
            ->andWhere('p.date >= :startDate')
            ->andWhere('p.date <= :endDate')
            ->setParameter('site', $clientSite)
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('p.date', 'DESC')
            ->addOrderBy('k.keyword', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
