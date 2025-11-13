<?php

namespace App\Repository;

use App\Entity\DevisItem;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DevisItem>
 *
 * @method DevisItem|null find($id, $lockMode = null, $lockVersion = null)
 * @method DevisItem|null findOneBy(array $criteria, array $orderBy = null)
 * @method DevisItem[]    findAll()
 * @method DevisItem[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class DevisItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DevisItem::class);
    }

    public function findByDevis(int $devisId): array
    {
        return $this->createQueryBuilder('di')
            ->andWhere('di.devis = :devisId')
            ->setParameter('devisId', $devisId)
            ->orderBy('di.position', 'ASC')
            ->getQuery()
            ->getResult();
    }
}