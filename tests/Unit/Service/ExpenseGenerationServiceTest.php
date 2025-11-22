<?php

namespace App\Tests\Unit\Service;

use App\Entity\Expense;
use App\Entity\ExpenseGeneration;
use App\Service\ExpenseGenerationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ExpenseGenerationServiceTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private ExpenseGenerationService $service;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $this->service = static::getContainer()->get(ExpenseGenerationService::class);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->entityManager->close();
    }

    public function testGenerateMonthlyExpensesFromPast(): void
    {
        // GIVEN: On se place au 20 novembre 2025, avec un template créé il y a 10 mois
        $fixedNow = new \DateTimeImmutable('2025-11-20');
        $startDate = new \DateTimeImmutable('2025-01-18'); // Il y a ~10 mois

        $template = new Expense();
        $template->setTitle('Test Abonnement Mensuel');
        $template->setAmount('108.00'); // 108€
        $template->setCategory(Expense::CATEGORY_SUBSCRIPTION);
        $template->setRecurrence(Expense::RECURRENCE_MENSUELLE);
        $template->setStartDate($startDate);
        $template->setIsActive(true);
        $template->setDateExpense($startDate);

        $this->entityManager->persist($template);
        $this->entityManager->flush();

        // WHEN: Génération avec le temps figé
        $stats = $this->service->generateRecurringExpenses($fixedNow);

        // THEN: Doit générer 11 occurrences (janvier à novembre 2025)
        $this->assertEquals(11, $stats['generated'], 'Devrait générer 11 mois de dépenses');
        $this->assertEquals(0, $stats['skipped'], 'Aucune dépense à ignorer');
        $this->assertEquals(1, $stats['templates'], 'Un template traité');

        // Vérifier que les dépenses ont bien été créées
        $generatedExpenses = $this->entityManager->getRepository(Expense::class)
            ->findBy(['title' => 'Test Abonnement Mensuel', 'recurrence' => Expense::RECURRENCE_PONCTUELLE]);

        $this->assertCount(11, $generatedExpenses, 'Doit avoir créé 11 dépenses ponctuelles');
    }

    public function testGenerateAnnualExpensesFromPast(): void
    {
        // GIVEN: Template annuel créé il y a 3 ans
        $fixedNow = new \DateTimeImmutable('2025-11-20');
        $startDate = new \DateTimeImmutable('2022-06-01'); // Il y a 3 ans et demi

        $template = new Expense();
        $template->setTitle('Test Abonnement Annuel');
        $template->setAmount('1200.00'); // 1200€
        $template->setCategory(Expense::CATEGORY_SUBSCRIPTION);
        $template->setRecurrence(Expense::RECURRENCE_ANNUELLE);
        $template->setStartDate($startDate);
        $template->setIsActive(true);
        $template->setDateExpense($startDate);

        $this->entityManager->persist($template);
        $this->entityManager->flush();

        // WHEN: Génération
        $stats = $this->service->generateRecurringExpenses($fixedNow);

        // THEN: Doit générer 4 occurrences (2022, 2023, 2024, 2025)
        $this->assertEquals(4, $stats['generated'], 'Devrait générer 4 années de dépenses');

        $generatedExpenses = $this->entityManager->getRepository(Expense::class)
            ->findBy(['title' => 'Test Abonnement Annuel', 'recurrence' => Expense::RECURRENCE_PONCTUELLE]);

        $this->assertCount(4, $generatedExpenses);
    }

    public function testDoesNotRegenerateExistingExpenses(): void
    {
        // GIVEN: Template avec dépenses déjà générées
        $fixedNow = new \DateTimeImmutable('2025-11-20');
        $startDate = new \DateTimeImmutable('2025-09-01');

        $template = new Expense();
        $template->setTitle('Test No Regeneration');
        $template->setAmount('50.00');
        $template->setCategory(Expense::CATEGORY_SUBSCRIPTION);
        $template->setRecurrence(Expense::RECURRENCE_MENSUELLE);
        $template->setStartDate($startDate);
        $template->setIsActive(true);
        $template->setDateExpense($startDate);

        $this->entityManager->persist($template);
        $this->entityManager->flush();

        // Première génération
        $stats1 = $this->service->generateRecurringExpenses($fixedNow);
        $this->assertGreaterThan(0, $stats1['generated'], 'La première exécution doit générer des dépenses');

        // Compter les dépenses générées pour ce template spécifique
        $expensesAfterFirst = $this->entityManager->getRepository(Expense::class)
            ->findBy(['title' => 'Test No Regeneration', 'recurrence' => Expense::RECURRENCE_PONCTUELLE]);
        $countAfterFirst = count($expensesAfterFirst);

        // WHEN: Seconde génération (sans avancer le temps)
        $stats2 = $this->service->generateRecurringExpenses($fixedNow);

        // THEN: Aucune nouvelle dépense générée pour ce template
        $expensesAfterSecond = $this->entityManager->getRepository(Expense::class)
            ->findBy(['title' => 'Test No Regeneration', 'recurrence' => Expense::RECURRENCE_PONCTUELLE]);

        $this->assertCount($countAfterFirst, $expensesAfterSecond, 'Aucune nouvelle dépense ne doit être créée');
    }

    public function testGenerationWithEndDate(): void
    {
        // GIVEN: Template avec date de fin
        $fixedNow = new \DateTimeImmutable('2025-11-20');
        $startDate = new \DateTimeImmutable('2025-01-01');
        $endDate = new \DateTimeImmutable('2025-03-31'); // Seulement 3 mois

        $template = new Expense();
        $template->setTitle('Test With End Date');
        $template->setAmount('50.00');
        $template->setCategory(Expense::CATEGORY_SUBSCRIPTION);
        $template->setRecurrence(Expense::RECURRENCE_MENSUELLE);
        $template->setStartDate($startDate);
        $template->setEndDate($endDate);
        $template->setIsActive(true);
        $template->setDateExpense($startDate);

        $this->entityManager->persist($template);
        $this->entityManager->flush();

        // WHEN: Génération
        $stats = $this->service->generateRecurringExpenses($fixedNow);

        // THEN: Seulement 3 mois générés (janvier, février, mars)
        $this->assertEquals(3, $stats['generated'], 'Seulement 3 mois entre start et end');

        // Template devrait être désactivé (endDate dépassée)
        $this->assertFalse($template->isActive(), 'Template devrait être désactivé car endDate dépassée');
    }

    public function testDoesNotGenerateInactiveTemplates(): void
    {
        // GIVEN: Template inactif
        $fixedNow = new \DateTimeImmutable('2025-11-20');
        $startDate = new \DateTimeImmutable('2025-01-01');

        $template = new Expense();
        $template->setTitle('Test Inactive');
        $template->setAmount('50.00');
        $template->setCategory(Expense::CATEGORY_SUBSCRIPTION);
        $template->setRecurrence(Expense::RECURRENCE_MENSUELLE);
        $template->setStartDate($startDate);
        $template->setIsActive(false); // INACTIF
        $template->setDateExpense($startDate);

        $this->entityManager->persist($template);
        $this->entityManager->flush();

        // WHEN: Génération
        $this->service->generateRecurringExpenses($fixedNow);

        // THEN: Aucune dépense générée pour ce template inactif
        $generatedExpenses = $this->entityManager->getRepository(Expense::class)
            ->findBy(['title' => 'Test Inactive', 'recurrence' => Expense::RECURRENCE_PONCTUELLE]);

        $this->assertCount(0, $generatedExpenses, 'Ne doit pas générer de dépenses pour template inactif');
    }

    public function testTrackingPreventsRegenerationAfterDeletion(): void
    {
        // GIVEN: Template avec une occurrence générée puis supprimée
        $fixedNow = new \DateTimeImmutable('2025-11-20');
        $startDate = new \DateTimeImmutable('2025-10-01');

        $template = new Expense();
        $template->setTitle('Test Deletion Tracking');
        $template->setAmount('50.00');
        $template->setCategory(Expense::CATEGORY_SUBSCRIPTION);
        $template->setRecurrence(Expense::RECURRENCE_MENSUELLE);
        $template->setStartDate($startDate);
        $template->setIsActive(true);
        $template->setDateExpense($startDate);

        $this->entityManager->persist($template);
        $this->entityManager->flush();

        // Première génération
        $this->service->generateRecurringExpenses($fixedNow);

        // Supprimer une dépense générée
        $generatedExpense = $this->entityManager->getRepository(Expense::class)
            ->findOneBy(['title' => 'Test Deletion Tracking', 'recurrence' => Expense::RECURRENCE_PONCTUELLE]);

        $this->assertNotNull($generatedExpense, 'Une dépense devrait avoir été générée');

        $this->entityManager->remove($generatedExpense);
        $this->entityManager->flush();

        // WHEN: Nouvelle génération
        $stats2 = $this->service->generateRecurringExpenses($fixedNow);

        // THEN: Ne doit pas régénérer (le tracking ExpenseGeneration empêche)
        $this->assertEquals(0, $stats2['generated'], 'Ne doit pas régénérer une dépense supprimée');

        // Vérifier que le tracking existe toujours
        $trackingExists = $this->entityManager->getRepository(ExpenseGeneration::class)
            ->findOneBy(['templateExpense' => $template, 'generatedForDate' => $startDate]);

        $this->assertNotNull($trackingExists, 'Le tracking doit exister même après suppression');
    }

    public function testDoesNotGenerateFutureDates(): void
    {
        // GIVEN: Template qui commence dans le futur
        $fixedNow = new \DateTimeImmutable('2025-11-20');
        $futureStart = new \DateTimeImmutable('2026-01-01'); // Dans le futur

        $template = new Expense();
        $template->setTitle('Test Future Start');
        $template->setAmount('50.00');
        $template->setCategory(Expense::CATEGORY_SUBSCRIPTION);
        $template->setRecurrence(Expense::RECURRENCE_MENSUELLE);
        $template->setStartDate($futureStart);
        $template->setIsActive(true);
        $template->setDateExpense($futureStart);

        $this->entityManager->persist($template);
        $this->entityManager->flush();

        // WHEN: Génération
        $this->service->generateRecurringExpenses($fixedNow);

        // THEN: Rien généré car la date de début est dans le futur
        $generatedExpenses = $this->entityManager->getRepository(Expense::class)
            ->findBy(['title' => 'Test Future Start', 'recurrence' => Expense::RECURRENCE_PONCTUELLE]);

        $this->assertCount(0, $generatedExpenses, 'Ne doit pas générer pour dates futures');
    }
}
