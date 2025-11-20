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

        $conn = $this->getEntityManager()->getConnection();
        $sql = "
            SELECT MONTH(date_facture) as month, SUM(total_ttc) as total
            FROM facture
            WHERE date_facture BETWEEN :startDate AND :endDate
            AND status = :status
            GROUP BY MONTH(date_facture)
            ORDER BY MONTH(date_facture)
        ";

        $result = $conn->executeQuery($sql, [
            'startDate' => $startDate->format('Y-m-d'),
            'endDate' => $endDate->format('Y-m-d'),
            'status' => Facture::STATUS_PAYE
        ])->fetchAllAssociative();

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

    // ====================================================
    // Méthodes pour le Dashboard
    // ====================================================

    /**
     * Retourne le CA encaissé (basé sur datePaiement) sur une période donnée
     */
    public function getRevenueByPeriod(\DateTimeImmutable $startDate, \DateTimeImmutable $endDate, bool $paid = true): float
    {
        $qb = $this->createQueryBuilder('f')
            ->select('COALESCE(SUM(f.totalTtc), 0)');

        if ($paid) {
            // CA encaissé = factures avec date de paiement renseignée
            $qb->andWhere('f.datePaiement BETWEEN :startDate AND :endDate')
               ->andWhere('f.status = :status')
               ->setParameter('status', Facture::STATUS_PAYE);
        } else {
            // CA facturé = factures émises sur la période
            $qb->andWhere('f.dateFacture BETWEEN :startDate AND :endDate');
        }

        $qb->setParameter('startDate', $startDate)
           ->setParameter('endDate', $endDate);

        return (float) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Retourne le CA par client pour une année donnée
     */
    public function getRevenueByClient(int $year): array
    {
        $startDate = new \DateTimeImmutable($year . '-01-01');
        $endDate = new \DateTimeImmutable($year . '-12-31');

        return $this->createQueryBuilder('f')
            ->select('c.name as clientName', 'c.id as clientId', 'SUM(f.totalTtc) as total', 'COUNT(f.id) as count')
            ->leftJoin('f.client', 'c')
            ->andWhere('f.dateFacture BETWEEN :startDate AND :endDate')
            ->andWhere('f.status = :status')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->setParameter('status', Facture::STATUS_PAYE)
            ->groupBy('c.id', 'c.name')
            ->orderBy('total', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Retourne le délai moyen de paiement en jours
     */
    public function getAveragePaymentDelay(): int
    {
        $factures = $this->createQueryBuilder('f')
            ->andWhere('f.status = :status')
            ->andWhere('f.datePaiement IS NOT NULL')
            ->andWhere('f.dateFacture IS NOT NULL')
            ->setParameter('status', Facture::STATUS_PAYE)
            ->getQuery()
            ->getResult();

        if (empty($factures)) {
            return 0;
        }

        $totalDelay = 0;
        foreach ($factures as $facture) {
            $dateFacture = $facture->getDateFacture();
            $datePaiement = $facture->getDatePaiement();
            if ($dateFacture && $datePaiement) {
                $totalDelay += $dateFacture->diff($datePaiement)->days;
            }
        }

        return (int) round($totalDelay / count($factures));
    }

    /**
     * Retourne le total des factures en attente de paiement (pending revenue)
     */
    public function getPendingRevenue(): float
    {
        $result = $this->createQueryBuilder('f')
            ->select('COALESCE(SUM(f.totalTtc), 0)')
            ->andWhere('f.status IN (:statuses)')
            ->setParameter('statuses', [
                Facture::STATUS_A_ENVOYER,
                Facture::STATUS_ENVOYE,
                Facture::STATUS_RELANCE,
                Facture::STATUS_EN_RETARD
            ])
            ->getQuery()
            ->getSingleScalarResult();

        return (float) $result;
    }

    /**
     * Retourne les factures dont l'échéance arrive dans les X prochains jours
     */
    public function getUpcomingPayments(int $days = 30): array
    {
        $today = new \DateTimeImmutable();
        $futureDate = $today->modify("+{$days} days");

        return $this->createQueryBuilder('f')
            ->andWhere('f.dateEcheance BETWEEN :today AND :futureDate')
            ->andWhere('f.status NOT IN (:paidStatuses)')
            ->setParameter('today', $today)
            ->setParameter('futureDate', $futureDate)
            ->setParameter('paidStatuses', [Facture::STATUS_PAYE, Facture::STATUS_ANNULE])
            ->orderBy('f.dateEcheance', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Retourne le CA par mois avec date d'encaissement (pour graphiques dashboard)
     */
    public function getMonthlyPaidRevenueForYear(int $year): array
    {
        $startDate = new \DateTimeImmutable($year . '-01-01');
        $endDate = new \DateTimeImmutable($year . '-12-31');

        $conn = $this->getEntityManager()->getConnection();
        $sql = "
            SELECT MONTH(date_paiement) as month, SUM(total_ttc) as total
            FROM facture
            WHERE date_paiement BETWEEN :startDate AND :endDate
            AND status = :status
            GROUP BY MONTH(date_paiement)
            ORDER BY MONTH(date_paiement)
        ";

        $result = $conn->executeQuery($sql, [
            'startDate' => $startDate->format('Y-m-d'),
            'endDate' => $endDate->format('Y-m-d'),
            'status' => Facture::STATUS_PAYE
        ])->fetchAllAssociative();

        // Fill missing months with 0
        $monthlyRevenue = [];
        for ($i = 1; $i <= 12; $i++) {
            $monthlyRevenue[$i] = 0.0;
        }

        foreach ($result as $row) {
            $monthlyRevenue[(int) $row['month']] = (float) ($row['total'] ?: 0);
        }

        return $monthlyRevenue;
    }
}