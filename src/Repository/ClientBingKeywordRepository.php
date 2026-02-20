<?php

namespace App\Repository;

use App\Entity\ClientBingKeyword;
use App\Entity\ClientSite;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ClientBingKeyword>
 */
class ClientBingKeywordRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ClientBingKeyword::class);
    }

    public function findByClientSiteAndKeyword(ClientSite $site, string $keyword): ?ClientBingKeyword
    {
        return $this->createQueryBuilder('k')
            ->where('k.clientSite = :site')
            ->andWhere('k.keyword = :keyword')
            ->setParameter('site', $site)
            ->setParameter('keyword', $keyword)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return ClientBingKeyword[]
     */
    public function findActiveByClientSite(ClientSite $site): array
    {
        return $this->createQueryBuilder('k')
            ->where('k.clientSite = :site')
            ->andWhere('k.isActive = true')
            ->setParameter('site', $site)
            ->orderBy('k.keyword', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function getActiveCount(ClientSite $site): int
    {
        return (int) $this->createQueryBuilder('k')
            ->select('COUNT(k.id)')
            ->where('k.clientSite = :site')
            ->andWhere('k.isActive = true')
            ->setParameter('site', $site)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
