<?php

namespace App\Repository;

use App\Entity\ClientSeoKeyword;
use App\Entity\ClientSite;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ClientSeoKeyword>
 */
class ClientSeoKeywordRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ClientSeoKeyword::class);
    }

    /**
     * @return ClientSeoKeyword[]
     */
    public function findByClientSite(ClientSite $clientSite): array
    {
        return $this->createQueryBuilder('k')
            ->where('k.clientSite = :site')
            ->andWhere('k.isActive = :active')
            ->setParameter('site', $clientSite)
            ->setParameter('active', true)
            ->orderBy('k.keyword', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findByKeywordAndSite(string $keyword, ClientSite $clientSite): ?ClientSeoKeyword
    {
        return $this->createQueryBuilder('k')
            ->where('LOWER(k.keyword) = LOWER(:keyword)')
            ->andWhere('k.clientSite = :site')
            ->setParameter('keyword', $keyword)
            ->setParameter('site', $clientSite)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return ClientSeoKeyword[]
     */
    public function findAllWithLatestPosition(ClientSite $clientSite): array
    {
        return $this->createQueryBuilder('k')
            ->leftJoin('k.positions', 'p')
            ->addSelect('p')
            ->where('k.clientSite = :site')
            ->setParameter('site', $clientSite)
            ->orderBy('k.keyword', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return array<array{relevanceLevel: string, cnt: int}>
     */
    public function getActiveCount(ClientSite $clientSite): int
    {
        return (int) $this->createQueryBuilder('k')
            ->select('COUNT(k.id)')
            ->where('k.clientSite = :site')
            ->andWhere('k.isActive = :active')
            ->setParameter('site', $clientSite)
            ->setParameter('active', true)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return array<array{id: int, firstSeen: string}>
     */
    public function getKeywordFirstAppearances(ClientSite $clientSite): array
    {
        return $this->createQueryBuilder('k')
            ->select('k.id, SUBSTRING(MIN(p.date), 1, 10) as firstSeen')
            ->join('k.positions', 'p')
            ->where('k.clientSite = :site')
            ->setParameter('site', $clientSite)
            ->groupBy('k.id')
            ->orderBy('firstSeen', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
