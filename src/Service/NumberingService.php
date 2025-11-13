<?php

namespace App\Service;

use App\Entity\Devis;
use App\Entity\Facture;
use Doctrine\ORM\EntityManagerInterface;

class NumberingService
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function generateDevisNumber(): string
    {
        $currentDate = new \DateTimeImmutable();
        $year = $currentDate->format('Y');
        $month = $currentDate->format('m');
        
        // Get the last devis number for this year-month
        $lastDevis = $this->entityManager->getRepository(Devis::class)
            ->createQueryBuilder('d')
            ->where('d.number LIKE :pattern')
            ->setParameter('pattern', "DEV-{$year}-{$month}-%")
            ->orderBy('d.number', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
        
        if ($lastDevis) {
            // Extract the last number from the format DEV-YYYY-MM-XX
            $lastNumber = (int) substr($lastDevis->getNumber(), -2);
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }
        
        return sprintf('DEV-%s-%s-%02d', $year, $month, $nextNumber);
    }

    public function generateFactureNumber(Devis $devis = null): string
    {
        // If devis is provided, replace DEV with FAC in the devis number
        if ($devis) {
            return str_replace('DEV-', 'FAC-', $devis->getNumber());
        }

        // Otherwise, generate a new facture number
        $currentDate = new \DateTimeImmutable();
        $year = $currentDate->format('Y');
        $month = $currentDate->format('m');

        // Get the last facture number for this year-month
        $lastFacture = $this->entityManager->getRepository(Facture::class)
            ->createQueryBuilder('f')
            ->where('f.number LIKE :pattern')
            ->setParameter('pattern', "FAC-{$year}-{$month}-%")
            ->orderBy('f.number', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if ($lastFacture) {
            // Extract the last number from the format FAC-YYYY-MM-XX
            $lastNumber = (int) substr($lastFacture->getNumber(), -2);
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }

        return sprintf('FAC-%s-%s-%02d', $year, $month, $nextNumber);
    }
}