<?php

namespace App\Repository;

use App\Entity\ClientSeoPage;
use App\Entity\ClientSite;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ClientSeoPage>
 */
class ClientSeoPageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ClientSeoPage::class);
    }

    /**
     * @return array<array{url: string, totalClicks: int, totalImpressions: int, avgPosition: float, avgCtr: float}>
     */
    public function findTopPages(ClientSite $clientSite, int $limit = 20): array
    {
        return $this->createQueryBuilder('p')
            ->select(
                'p.url',
                'SUM(p.clicks) as totalClicks',
                'SUM(p.impressions) as totalImpressions',
                'AVG(p.position) as avgPosition',
                'AVG(p.ctr) as avgCtr'
            )
            ->where('p.clientSite = :site')
            ->setParameter('site', $clientSite)
            ->groupBy('p.url')
            ->orderBy('totalClicks', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findByUrlHashAndDate(ClientSite $clientSite, string $urlHash, \DateTimeImmutable $date): ?ClientSeoPage
    {
        return $this->createQueryBuilder('p')
            ->where('p.clientSite = :site')
            ->andWhere('p.urlHash = :hash')
            ->andWhere('p.date = :date')
            ->setParameter('site', $clientSite)
            ->setParameter('hash', $urlHash)
            ->setParameter('date', $date)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
