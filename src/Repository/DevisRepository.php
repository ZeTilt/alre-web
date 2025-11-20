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

    // ====================================================
    // Méthodes pour le Dashboard
    // ====================================================

    /**
     * Calcule le taux de conversion des devis (acceptés / envoyés)
     * @return float Taux de conversion en pourcentage
     */
    public function getConversionRate(\DateTimeImmutable $startDate, \DateTimeImmutable $endDate): float
    {
        // Devis envoyés (qui ont été soumis au client)
        $sent = $this->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->andWhere('d.dateCreation BETWEEN :startDate AND :endDate')
            ->andWhere('d.status IN (:sentStatuses)')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->setParameter('sentStatuses', [
                Devis::STATUS_ENVOYE,
                Devis::STATUS_RELANCE,
                Devis::STATUS_ACCEPTE,
                Devis::STATUS_REFUSE
            ])
            ->getQuery()
            ->getSingleScalarResult();

        if ($sent == 0) {
            return 0.0;
        }

        // Devis acceptés
        $accepted = $this->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->andWhere('d.dateCreation BETWEEN :startDate AND :endDate')
            ->andWhere('d.status = :status')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->setParameter('status', Devis::STATUS_ACCEPTE)
            ->getQuery()
            ->getSingleScalarResult();

        return round(($accepted / $sent) * 100, 2);
    }

    /**
     * Retourne les statistiques par statut pour une année
     */
    public function getQuotesByStatus(int $year): array
    {
        $startDate = new \DateTimeImmutable($year . '-01-01');
        $endDate = new \DateTimeImmutable($year . '-12-31');

        $result = $this->createQueryBuilder('d')
            ->select('d.status', 'COUNT(d.id) as count', 'SUM(d.totalTtc) as total')
            ->andWhere('d.dateCreation BETWEEN :startDate AND :endDate')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->groupBy('d.status')
            ->getQuery()
            ->getResult();

        $stats = [];
        foreach ($result as $row) {
            $stats[$row['status']] = [
                'count' => (int) $row['count'],
                'total' => (float) ($row['total'] ?: 0)
            ];
        }

        return $stats;
    }

    /**
     * Retourne le montant total des devis en attente
     */
    public function getPendingQuotesTotal(): float
    {
        $result = $this->createQueryBuilder('d')
            ->select('COALESCE(SUM(d.totalTtc), 0)')
            ->andWhere('d.status IN (:statuses)')
            ->setParameter('statuses', [
                Devis::STATUS_A_ENVOYER,
                Devis::STATUS_ENVOYE,
                Devis::STATUS_RELANCE
            ])
            ->getQuery()
            ->getSingleScalarResult();

        return (float) $result;
    }

    /**
     * Retourne les devis à relancer (envoyés il y a plus de X jours)
     */
    public function getQuotesToFollowUp(int $daysOld = 7): array
    {
        $dateLimit = (new \DateTimeImmutable())->modify("-{$daysOld} days");

        return $this->createQueryBuilder('d')
            ->andWhere('d.dateEnvoi < :dateLimit')
            ->andWhere('d.status IN (:statuses)')
            ->setParameter('dateLimit', $dateLimit)
            ->setParameter('statuses', [Devis::STATUS_ENVOYE, Devis::STATUS_RELANCE])
            ->orderBy('d.dateEnvoi', 'ASC')
            ->getQuery()
            ->getResult();
    }
}