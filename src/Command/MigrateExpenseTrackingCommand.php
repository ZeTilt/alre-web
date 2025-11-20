<?php

namespace App\Command;

use App\Entity\Expense;
use App\Entity\ExpenseGeneration;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:migrate-expense-tracking',
    description: 'Migre les dépenses existantes vers le système de suivi (à exécuter une seule fois)',
)]
class MigrateExpenseTrackingCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Migration des dépenses existantes vers le système de suivi');

        // Récupérer toutes les dépenses récurrentes (templates)
        $recurringExpenses = $this->entityManager->getRepository(Expense::class)
            ->createQueryBuilder('e')
            ->where('e.recurrence != :ponctuelle')
            ->setParameter('ponctuelle', Expense::RECURRENCE_PONCTUELLE)
            ->getQuery()
            ->getResult();

        $io->info(sprintf('Trouvé %d template(s) de dépense(s) récurrente(s)', count($recurringExpenses)));

        $migratedCount = 0;

        foreach ($recurringExpenses as $template) {
            $io->section(sprintf('Template: %s (%s)', $template->getTitle(), $template->getRecurrenceLabel()));

            // Trouver toutes les dépenses ponctuelles qui correspondent à ce template
            $matchingExpenses = $this->entityManager->getRepository(Expense::class)
                ->createQueryBuilder('e')
                ->where('e.title = :title')
                ->andWhere('e.amount = :amount')
                ->andWhere('e.recurrence = :ponctuelle')
                ->setParameter('title', $template->getTitle())
                ->setParameter('amount', $template->getAmount())
                ->setParameter('ponctuelle', Expense::RECURRENCE_PONCTUELLE)
                ->orderBy('e.dateExpense', 'ASC')
                ->getQuery()
                ->getResult();

            $io->text(sprintf('Trouvé %d dépense(s) ponctuelle(s) correspondante(s)', count($matchingExpenses)));

            foreach ($matchingExpenses as $expense) {
                // Vérifier si un enregistrement de suivi existe déjà
                $existing = $this->entityManager->getRepository(ExpenseGeneration::class)
                    ->createQueryBuilder('eg')
                    ->where('eg.templateExpense = :template')
                    ->andWhere('eg.generatedForDate = :date')
                    ->setParameter('template', $template)
                    ->setParameter('date', $expense->getDateExpense())
                    ->getQuery()
                    ->getOneOrNullResult();

                if ($existing) {
                    $io->text(sprintf('  - %s : enregistrement de suivi existe déjà',
                        $expense->getDateExpense()->format('d/m/Y')
                    ));
                    continue;
                }

                // Créer l'enregistrement de suivi
                $generation = new ExpenseGeneration();
                $generation->setTemplateExpense($template);
                $generation->setGeneratedForDate($expense->getDateExpense());
                $generation->setGeneratedExpense($expense);

                $this->entityManager->persist($generation);
                $migratedCount++;

                $io->text(sprintf('  ✓ %s : enregistrement de suivi créé',
                    $expense->getDateExpense()->format('d/m/Y')
                ));
            }
        }

        $this->entityManager->flush();

        $io->newLine();
        $io->success(sprintf('%d enregistrement(s) de suivi créé(s)', $migratedCount));
        $io->note('Cette commande ne doit être exécutée qu\'une seule fois. Les prochaines générations utiliseront automatiquement le système de suivi.');

        return Command::SUCCESS;
    }
}
