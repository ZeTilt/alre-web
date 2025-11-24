<?php

namespace App\Service;

use App\Entity\Expense;
use App\Entity\ExpenseGeneration;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class ExpenseGenerationService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Génère automatiquement toutes les dépenses récurrentes manquantes jusqu'à maintenant
     *
     * @param \DateTimeImmutable|null $now Date de référence (null = maintenant, injectable pour tests)
     */
    public function generateRecurringExpenses(?\DateTimeImmutable $now = null): array
    {
        $now = $now ?? new \DateTimeImmutable();
        $stats = [
            'generated' => 0,
            'skipped' => 0,
        ];

        // Récupérer toutes les dépenses récurrentes actives
        $recurringExpenses = $this->entityManager->getRepository(Expense::class)
            ->createQueryBuilder('e')
            ->where('e.recurrence != :ponctuelle')
            ->andWhere('e.isActive = :active')
            ->setParameter('ponctuelle', Expense::RECURRENCE_PONCTUELLE)
            ->setParameter('active', true)
            ->getQuery()
            ->getResult();

        $stats['templates'] = count($recurringExpenses);

        foreach ($recurringExpenses as $template) {
            $startDate = $template->getStartDate() ?: $template->getDateExpense();
            $datesToGenerate = [];

            if ($template->getRecurrence() === Expense::RECURRENCE_MENSUELLE) {
                // Générer toutes les occurrences mensuelles depuis la date de début jusqu'à maintenant
                $currentDate = $startDate;
                while ($currentDate <= $now) {
                    if ($template->getEndDate() && $currentDate > $template->getEndDate()) {
                        break;
                    }
                    $datesToGenerate[] = $currentDate;
                    $currentDate = $currentDate->modify('+1 month');
                }
            } elseif ($template->getRecurrence() === Expense::RECURRENCE_ANNUELLE) {
                // Générer toutes les occurrences annuelles depuis la date de début jusqu'à maintenant
                $currentDate = $startDate;
                while ($currentDate <= $now) {
                    if ($template->getEndDate() && $currentDate > $template->getEndDate()) {
                        break;
                    }
                    $datesToGenerate[] = $currentDate;
                    $currentDate = $currentDate->modify('+1 year');
                }
            }

            // Générer chaque occurrence
            foreach ($datesToGenerate as $targetDate) {
                // Vérifier si cette occurrence a déjà été générée
                $hasBeenGenerated = $this->entityManager->getRepository(ExpenseGeneration::class)
                    ->hasBeenGenerated($template->getId(), $targetDate);

                if ($hasBeenGenerated) {
                    $stats['skipped']++;
                    continue;
                }

                // Créer la nouvelle dépense
                $newExpense = new Expense();
                $newExpense->setTitle($template->getTitle());
                $newExpense->setDescription($template->getDescription());
                $newExpense->setAmount($template->getAmount());
                $newExpense->setCategory($template->getCategory());
                $newExpense->setDateExpense($targetDate);
                $newExpense->setRecurrence(Expense::RECURRENCE_PONCTUELLE);
                $newExpense->setIsActive(true);

                $this->entityManager->persist($newExpense);

                // Créer l'enregistrement de suivi
                $generation = new ExpenseGeneration();
                $generation->setTemplateExpense($template);
                $generation->setGeneratedForDate($targetDate);
                $generation->setGeneratedExpense($newExpense);

                $this->entityManager->persist($generation);
                $stats['generated']++;

                $this->logger->info(sprintf('Dépense "%s" générée pour le %s',
                    $newExpense->getTitle(),
                    $targetDate->format('d/m/Y')
                ));
            }

            // Désactiver le template si la date de fin est dépassée
            if ($template->getEndDate() && $template->getEndDate() < $now && $template->isActive()) {
                $template->setIsActive(false);
                $this->logger->info(sprintf('Dépense récurrente "%s" désactivée (date de fin dépassée)', $template->getTitle()));
            }
        }

        if ($stats['generated'] > 0 || $this->entityManager->getUnitOfWork()->size() > 0) {
            $this->entityManager->flush();
        }

        return $stats;
    }
}
