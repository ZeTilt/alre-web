<?php

namespace App\Repository;

use App\Entity\ExpenseGeneration;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ExpenseGeneration>
 */
class ExpenseGenerationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ExpenseGeneration::class);
    }

    /**
     * Vérifie si une génération existe déjà pour un template et une date
     */
    public function hasBeenGenerated(int $templateId, \DateTimeImmutable $date): bool
    {
        $result = $this->createQueryBuilder('eg')
            ->select('COUNT(eg.id)')
            ->where('eg.templateExpense = :template')
            ->andWhere('eg.generatedForDate = :date')
            ->setParameter('template', $templateId)
            ->setParameter('date', $date)
            ->getQuery()
            ->getSingleScalarResult();

        return $result > 0;
    }
}
