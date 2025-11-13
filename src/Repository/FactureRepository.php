<?php

namespace App\Repository;

use App\Entity\Facture;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Facture>
 *
 * @method Facture|null find($id, $lockMode = null, $lockVersion = null)
 * @method Facture|null findOneBy(array $criteria, array $orderBy = null)
 * @method Facture[]    findAll()
 * @method Facture[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class FactureRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Facture::class);
    }

    /**
     * @return Facture[] Returns an array of Facture objects
     */
    public function findByStatus(string $status): array
    {
        return $this->createQueryBuilder('f')
            ->andWhere('f.status = :status')
            ->setParameter('status', $status)
            ->orderBy('f.dateFacture', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Facture[] Returns an array of Facture objects
     */
    public function findUnpaidFactures(): array
    {
        return $this->createQueryBuilder('f')
            ->andWhere('f.status IN (:statuses)')
            ->setParameter('statuses', [
                Facture::STATUS_A_ENVOYER,
                Facture::STATUS_ENVOYE,
                Facture::STATUS_RELANCE,
                Facture::STATUS_EN_RETARD
            ])
            ->orderBy('f.dateEcheance', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Facture[] Returns an array of Facture objects
     */
    public function findOverdueFactures(): array
    {
        return $this->createQueryBuilder('f')
            ->andWhere('f.dateEcheance < :today')
            ->andWhere('f.status NOT IN (:paidStatuses)')
            ->setParameter('today', new \DateTimeImmutable())
            ->setParameter('paidStatuses', [Facture::STATUS_PAYE, Facture::STATUS_ANNULE])
            ->orderBy('f.dateEcheance', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Facture[] Returns an array of Facture objects
     */
    public function findRecentFactures(int $limit = 10): array
    {
        return $this->createQueryBuilder('f')
            ->orderBy('f.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findByClient(int $clientId): array
    {
        return $this->createQueryBuilder('f')
            ->andWhere('f.client = :clientId')
            ->setParameter('clientId', $clientId)
            ->orderBy('f.dateFacture', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findByYear(int $year): array
    {
        $startDate = new \DateTimeImmutable($year . '-01-01');
        $endDate = new \DateTimeImmutable($year . '-12-31');

        return $this->createQueryBuilder('f')
            ->andWhere('f.dateFacture BETWEEN :startDate AND :endDate')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('f.dateFacture', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function getStatsForYear(int $year): array
    {
        $startDate = new \DateTimeImmutable($year . '-01-01');
        $endDate = new \DateTimeImmutable($year . '-12-31');

        $qb = $this->createQueryBuilder('f')
            ->select('f.status', 'COUNT(f.id) as count', 'SUM(f.totalTtc) as total')
            ->andWhere('f.dateFacture BETWEEN :startDate AND :endDate')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->groupBy('f.status');

        return $qb->getQuery()->getResult();
    }

    public function getRevenueForYear(int $year): string
    {
        $startDate = new \DateTimeImmutable($year . '-01-01');
        $endDate = new \DateTimeImmutable($year . '-12-31');

        $result = $this->createQueryBuilder('f')
            ->select('SUM(f.totalTtc) as total')
            ->andWhere('f.dateFacture BETWEEN :startDate AND :endDate')
            ->andWhere('f.status = :status')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->setParameter('status', Facture::STATUS_PAYE)
            ->getQuery()
            ->getSingleScalarResult();

        return $result ?: '0.00';
    }

    public function getMonthlyRevenueForYear(int $year): array
    {
        $startDate = new \DateTimeImmutable($year . '-01-01');
        $endDate = new \DateTimeImmutable($year . '-12-31');

        $result = $this->createQueryBuilder('f')
            ->select('MONTH(f.dateFacture) as month', 'SUM(f.totalTtc) as total')
            ->andWhere('f.dateFacture BETWEEN :startDate AND :endDate')
            ->andWhere('f.status = :status')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->setParameter('status', Facture::STATUS_PAYE)
            ->groupBy('month')
            ->orderBy('month', 'ASC')
            ->getQuery()
            ->getResult();

        // Fill missing months with 0
        $monthlyRevenue = [];
        for ($i = 1; $i <= 12; $i++) {
            $monthlyRevenue[$i] = '0.00';
        }

        foreach ($result as $row) {
            $monthlyRevenue[(int) $row['month']] = $row['total'] ?: '0.00';
        }

        return $monthlyRevenue;
    }

    public function search(string $query): array
    {
        return $this->createQueryBuilder('f')
            ->leftJoin('f.client', 'c')
            ->andWhere('
                f.number LIKE :query 
                OR f.title LIKE :query 
                OR c.name LIKE :query
                OR c.companyName LIKE :query
            ')
            ->setParameter('query', '%' . $query . '%')
            ->orderBy('f.dateFacture', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findNextNumber(): string
    {
        $year = date('Y');
        $prefix = 'FAC-' . $year . '-';
        
        $lastFacture = $this->createQueryBuilder('f')
            ->andWhere('f.number LIKE :prefix')
            ->setParameter('prefix', $prefix . '%')
            ->orderBy('f.number', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$lastFacture) {
            return $prefix . '0001';
        }

        $lastNumber = (int) substr($lastFacture->getNumber(), -4);
        return $prefix . str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
    }
}