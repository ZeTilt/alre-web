<?php

namespace App\Repository;

use App\Entity\Devis;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Devis>
 *
 * @method Devis|null find($id, $lockMode = null, $lockVersion = null)
 * @method Devis|null findOneBy(array $criteria, array $orderBy = null)
 * @method Devis[]    findAll()
 * @method Devis[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class DevisRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Devis::class);
    }

    /**
     * @return Devis[] Returns an array of Devis objects
     */
    public function findByStatus(string $status): array
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.status = :status')
            ->setParameter('status', $status)
            ->orderBy('d.dateCreation', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Devis[] Returns an array of Devis objects
     */
    public function findPendingDevis(): array
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.status IN (:statuses)')
            ->setParameter('statuses', [Devis::STATUS_A_ENVOYER, Devis::STATUS_ENVOYE, Devis::STATUS_RELANCE])
            ->orderBy('d.dateCreation', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Devis[] Returns an array of Devis objects
     */
    public function findExpiredDevis(): array
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.dateValidite < :today')
            ->andWhere('d.status NOT IN (:closedStatuses)')
            ->setParameter('today', new \DateTimeImmutable())
            ->setParameter('closedStatuses', [
                Devis::STATUS_ACCEPTE,
                Devis::STATUS_REFUSE,
                Devis::STATUS_ANNULE,
                Devis::STATUS_EXPIRE
            ])
            ->orderBy('d.dateValidite', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Devis[] Returns an array of Devis objects
     */
    public function findRecentDevis(int $limit = 10): array
    {
        return $this->createQueryBuilder('d')
            ->orderBy('d.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findByClient(int $clientId): array
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.client = :clientId')
            ->setParameter('clientId', $clientId)
            ->orderBy('d.dateCreation', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findByYear(int $year): array
    {
        $startDate = new \DateTimeImmutable($year . '-01-01');
        $endDate = new \DateTimeImmutable($year . '-12-31');

        return $this->createQueryBuilder('d')
            ->andWhere('d.dateCreation BETWEEN :startDate AND :endDate')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('d.dateCreation', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function getStatsForYear(int $year): array
    {
        $startDate = new \DateTimeImmutable($year . '-01-01');
        $endDate = new \DateTimeImmutable($year . '-12-31');

        $qb = $this->createQueryBuilder('d')
            ->select('d.status', 'COUNT(d.id) as count', 'SUM(d.totalTtc) as total')
            ->andWhere('d.dateCreation BETWEEN :startDate AND :endDate')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->groupBy('d.status');

        return $qb->getQuery()->getResult();
    }

    public function search(string $query): array
    {
        return $this->createQueryBuilder('d')
            ->leftJoin('d.client', 'c')
            ->andWhere('
                d.number LIKE :query 
                OR d.title LIKE :query 
                OR c.name LIKE :query
                OR c.companyName LIKE :query
            ')
            ->setParameter('query', '%' . $query . '%')
            ->orderBy('d.dateCreation', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findNextNumber(): string
    {
        $year = date('Y');
        $prefix = 'DEV-' . $year . '-';
        
        $lastDevis = $this->createQueryBuilder('d')
            ->andWhere('d.number LIKE :prefix')
            ->setParameter('prefix', $prefix . '%')
            ->orderBy('d.number', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$lastDevis) {
            return $prefix . '0001';
        }

        $lastNumber = (int) substr($lastDevis->getNumber(), -4);
        return $prefix . str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
    }
}