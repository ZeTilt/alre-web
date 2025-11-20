<?php

namespace App\Command;

use App\Service\ExpenseGenerationService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:generate-recurring-expenses',
    description: 'Génère automatiquement les dépenses récurrentes (mensuelles et annuelles)',
)]
class GenerateRecurringExpensesCommand extends Command
{
    public function __construct(
        private ExpenseGenerationService $expenseGenerationService
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Génération des dépenses récurrentes');

        $stats = $this->expenseGenerationService->generateRecurringExpenses();

        $io->info(sprintf('Trouvé %d template(s) de dépense(s) récurrente(s)', $stats['templates']));
        $io->newLine();
        $io->success(sprintf('%d dépense(s) générée(s), %d ignorée(s)', $stats['generated'], $stats['skipped']));

        return Command::SUCCESS;
    }
}
