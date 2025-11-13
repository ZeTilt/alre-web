<?php

namespace App\Repository;

use App\Entity\Client;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Client>
 *
 * @method Client|null find($id, $lockMode = null, $lockVersion = null)
 * @method Client|null findOneBy(array $criteria, array $orderBy = null)
 * @method Client[]    findAll()
 * @method Client[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ClientRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Client::class);
    }

    /**
     * @return Client[] Returns an array of Client objects
     */
    public function findActiveClients(): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Client[] Returns an array of Client objects
     */
    public function findByType(string $type): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.type = :type')
            ->andWhere('c.isActive = :active')
            ->setParameter('type', $type)
            ->setParameter('active', true)
            ->orderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function search(string $query): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.isActive = :active')
            ->andWhere('
                c.name LIKE :query 
                OR c.companyName LIKE :query 
                OR c.email LIKE :query
                OR c.contactFirstName LIKE :query
                OR c.contactLastName LIKE :query
            ')
            ->setParameter('active', true)
            ->setParameter('query', '%' . $query . '%')
            ->orderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}