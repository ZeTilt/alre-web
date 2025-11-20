<?php

namespace App\EventSubscriber;

use App\Entity\Expense;
use App\Service\ExpenseGenerationService;
use EasyCorp\Bundle\EasyAdminBundle\Event\AfterEntityPersistedEvent;
use EasyCorp\Bundle\EasyAdminBundle\Event\AfterEntityUpdatedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ExpenseSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private ExpenseGenerationService $expenseGenerationService
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            AfterEntityPersistedEvent::class => ['onExpensePersisted'],
            AfterEntityUpdatedEvent::class => ['onExpenseUpdated'],
        ];
    }

    public function onExpensePersisted(AfterEntityPersistedEvent $event): void
    {
        $entity = $event->getEntityInstance();

        if (!($entity instanceof Expense)) {
            return;
        }

        // Si c'est une dépense récurrente, générer automatiquement les occurrences
        if ($entity->isRecurring() && $entity->isActive()) {
            $this->expenseGenerationService->generateRecurringExpenses();
        }
    }

    public function onExpenseUpdated(AfterEntityUpdatedEvent $event): void
    {
        $entity = $event->getEntityInstance();

        if (!($entity instanceof Expense)) {
            return;
        }

        // Si c'est une dépense récurrente, générer automatiquement les occurrences manquantes
        if ($entity->isRecurring() && $entity->isActive()) {
            $this->expenseGenerationService->generateRecurringExpenses();
        }
    }
}
