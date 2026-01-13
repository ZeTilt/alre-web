<?php

namespace App\Tests\Unit\Entity;

use App\Entity\SeoKeyword;
use App\Entity\SeoPosition;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour SeoKeyword.
 *
 * Couvre:
 * - getLatestPosition() - récupère la dernière position
 * - addPosition() / removePosition() - gestion de la collection
 * - isActive() - état actif/inactif
 * - __toString()
 */
class SeoKeywordTest extends TestCase
{
    // ===== getLatestPosition() TESTS =====

    public function testGetLatestPositionReturnsNullWhenNoPositions(): void
    {
        $keyword = new SeoKeyword();

        $this->assertNull($keyword->getLatestPosition());
    }

    public function testGetLatestPositionReturnsFirstPosition(): void
    {
        $keyword = new SeoKeyword();

        // Créer des positions (la collection est triée par date DESC)
        $position1 = new SeoPosition();
        $position1->setPosition(10.5);
        $position1->setDate(new \DateTimeImmutable('2026-01-10'));

        $position2 = new SeoPosition();
        $position2->setPosition(8.2);
        $position2->setDate(new \DateTimeImmutable('2026-01-12'));

        // Ajouter dans l'ordre inverse pour tester le tri
        $keyword->addPosition($position1);
        $keyword->addPosition($position2);

        // La collection retourne le premier élément (devrait être le plus récent si trié)
        // Note: Le tri est fait par Doctrine, ici on teste juste que first() retourne quelque chose
        $latest = $keyword->getLatestPosition();
        $this->assertNotNull($latest);
        $this->assertInstanceOf(SeoPosition::class, $latest);
    }

    public function testGetLatestPositionWithSinglePosition(): void
    {
        $keyword = new SeoKeyword();

        $position = new SeoPosition();
        $position->setPosition(5.0);
        $keyword->addPosition($position);

        $this->assertSame($position, $keyword->getLatestPosition());
    }

    // ===== addPosition() TESTS =====

    public function testAddPositionSetsKeywordOnPosition(): void
    {
        $keyword = new SeoKeyword();
        $position = new SeoPosition();

        $keyword->addPosition($position);

        $this->assertSame($keyword, $position->getKeyword());
    }

    public function testAddPositionDoesNotAddDuplicate(): void
    {
        $keyword = new SeoKeyword();
        $position = new SeoPosition();

        $keyword->addPosition($position);
        $keyword->addPosition($position); // Ajout en double

        $this->assertCount(1, $keyword->getPositions());
    }

    public function testAddPositionReturnsSelf(): void
    {
        $keyword = new SeoKeyword();
        $position = new SeoPosition();

        $this->assertSame($keyword, $keyword->addPosition($position));
    }

    // ===== removePosition() TESTS =====

    public function testRemovePositionRemovesFromCollection(): void
    {
        $keyword = new SeoKeyword();
        $position = new SeoPosition();

        $keyword->addPosition($position);
        $this->assertCount(1, $keyword->getPositions());

        $keyword->removePosition($position);
        $this->assertCount(0, $keyword->getPositions());
    }

    public function testRemovePositionSetsKeywordToNull(): void
    {
        $keyword = new SeoKeyword();
        $position = new SeoPosition();

        $keyword->addPosition($position);
        $keyword->removePosition($position);

        $this->assertNull($position->getKeyword());
    }

    public function testRemovePositionReturnsSelf(): void
    {
        $keyword = new SeoKeyword();
        $position = new SeoPosition();

        $keyword->addPosition($position);
        $this->assertSame($keyword, $keyword->removePosition($position));
    }

    public function testRemovePositionDoesNothingIfNotInCollection(): void
    {
        $keyword = new SeoKeyword();
        $position = new SeoPosition();

        // Position jamais ajoutée
        $keyword->removePosition($position);

        $this->assertCount(0, $keyword->getPositions());
    }

    // ===== isActive() TESTS =====

    public function testIsActiveDefaultsToTrue(): void
    {
        $keyword = new SeoKeyword();

        $this->assertTrue($keyword->isActive());
    }

    public function testSetIsActive(): void
    {
        $keyword = new SeoKeyword();

        $keyword->setIsActive(false);
        $this->assertFalse($keyword->isActive());

        $keyword->setIsActive(true);
        $this->assertTrue($keyword->isActive());
    }

