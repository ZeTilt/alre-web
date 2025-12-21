<?php

namespace App\Tests\Unit\Service;

use App\Entity\Devis;
use App\Entity\Facture;
use App\Repository\DevisRepository;
use App\Repository\FactureRepository;
use App\Service\NumberingService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\TestCase;

class NumberingServiceTest extends TestCase
{
    private EntityManagerInterface $entityManager;
    private NumberingService $service;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->service = new NumberingService($this->entityManager);
    }

    private function mockDevisRepository(?Devis $lastDevis = null): void
    {
        $query = $this->getMockBuilder(Query::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getOneOrNullResult'])
            ->getMock();
        $query->method('getOneOrNullResult')->willReturn($lastDevis);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('where')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('orderBy')->willReturnSelf();
        $qb->method('setMaxResults')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $repo = $this->createMock(DevisRepository::class);
        $repo->method('createQueryBuilder')->willReturn($qb);

        $this->entityManager->method('getRepository')
            ->with(Devis::class)
            ->willReturn($repo);
    }

    private function mockFactureRepository(?Facture $lastFacture = null): void
    {
        $query = $this->getMockBuilder(Query::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getOneOrNullResult'])
            ->getMock();
        $query->method('getOneOrNullResult')->willReturn($lastFacture);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('where')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('orderBy')->willReturnSelf();
        $qb->method('setMaxResults')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $repo = $this->createMock(FactureRepository::class);
        $repo->method('createQueryBuilder')->willReturn($qb);

        $this->entityManager->method('getRepository')
            ->with(Facture::class)
            ->willReturn($repo);
    }

    public function testGenerateFirstDevisNumberOfMonth(): void
    {
        $this->mockDevisRepository(null);

        $number = $this->service->generateDevisNumber();

        $currentDate = new \DateTimeImmutable();
        $expectedPrefix = 'AW-DEV-' . $currentDate->format('Y') . '-' . $currentDate->format('m') . '-';

        $this->assertStringStartsWith($expectedPrefix, $number);
        $this->assertStringEndsWith('-01', $number);
    }

    public function testGenerateSequentialDevisNumber(): void
    {
        $lastDevis = new Devis();
        $lastDevis->setTitle('Last');
        $reflection = new \ReflectionClass($lastDevis);
        $numberProperty = $reflection->getProperty('number');
        $numberProperty->setValue($lastDevis, 'AW-DEV-2025-12-05');

        $this->mockDevisRepository($lastDevis);

        $number = $this->service->generateDevisNumber();

        $currentDate = new \DateTimeImmutable();
        $expectedNumber = sprintf('AW-DEV-%s-%s-06', $currentDate->format('Y'), $currentDate->format('m'));

        $this->assertEquals($expectedNumber, $number);
    }

    public function testGenerateDevisNumberFormat(): void
    {
        $this->mockDevisRepository(null);

        $number = $this->service->generateDevisNumber();

        // Format should be AW-DEV-YYYY-MM-XX
        $this->assertMatchesRegularExpression('/^AW-DEV-\d{4}-\d{2}-\d{2}$/', $number);
    }

    public function testGenerateFactureNumber(): void
    {
        $this->mockFactureRepository(null);

        $number = $this->service->generateFactureNumber();

        $currentDate = new \DateTimeImmutable();
        $expectedPrefix = 'AW-FAC-' . $currentDate->format('Y') . '-' . $currentDate->format('m') . '-';

        $this->assertStringStartsWith($expectedPrefix, $number);
        $this->assertStringEndsWith('-01', $number);
    }

    public function testGenerateSequentialFactureNumber(): void
    {
        $lastFacture = new Facture();
        $lastFacture->setTitle('Last');
        $reflection = new \ReflectionClass($lastFacture);
        $numberProperty = $reflection->getProperty('number');
        $numberProperty->setValue($lastFacture, 'AW-FAC-2025-12-10');

        $this->mockFactureRepository($lastFacture);

        $number = $this->service->generateFactureNumber();

        $currentDate = new \DateTimeImmutable();
        $expectedNumber = sprintf('AW-FAC-%s-%s-11', $currentDate->format('Y'), $currentDate->format('m'));

        $this->assertEquals($expectedNumber, $number);
    }

    public function testGenerateFactureNumberFormat(): void
    {
        $this->mockFactureRepository(null);

        $number = $this->service->generateFactureNumber();

        // Format should be AW-FAC-YYYY-MM-XX
        $this->assertMatchesRegularExpression('/^AW-FAC-\d{4}-\d{2}-\d{2}$/', $number);
    }

    public function testNumberingUsesCurrentDate(): void
    {
        $this->mockDevisRepository(null);

        $number = $this->service->generateDevisNumber();
        $currentDate = new \DateTimeImmutable();

        $this->assertStringContainsString($currentDate->format('Y'), $number);
        $this->assertStringContainsString($currentDate->format('m'), $number);
    }
}
