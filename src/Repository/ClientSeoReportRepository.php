<?php

namespace App\Repository;

use App\Entity\ClientSeoReport;
use App\Entity\ClientSite;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ClientSeoReport>
 */
class ClientSeoReportRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ClientSeoReport::class);
    }

    /**
     * @return ClientSeoReport[]
     */
    public function findByClientSite(ClientSite $site): array
    {
        return $this->createQueryBuilder('r')
            ->where('r.clientSite = :site')
            ->setParameter('site', $site)
            ->orderBy('r.generatedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findLatestByClientSite(ClientSite $site): ?ClientSeoReport
    {
        return $this->createQueryBuilder('r')
            ->where('r.clientSite = :site')
            ->setParameter('site', $site)
            ->orderBy('r.generatedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
