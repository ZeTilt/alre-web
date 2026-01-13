<?php

namespace App\Tests\Unit\Command;

use App\Command\UpdateDevisFactureStatusCommand;
use App\Entity\Devis;
use App\Entity\Facture;
use App\Repository\DevisRepository;
use App\Repository\FactureRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Tests unitaires pour UpdateDevisFactureStatusCommand.
 *
 * Couvre:
 * - Mise à jour des statuts de devis expirés
 * - Mise à jour des statuts de factures en retard
 * - Comptage des mises à jour
 */
class UpdateDevisFactureStatusCommandTest extends TestCase
{
    private MockObject&DevisRepository $devisRepository;
    private MockObject&FactureRepository $factureRepository;
    private MockObject&EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->devisRepository = $this->createMock(DevisRepository::class);
        $this->factureRepository = $this->createMock(FactureRepository::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
    }

    private function createCommandTester(): CommandTester
    {
        $command = new UpdateDevisFactureStatusCommand(
            $this->devisRepository,
            $this->factureRepository,
            $this->entityManager
        );

        $application = new Application();
        $application->add($command);

        return new CommandTester($application->find('app:update-status'));
    }

    private function mockQueryBuilder(MockObject $repository, array $results): void
    {
        $queryBuilder = $this->getMockBuilder(QueryBuilder::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['where', 'setParameter', 'getQuery'])
            ->getMock();

        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->method('setParameter')->willReturnSelf();

        // Create a mock that returns results via getResult
        $query = $this->getMockBuilder(Query::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getResult'])
            ->getMock();
        $query->method('getResult')->willReturn($results);

        $queryBuilder->method('getQuery')->willReturn($query);

        $repository->method('createQueryBuilder')->willReturn($queryBuilder);
    }

    // ===== BASIC EXECUTION TESTS =====

    public function testExecuteWithNoDocumentsToUpdate(): void
    {
        $this->mockQueryBuilder($this->devisRepository, []);
        $this->mockQueryBuilder($this->factureRepository, []);

        $this->entityManager->expects($this->once())->method('flush');

        $commandTester = $this->createCommandTester();
        $commandTester->execute([]);

        $this->assertEquals(Command::SUCCESS, $commandTester->getStatusCode());
        $this->assertStringContainsString('0 devis et 0 factures modifiés', $commandTester->getDisplay());
    }

    public function testExecuteUpdatesExpiredDevis(): void
    {
        // Create a devis that will be updated
        $devis = $this->createMock(Devis::class);
        $devis->method('updateStatusBasedOnDeadline')->willReturn(true);

        $this->mockQueryBuilder($this->devisRepository, [$devis]);
        $this->mockQueryBuilder($this->factureRepository, []);

        $this->entityManager->expects($this->once())->method('flush');

        $commandTester = $this->createCommandTester();
        $commandTester->execute([]);

        $this->assertEquals(Command::SUCCESS, $commandTester->getStatusCode());
        $this->assertStringContainsString('1 devis', $commandTester->getDisplay());
    }

    public function testExecuteUpdatesOverdueFactures(): void
    {
        // Create a facture that will be updated
        $facture = $this->createMock(Facture::class);
        $facture->method('updateStatusBasedOnDeadline')->willReturn(true);

        $this->mockQueryBuilder($this->devisRepository, []);
        $this->mockQueryBuilder($this->factureRepository, [$facture]);

        $this->entityManager->expects($this->once())->method('flush');

        $commandTester = $this->createCommandTester();
        $commandTester->execute([]);

        $this->assertEquals(Command::SUCCESS, $commandTester->getStatusCode());
        $this->assertStringContainsString('1 factures modifiés', $commandTester->getDisplay());
    }

    public function testExecuteUpdatesBothDevisAndFactures(): void
    {
        $devis1 = $this->createMock(Devis::class);
        $devis1->method('updateStatusBasedOnDeadline')->willReturn(true);

        $devis2 = $this->createMock(Devis::class);
        $devis2->method('updateStatusBasedOnDeadline')->willReturn(true);

        $facture1 = $this->createMock(Facture::class);
        $facture1->method('updateStatusBasedOnDeadline')->willReturn(true);

        $facture2 = $this->createMock(Facture::class);
        $facture2->method('updateStatusBasedOnDeadline')->willReturn(true);

        $facture3 = $this->createMock(Facture::class);
        $facture3->method('updateStatusBasedOnDeadline')->willReturn(true);

        $this->mockQueryBuilder($this->devisRepository, [$devis1, $devis2]);
        $this->mockQueryBuilder($this->factureRepository, [$facture1, $facture2, $facture3]);

        $commandTester = $this->createCommandTester();
        $commandTester->execute([]);

        $this->assertEquals(Command::SUCCESS, $commandTester->getStatusCode());
        $this->assertStringContainsString('2 devis et 3 factures modifiés', $commandTester->getDisplay());
    }

    public function testExecuteDoesNotCountUnchangedDocuments(): void
    {
        $devisUpdated = $this->createMock(Devis::class);
        $devisUpdated->method('updateStatusBasedOnDeadline')->willReturn(true);

        $devisNotUpdated = $this->createMock(Devis::class);
        $devisNotUpdated->method('updateStatusBasedOnDeadline')->willReturn(false);

        $factureNotUpdated = $this->createMock(Facture::class);
        $factureNotUpdated->method('updateStatusBasedOnDeadline')->willReturn(false);

        $this->mockQueryBuilder($this->devisRepository, [$devisUpdated, $devisNotUpdated]);
        $this->mockQueryBuilder($this->factureRepository, [$factureNotUpdated]);

        $commandTester = $this->createCommandTester();
        $commandTester->execute([]);

        $this->assertEquals(Command::SUCCESS, $commandTester->getStatusCode());
        $this->assertStringContainsString('1 devis et 0 factures modifiés', $commandTester->getDisplay());
    }

    // ===== FLUSH BEHAVIOR TESTS =====

    public function testExecuteFlushesChanges(): void
    {
        $this->mockQueryBuilder($this->devisRepository, []);
        $this->mockQueryBuilder($this->factureRepository, []);

        $this->entityManager->expects($this->once())->method('flush');

        $commandTester = $this->createCommandTester();
        $commandTester->execute([]);

        $this->assertEquals(Command::SUCCESS, $commandTester->getStatusCode());
    }

    // ===== OUTPUT FORMAT TESTS =====

    public function testExecuteDisplaysSuccessMessage(): void
    {
        $this->mockQueryBuilder($this->devisRepository, []);
        $this->mockQueryBuilder($this->factureRepository, []);

        $commandTester = $this->createCommandTester();
        $commandTester->execute([]);

        $display = $commandTester->getDisplay();
        $this->assertStringContainsString('Statuts mis à jour avec succès', $display);
    }
}