    // ===== FLUENT SETTERS TESTS =====

    public function testSettersReturnSelf(): void
    {
        $keyword = new SeoKeyword();

        $this->assertSame($keyword, $keyword->setKeyword('test keyword'));
        $this->assertSame($keyword, $keyword->setTargetUrl('https://example.com'));
        $this->assertSame($keyword, $keyword->setIsActive(true));
        $this->assertSame($keyword, $keyword->setLastSyncAt(new \DateTimeImmutable()));
        $this->assertSame($keyword, $keyword->setCreatedAt(new \DateTimeImmutable()));
    }

    public function testSettersStoreValues(): void
    {
        $keyword = new SeoKeyword();
        $lastSync = new \DateTimeImmutable();

        $keyword->setKeyword('création site web')
                ->setTargetUrl('https://alre-web.fr/services')
                ->setLastSyncAt($lastSync);

        $this->assertEquals('création site web', $keyword->getKeyword());
        $this->assertEquals('https://alre-web.fr/services', $keyword->getTargetUrl());
        $this->assertEquals($lastSync, $keyword->getLastSyncAt());
    }

    // ===== CONSTRUCTOR TESTS =====

    public function testConstructorSetsCreatedAt(): void
    {
        $before = new \DateTimeImmutable();
        $keyword = new SeoKeyword();
        $after = new \DateTimeImmutable();

        $this->assertNotNull($keyword->getCreatedAt());
        $this->assertGreaterThanOrEqual($before, $keyword->getCreatedAt());
        $this->assertLessThanOrEqual($after, $keyword->getCreatedAt());
    }

    public function testConstructorInitializesPositionsCollection(): void
    {
        $keyword = new SeoKeyword();

        $this->assertCount(0, $keyword->getPositions());
    }

    // ===== __toString() TESTS =====

    public function testToStringReturnsKeyword(): void
    {
        $keyword = new SeoKeyword();
        $keyword->setKeyword('développeur symfony vannes');

        $this->assertEquals('développeur symfony vannes', (string) $keyword);
    }

    public function testToStringReturnsDefaultWhenNoKeyword(): void
    {
        $keyword = new SeoKeyword();

        $this->assertEquals('Nouveau mot-clé', (string) $keyword);
    }

    // ===== NULLABLE FIELDS TESTS =====

    public function testTargetUrlCanBeNull(): void
    {
        $keyword = new SeoKeyword();

        $this->assertNull($keyword->getTargetUrl());

        $keyword->setTargetUrl('https://example.com');
        $this->assertEquals('https://example.com', $keyword->getTargetUrl());

        $keyword->setTargetUrl(null);
        $this->assertNull($keyword->getTargetUrl());
    }

    public function testLastSyncAtCanBeNull(): void
    {
        $keyword = new SeoKeyword();

        $this->assertNull($keyword->getLastSyncAt());
    }

    // ===== SOURCE FIELD TESTS =====

    public function testSourceDefaultsToManual(): void
    {
        $keyword = new SeoKeyword();

        $this->assertEquals(SeoKeyword::SOURCE_MANUAL, $keyword->getSource());
    }

    public function testSetSource(): void
    {
        $keyword = new SeoKeyword();

        $keyword->setSource(SeoKeyword::SOURCE_AUTO_GSC);
        $this->assertEquals(SeoKeyword::SOURCE_AUTO_GSC, $keyword->getSource());

        $keyword->setSource(SeoKeyword::SOURCE_MANUAL);
        $this->assertEquals(SeoKeyword::SOURCE_MANUAL, $keyword->getSource());
    }

    public function testSetSourceReturnsSelf(): void
    {
        $keyword = new SeoKeyword();

        $this->assertSame($keyword, $keyword->setSource(SeoKeyword::SOURCE_AUTO_GSC));
    }

    public function testIsManualReturnsTrueForManualSource(): void
    {
        $keyword = new SeoKeyword();
        $keyword->setSource(SeoKeyword::SOURCE_MANUAL);

        $this->assertTrue($keyword->isManual());
        $this->assertFalse($keyword->isAutoImported());
    }

    public function testIsAutoImportedReturnsTrueForAutoGscSource(): void
    {
        $keyword = new SeoKeyword();
        $keyword->setSource(SeoKeyword::SOURCE_AUTO_GSC);

        $this->assertTrue($keyword->isAutoImported());
        $this->assertFalse($keyword->isManual());
    }

