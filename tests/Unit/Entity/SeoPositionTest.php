<?php

namespace App\Tests\Unit\Entity;

use App\Entity\SeoKeyword;
use App\Entity\SeoPosition;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour SeoPosition.
 *
 * Couvre:
 * - getCtr() - calcule le Click-Through Rate
 * - Relation avec SeoKeyword
 * - __toString()
 */
class SeoPositionTest extends TestCase
{
    // ===== getCtr() TESTS =====

    public function testGetCtrCalculatesCorrectPercentage(): void
    {
        $position = new SeoPosition();
        $position->setClicks(50);
        $position->setImpressions(1000);

        // CTR = (50 / 1000) * 100 = 5%
        $this->assertEquals(5.0, $position->getCtr());
    }

    public function testGetCtrReturnsZeroWhenNoImpressions(): void
    {
        $position = new SeoPosition();
        $position->setClicks(10);
        $position->setImpressions(0);

        // Évite division par zéro
        $this->assertEquals(0.0, $position->getCtr());
    }

    public function testGetCtrReturnsZeroWhenNoClicks(): void
    {
        $position = new SeoPosition();
        $position->setClicks(0);
        $position->setImpressions(500);

        $this->assertEquals(0.0, $position->getCtr());
    }

    public function testGetCtrRoundsToTwoDecimals(): void
    {
        $position = new SeoPosition();
        $position->setClicks(33);
        $position->setImpressions(1000);

        // CTR = (33 / 1000) * 100 = 3.3%
        $this->assertEquals(3.3, $position->getCtr());
    }

    public function testGetCtrWithHighCtr(): void
    {
        $position = new SeoPosition();
        $position->setClicks(250);
        $position->setImpressions(1000);

        // CTR = 25%
        $this->assertEquals(25.0, $position->getCtr());
    }

    public function testGetCtrWithLowCtr(): void
    {
        $position = new SeoPosition();
        $position->setClicks(1);
        $position->setImpressions(10000);

        // CTR = 0.01%
        $this->assertEquals(0.01, $position->getCtr());
    }

    public function testGetCtrWithPreciseCalculation(): void
    {
        $position = new SeoPosition();
        $position->setClicks(7);
        $position->setImpressions(300);

        // CTR = (7 / 300) * 100 = 2.333... arrondi à 2.33
        $this->assertEquals(2.33, $position->getCtr());
    }

    // ===== RELATION TESTS =====

    public function testSetKeyword(): void
    {
        $keyword = new SeoKeyword();
        $keyword->setKeyword('test');

        $position = new SeoPosition();
        $position->setKeyword($keyword);

        $this->assertSame($keyword, $position->getKeyword());
    }

    public function testSetKeywordToNull(): void
    {
        $keyword = new SeoKeyword();
        $position = new SeoPosition();

        $position->setKeyword($keyword);
        $position->setKeyword(null);

        $this->assertNull($position->getKeyword());
    }

    // ===== DEFAULT VALUES TESTS =====

    public function testClicksDefaultsToZero(): void
    {
        $position = new SeoPosition();

        $this->assertEquals(0, $position->getClicks());
    }

    public function testImpressionsDefaultsToZero(): void
    {
        $position = new SeoPosition();

        $this->assertEquals(0, $position->getImpressions());
    }

    // ===== FLUENT SETTERS TESTS =====

    public function testSettersReturnSelf(): void
    {
        $position = new SeoPosition();

        $this->assertSame($position, $position->setKeyword(new SeoKeyword()));
        $this->assertSame($position, $position->setPosition(5.5));
        $this->assertSame($position, $position->setClicks(100));
        $this->assertSame($position, $position->setImpressions(1000));
        $this->assertSame($position, $position->setDate(new \DateTimeImmutable()));
        $this->assertSame($position, $position->setCreatedAt(new \DateTimeImmutable()));
    }

    public function testSettersStoreValues(): void
    {
        $position = new SeoPosition();
        $date = new \DateTimeImmutable('2026-01-12');

        $position->setPosition(3.7)
                 ->setClicks(42)
                 ->setImpressions(850)
                 ->setDate($date);

        $this->assertEquals(3.7, $position->getPosition());
        $this->assertEquals(42, $position->getClicks());
        $this->assertEquals(850, $position->getImpressions());
        $this->assertEquals($date, $position->getDate());
    }

    // ===== CONSTRUCTOR TESTS =====

    public function testConstructorSetsCreatedAt(): void
    {
        $before = new \DateTimeImmutable();
        $position = new SeoPosition();
        $after = new \DateTimeImmutable();

        $this->assertNotNull($position->getCreatedAt());
        $this->assertGreaterThanOrEqual($before, $position->getCreatedAt());
        $this->assertLessThanOrEqual($after, $position->getCreatedAt());
    }

    // ===== __toString() TESTS =====

    public function testToStringWithKeywordAndDate(): void
    {
        $keyword = new SeoKeyword();
        $keyword->setKeyword('création site web');

        $position = new SeoPosition();
        $position->setKeyword($keyword)
                 ->setPosition(5.5)
                 ->setDate(new \DateTimeImmutable('2026-01-12'));

        $this->assertEquals('création site web - Position 5.5 (12/01/2026)', (string) $position);
    }

    public function testToStringWithoutKeyword(): void
    {
        $position = new SeoPosition();
        $position->setPosition(10.0)
                 ->setDate(new \DateTimeImmutable('2026-01-12'));

        $this->assertEquals('? - Position 10.0 (12/01/2026)', (string) $position);
    }

    public function testToStringWithoutDate(): void
    {
        $keyword = new SeoKeyword();
        $keyword->setKeyword('test');

        $position = new SeoPosition();
        $position->setKeyword($keyword)
                 ->setPosition(7.3);

        $this->assertEquals('test - Position 7.3 (?)', (string) $position);
    }

    public function testToStringWithoutPositionValue(): void
    {
        $position = new SeoPosition();

        // Position par défaut sera 0
        $this->assertStringContainsString('Position 0.0', (string) $position);
    }

    // ===== POSITION VALUE TESTS =====

    public function testPositionCanBeDecimal(): void
    {
        $position = new SeoPosition();
        $position->setPosition(4.7);

        $this->assertEquals(4.7, $position->getPosition());
    }

    public function testPositionCanBeInteger(): void
    {
        $position = new SeoPosition();
        $position->setPosition(1.0);

        $this->assertEquals(1.0, $position->getPosition());
    }

    public function testPositionCanBeLargeNumber(): void
    {
        $position = new SeoPosition();
        $position->setPosition(150.5); // Position au-delà de la page 15

        $this->assertEquals(150.5, $position->getPosition());
    }
}
