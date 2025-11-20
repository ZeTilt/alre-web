<?php

namespace App\Repository;

use App\Entity\Expense;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Expense>
 */
class ExpenseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Expense::class);
    }

    // ====================================================
    // Méthodes pour le Dashboard
    // ====================================================

    /**
     * Retourne le total des dépenses sur une période donnée
     */
    public function getTotalExpensesByPeriod(\DateTimeImmutable $startDate, \DateTimeImmutable $endDate): float
    {
        $result = $this->createQueryBuilder('e')
            ->select('COALESCE(SUM(e.amount), 0)')
            ->andWhere('e.dateExpense BETWEEN :startDate AND :endDate')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->getQuery()
            ->getSingleScalarResult();

        return (float) $result;
    }

    /**
     * Retourne les dépenses par catégorie pour une période donnée
     */
    public function getExpensesByCategory(\DateTimeImmutable $startDate, \DateTimeImmutable $endDate): array
    {
        $result = $this->createQueryBuilder('e')
            ->select('e.category', 'SUM(e.amount) as total', 'COUNT(e.id) as count')
            ->andWhere('e.dateExpense BETWEEN :startDate AND :endDate')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->groupBy('e.category')
            ->orderBy('total', 'DESC')
            ->getQuery()
            ->getResult();

        $stats = [];
        foreach ($result as $row) {
            $stats[$row['category']] = [
                'total' => (float) ($row['total'] ?: 0),
                'count' => (int) $row['count']
            ];
        }

        return $stats;
    }

    /**
     * Retourne les dépenses mensuelles pour une année (pour graphiques)
     */
    public function getMonthlyExpensesForYear(int $year): array
    {
        $startDate = new \DateTimeImmutable($year . '-01-01');
        $endDate = new \DateTimeImmutable($year . '-12-31');

        $conn = $this->getEntityManager()->getConnection();
        $sql = "
            SELECT MONTH(date_expense) as month, SUM(amount) as total
            FROM expense
            WHERE date_expense BETWEEN :startDate AND :endDate
            GROUP BY MONTH(date_expense)
            ORDER BY MONTH(date_expense)
        ";

        $result = $conn->executeQuery($sql, [
            'startDate' => $startDate->format('Y-m-d'),
            'endDate' => $endDate->format('Y-m-d')
        ])->fetchAllAssociative();

        // Fill missing months with 0
        $monthlyExpenses = [];
        for ($i = 1; $i <= 12; $i++) {
            $monthlyExpenses[$i] = 0.0;
        }

        foreach ($result as $row) {
            $monthlyExpenses[(int) $row['month']] = (float) ($row['total'] ?: 0);
        }

        return $monthlyExpenses;
    }

    /**
     * Retourne les dépenses par mois pour une année donnée
     */
    public function getExpensesByMonth(int $year): array
    {
        return $this->getMonthlyExpensesForYear($year);
    }

}