    public function testGetSourceChoices(): void
    {
        $choices = SeoKeyword::getSourceChoices();

        $this->assertIsArray($choices);
        $this->assertArrayHasKey('Manuel', $choices);
        $this->assertArrayHasKey('Auto (GSC)', $choices);
        $this->assertEquals(SeoKeyword::SOURCE_MANUAL, $choices['Manuel']);
        $this->assertEquals(SeoKeyword::SOURCE_AUTO_GSC, $choices['Auto (GSC)']);
    }

    public function testGetSourceLabel(): void
    {
        $keyword = new SeoKeyword();

        $keyword->setSource(SeoKeyword::SOURCE_MANUAL);
        $this->assertEquals('Manuel', $keyword->getSourceLabel());

        $keyword->setSource(SeoKeyword::SOURCE_AUTO_GSC);
        $this->assertEquals('Auto (GSC)', $keyword->getSourceLabel());
    }

    // ===== RELEVANCE LEVEL FIELD TESTS =====

    public function testRelevanceLevelDefaultsToMedium(): void
    {
        $keyword = new SeoKeyword();

        $this->assertEquals(SeoKeyword::RELEVANCE_MEDIUM, $keyword->getRelevanceLevel());
    }

    public function testSetRelevanceLevel(): void
    {
        $keyword = new SeoKeyword();

        $keyword->setRelevanceLevel(SeoKeyword::RELEVANCE_HIGH);
        $this->assertEquals(SeoKeyword::RELEVANCE_HIGH, $keyword->getRelevanceLevel());

        $keyword->setRelevanceLevel(SeoKeyword::RELEVANCE_LOW);
        $this->assertEquals(SeoKeyword::RELEVANCE_LOW, $keyword->getRelevanceLevel());
    }

    public function testSetRelevanceLevelReturnsSelf(): void
    {
        $keyword = new SeoKeyword();

        $this->assertSame($keyword, $keyword->setRelevanceLevel(SeoKeyword::RELEVANCE_HIGH));
    }

    public function testGetRelevanceLevelChoices(): void
    {
        $choices = SeoKeyword::getRelevanceLevelChoices();

        $this->assertIsArray($choices);
        $this->assertArrayHasKey('Haute', $choices);
        $this->assertArrayHasKey('Moyenne', $choices);
        $this->assertArrayHasKey('Basse', $choices);
        $this->assertEquals(SeoKeyword::RELEVANCE_HIGH, $choices['Haute']);
        $this->assertEquals(SeoKeyword::RELEVANCE_MEDIUM, $choices['Moyenne']);
        $this->assertEquals(SeoKeyword::RELEVANCE_LOW, $choices['Basse']);
    }

    public function testGetRelevanceLevelLabel(): void
    {
        $keyword = new SeoKeyword();

        $keyword->setRelevanceLevel(SeoKeyword::RELEVANCE_HIGH);
        $this->assertEquals('Haute', $keyword->getRelevanceLevelLabel());

        $keyword->setRelevanceLevel(SeoKeyword::RELEVANCE_MEDIUM);
        $this->assertEquals('Moyenne', $keyword->getRelevanceLevelLabel());

        $keyword->setRelevanceLevel(SeoKeyword::RELEVANCE_LOW);
        $this->assertEquals('Basse', $keyword->getRelevanceLevelLabel());
    }

    // ===== LAST SEEN IN GSC FIELD TESTS =====

    public function testLastSeenInGscDefaultsToNull(): void
    {
        $keyword = new SeoKeyword();

        $this->assertNull($keyword->getLastSeenInGsc());
    }

    public function testSetLastSeenInGsc(): void
    {
        $keyword = new SeoKeyword();
        $now = new \DateTimeImmutable();

        $keyword->setLastSeenInGsc($now);
        $this->assertEquals($now, $keyword->getLastSeenInGsc());

        $keyword->setLastSeenInGsc(null);
        $this->assertNull($keyword->getLastSeenInGsc());
    }

    public function testSetLastSeenInGscReturnsSelf(): void
    {
        $keyword = new SeoKeyword();

        $this->assertSame($keyword, $keyword->setLastSeenInGsc(new \DateTimeImmutable()));
    }
}
