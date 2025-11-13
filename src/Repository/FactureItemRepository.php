<?php

namespace App\Repository;

use App\Entity\FactureItem;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<FactureItem>
 *
 * @method FactureItem|null find($id, $lockMode = null, $lockVersion = null)
 * @method FactureItem|null findOneBy(array $criteria, array $orderBy = null)
 * @method FactureItem[]    findAll()
 * @method FactureItem[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class FactureItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FactureItem::class);
    }

    public function findByFacture(int $factureId): array
    {
        return $this->createQueryBuilder('fi')
            ->andWhere('fi.facture = :factureId')
            ->setParameter('factureId', $factureId)
            ->orderBy('fi.position', 'ASC')
            ->getQuery()
            ->getResult();
    }
}