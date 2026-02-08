<?php

namespace App\Repository;

use App\Entity\Client;
use App\Entity\ClientSite;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ClientSite>
 */
class ClientSiteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ClientSite::class);
    }

    /**
     * @return ClientSite[]
     */
    public function findActiveByClient(Client $client): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.client = :client')
            ->andWhere('s.isActive = :active')
            ->setParameter('client', $client)
            ->setParameter('active', true)
            ->orderBy('s.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return ClientSite[]
     */
    public function findAllActive(): array
    {
        return $this->createQueryBuilder('s')
            ->join('s.client', 'c')
            ->addSelect('c')
            ->where('s.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('c.name', 'ASC')
            ->addOrderBy('s.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
