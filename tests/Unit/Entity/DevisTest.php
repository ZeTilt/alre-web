<?php

namespace App\Tests\Unit\Entity;

use App\Entity\Client;
use App\Entity\Devis;
use App\Entity\DevisItem;
use App\Entity\Facture;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour l'entité Devis.
 *
 * Couvre:
 * - calculateTotals() - calcul des totaux depuis les items
 * - getVatAmount() - montant de la TVA
 * - isExpired() - vérification expiration
 * - canBeAccepted() / canBeSent() / canBeConverted() - workflow status
 * - syncAcompteValues() - synchronisation acompte/pourcentage
 * - updateStatusBasedOnDeadline() - mise à jour automatique du statut
 * - setStatus() - auto-set des dates selon le statut
 */
class DevisTest extends TestCase
{
    // ===== CONSTRUCTION TESTS =====

    public function testDefaultValues(): void
    {
        $devis = new Devis();

        $this->assertEquals(Devis::STATUS_BROUILLON, $devis->getStatus());
        $this->assertEquals('0.00', $devis->getTotalHt());
        $this->assertEquals('20.00', $devis->getVatRate());
        $this->assertEquals('0.00', $devis->getTotalTtc());
        $this->assertTrue($devis->isAcompteVerse());
        $this->assertNotNull($devis->getCreatedAt());
        $this->assertNotNull($devis->getDateCreation());
        $this->assertNotNull($devis->getDateValidite());
    }

    public function testDateValiditeIsThirtyDaysInFuture(): void
    {
        $devis = new Devis();
        $now = new \DateTimeImmutable();

        // Date validité should be approximately 30 days from now
        $diff = $devis->getDateValidite()->diff($now)->days;
        $this->assertLessThanOrEqual(31, $diff);
        $this->assertGreaterThanOrEqual(29, $diff);
    }

    // ===== FLUENT SETTERS TESTS =====

    public function testFluentSetters(): void
    {
        $client = new Client();
        $user = new User();
        $devis = new Devis();

        $result = $devis->setTitle('Test Devis')
            ->setDescription('Description')
            ->setClient($client)
            ->setCreatedBy($user)
            ->setNumber('DEV-2026-0001')
            ->setConditions('Conditions générales')
            ->setNotes('Notes internes');

        $this->assertSame($devis, $result);
        $this->assertEquals('Test Devis', $devis->getTitle());
        $this->assertEquals('Description', $devis->getDescription());
        $this->assertSame($client, $devis->getClient());
        $this->assertSame($user, $devis->getCreatedBy());
        $this->assertEquals('DEV-2026-0001', $devis->getNumber());
    }

    // ===== CALCULATE TOTALS TESTS =====

    public function testSetTotalHtCalculatesTotalTtc(): void
    {
        $devis = new Devis();
        $devis->setVatRate('20.00');

        $devis->setTotalHt('1000.00');

        $this->assertEquals('1200.00', $devis->getTotalTtc());
    }

    public function testSetVatRateRecalculatesTotalTtc(): void
    {
        $devis = new Devis();
        $devis->setTotalHt('1000.00');

        $devis->setVatRate('10.00');

        $this->assertEquals('1100.00', $devis->getTotalTtc());
    }

    public function testCalculateTotalsFromItems(): void
    {
        $devis = new Devis();
        $devis->setVatRate('20.00');

        $item1 = $this->createMock(DevisItem::class);
        $item1->method('getTotalAfterDiscount')->willReturn(500.0);

        $item2 = $this->createMock(DevisItem::class);
        $item2->method('getTotalAfterDiscount')->willReturn(300.0);

        // Use reflection to add items without triggering Doctrine's bidirectional setter
        $reflection = new \ReflectionClass($devis);
        $itemsProperty = $reflection->getProperty('items');
        $itemsProperty->setAccessible(true);
        $items = $itemsProperty->getValue($devis);
        $items->add($item1);
        $items->add($item2);

        $devis->calculateTotals();

        $this->assertEquals('800.00', $devis->getTotalHt());
        $this->assertEquals('960.00', $devis->getTotalTtc());
    }

    public function testCalculateTotalsWithNoItems(): void
    {
        $devis = new Devis();
        $devis->setVatRate('20.00');

        $devis->calculateTotals();

        $this->assertEquals('0.00', $devis->getTotalHt());
        $this->assertEquals('0.00', $devis->getTotalTtc());
    }

    public function testGetVatAmount(): void
    {
        $devis = new Devis();
        $devis->setTotalHt('1000.00');
        $devis->setVatRate('20.00');

        $this->assertEquals('200.00', $devis->getVatAmount());
    }

    public function testGetVatAmountWithZeroVat(): void
    {
        $devis = new Devis();
        $devis->setTotalHt('1000.00');
        $devis->setVatRate('0.00');

        $this->assertEquals('0.00', $devis->getVatAmount());
    }

    // ===== IS EXPIRED TESTS =====

    public function testIsExpiredReturnsFalseWithoutDateValidite(): void
    {
        $devis = new Devis();
        $devis->setDateValidite(null);

        $this->assertFalse($devis->isExpired());
    }

    public function testIsExpiredReturnsTrueWhenPastAndNotFinalStatus(): void
    {
        $devis = new Devis();
        $devis->setDateValidite(new \DateTimeImmutable('-1 day'));
        $devis->setStatus(Devis::STATUS_ENVOYE);

        $this->assertTrue($devis->isExpired());
    }

    public function testIsExpiredReturnsFalseWhenPastButAccepted(): void
    {
        $devis = new Devis();
        $devis->setDateValidite(new \DateTimeImmutable('-1 day'));
        $devis->setStatus(Devis::STATUS_ACCEPTE);

        $this->assertFalse($devis->isExpired());
    }

    public function testIsExpiredReturnsFalseWhenPastButRefused(): void
    {
        $devis = new Devis();
        $devis->setDateValidite(new \DateTimeImmutable('-1 day'));
        $devis->setStatus(Devis::STATUS_REFUSE);

        $this->assertFalse($devis->isExpired());
    }

    public function testIsExpiredReturnsFalseWhenPastButAnnule(): void
    {
        $devis = new Devis();
        $devis->setDateValidite(new \DateTimeImmutable('-1 day'));
        $devis->setStatus(Devis::STATUS_ANNULE);

        $this->assertFalse($devis->isExpired());
    }

    public function testIsExpiredReturnsFalseWhenFuture(): void
    {
        $devis = new Devis();
        $devis->setDateValidite(new \DateTimeImmutable('+1 day'));
        $devis->setStatus(Devis::STATUS_ENVOYE);

        $this->assertFalse($devis->isExpired());
    }

    // ===== CAN BE ACCEPTED TESTS =====

    public function testCanBeAcceptedReturnsTrueWhenEnvoyeAndNotExpired(): void
    {
        $devis = new Devis();
        $devis->setStatus(Devis::STATUS_ENVOYE);
        $devis->setDateValidite(new \DateTimeImmutable('+30 days'));

        $this->assertTrue($devis->canBeAccepted());
    }

    public function testCanBeAcceptedReturnsTrueWhenRelanceAndNotExpired(): void
    {
        $devis = new Devis();
        $devis->setStatus(Devis::STATUS_RELANCE);
        $devis->setDateValidite(new \DateTimeImmutable('+30 days'));

        $this->assertTrue($devis->canBeAccepted());
    }

    public function testCanBeAcceptedReturnsFalseWhenBrouillon(): void
    {
        $devis = new Devis();
        $devis->setStatus(Devis::STATUS_BROUILLON);

        $this->assertFalse($devis->canBeAccepted());
    }

    public function testCanBeAcceptedReturnsFalseWhenExpired(): void
    {
        $devis = new Devis();
        $devis->setStatus(Devis::STATUS_ENVOYE);
        $devis->setDateValidite(new \DateTimeImmutable('-1 day'));

        $this->assertFalse($devis->canBeAccepted());
    }

    // ===== CAN BE SENT TESTS =====

    public function testCanBeSentReturnsTrueWhenBrouillon(): void
    {
        $devis = new Devis();
        $devis->setStatus(Devis::STATUS_BROUILLON);

        $this->assertTrue($devis->canBeSent());
    }

    public function testCanBeSentReturnsTrueWhenAEnvoyer(): void
    {
        $devis = new Devis();
        $devis->setStatus(Devis::STATUS_A_ENVOYER);

        $this->assertTrue($devis->canBeSent());
    }

    public function testCanBeSentReturnsFalseWhenEnvoye(): void
    {
        $devis = new Devis();
        $devis->setStatus(Devis::STATUS_ENVOYE);

        $this->assertFalse($devis->canBeSent());
    }

    // ===== CAN BE CONVERTED TESTS =====

    public function testCanBeConvertedReturnsTrueWhenAccepteWithoutFacture(): void
    {
        $devis = new Devis();
        $devis->setStatus(Devis::STATUS_ACCEPTE);

        $this->assertTrue($devis->canBeConverted());
    }

    public function testCanBeConvertedReturnsFalseWhenNotAccepte(): void
    {
        $devis = new Devis();
        $devis->setStatus(Devis::STATUS_ENVOYE);

        $this->assertFalse($devis->canBeConverted());
    }

    public function testCanBeConvertedReturnsFalseWhenHasFactureStandard(): void
    {
        $devis = new Devis();
        $devis->setStatus(Devis::STATUS_ACCEPTE);

        $facture = new Facture();
        $facture->setType(Facture::TYPE_STANDARD);
        $devis->addFacture($facture);

        $this->assertFalse($devis->canBeConverted());
    }

    // ===== CAN GENERATE FACTURE ACOMPTE TESTS =====

    public function testCanGenerateFactureAcompteReturnsTrue(): void
    {
        $devis = new Devis();
        $devis->setStatus(Devis::STATUS_ACCEPTE);
        $devis->setAcompte('500.00');

        $this->assertTrue($devis->canGenerateFactureAcompte());
    }

    public function testCanGenerateFactureAcompteReturnsFalseWithoutAcompte(): void
    {
        $devis = new Devis();
        $devis->setStatus(Devis::STATUS_ACCEPTE);

        $this->assertFalse($devis->canGenerateFactureAcompte());
    }

    public function testCanGenerateFactureAcompteReturnsFalseWhenAlreadyHasAcompte(): void
    {
        $devis = new Devis();
        $devis->setStatus(Devis::STATUS_ACCEPTE);
        $devis->setAcompte('500.00');

        $factureAcompte = new Facture();
        $factureAcompte->setType(Facture::TYPE_ACOMPTE);
        $devis->addFacture($factureAcompte);

        $this->assertFalse($devis->canGenerateFactureAcompte());
    }

    // ===== CAN GENERATE FACTURE SOLDE TESTS =====

    public function testCanGenerateFactureSoldeReturnsTrue(): void
    {
        $devis = new Devis();
        $devis->setStatus(Devis::STATUS_ACCEPTE);

        $factureAcompte = new Facture();
        $factureAcompte->setType(Facture::TYPE_ACOMPTE);
        $factureAcompte->setStatus(Facture::STATUS_PAYE);
        $devis->addFacture($factureAcompte);

        $this->assertTrue($devis->canGenerateFactureSolde());
    }

    public function testCanGenerateFactureSoldeReturnsFalseWhenNoAcompte(): void
    {
        $devis = new Devis();
        $devis->setStatus(Devis::STATUS_ACCEPTE);

        $this->assertFalse($devis->canGenerateFactureSolde());
    }

    public function testCanGenerateFactureSoldeReturnsFalseWhenAcompteNotPaid(): void
    {
        $devis = new Devis();
        $devis->setStatus(Devis::STATUS_ACCEPTE);

        $factureAcompte = new Facture();
        $factureAcompte->setType(Facture::TYPE_ACOMPTE);
        $factureAcompte->setStatus(Facture::STATUS_ENVOYE);
        $devis->addFacture($factureAcompte);

        $this->assertFalse($devis->canGenerateFactureSolde());
    }

    public function testCanGenerateFactureSoldeReturnsFalseWhenAlreadyHasSolde(): void
    {
        $devis = new Devis();
        $devis->setStatus(Devis::STATUS_ACCEPTE);

        $factureAcompte = new Facture();
        $factureAcompte->setType(Facture::TYPE_ACOMPTE);
        $factureAcompte->setStatus(Facture::STATUS_PAYE);
        $devis->addFacture($factureAcompte);

        $factureSolde = new Facture();
        $factureSolde->setType(Facture::TYPE_SOLDE);
        $devis->addFacture($factureSolde);

        $this->assertFalse($devis->canGenerateFactureSolde());
    }

    // ===== SYNC ACOMPTE VALUES TESTS =====

    public function testSyncAcompteValuesCalculatesPercentageFromMontant(): void
    {
        $devis = new Devis();
        $devis->setTotalHt('1000.00');
        $devis->setVatRate('20.00'); // totalTtc = 1200.00
        $devis->setAcompte('360.00'); // 30% de 1200

        $devis->syncAcompteValues();

        $this->assertEquals('30', $devis->getAcomptePercentage());
    }

    public function testSyncAcompteValuesCalculatesMontantFromPercentage(): void
    {
        $devis = new Devis();
        $devis->setTotalHt('1000.00');
        $devis->setVatRate('20.00'); // totalTtc = 1200.00
        $devis->setAcomptePercentage('30');

        $devis->syncAcompteValues();

        $this->assertEquals('360.00', $devis->getAcompte());
    }

    public function testSyncAcompteValuesDoesNothingWhenTotalTtcZero(): void
    {
        $devis = new Devis();
        $devis->setAcompte('100.00');

        $devis->syncAcompteValues();

        // Should not calculate percentage when totalTtc is 0
        $this->assertNull($devis->getAcomptePercentage());
    }

    public function testSyncAcompteValuesPrioritizesPercentageWhenBothSet(): void
    {
        $devis = new Devis();
        $devis->setTotalHt('1000.00');
        $devis->setVatRate('20.00'); // totalTtc = 1200.00
        $devis->setAcompte('500.00'); // Not matching 30%
        $devis->setAcomptePercentage('30');

        $devis->syncAcompteValues();

        // Should recalculate montant from percentage
        $this->assertEquals('360.00', $devis->getAcompte());
    }

    // ===== SET STATUS AUTO-DATE TESTS =====

    public function testSetStatusEnvoyeSetsDateEnvoi(): void
    {
        $devis = new Devis();
        $this->assertNull($devis->getDateEnvoi());

        $devis->setStatus(Devis::STATUS_ENVOYE);

        $this->assertNotNull($devis->getDateEnvoi());
        $this->assertInstanceOf(\DateTimeImmutable::class, $devis->getDateEnvoi());
    }

    public function testSetStatusEnvoyeDoesNotOverwriteDateEnvoi(): void
    {
        $devis = new Devis();
        $existingDate = new \DateTimeImmutable('-5 days');
        $devis->setDateEnvoi($existingDate);

        $devis->setStatus(Devis::STATUS_ENVOYE);

        $this->assertSame($existingDate, $devis->getDateEnvoi());
    }

    public function testSetStatusAccepteSetsDateReponse(): void
    {
        $devis = new Devis();
        $this->assertNull($devis->getDateReponse());

        $devis->setStatus(Devis::STATUS_ACCEPTE);

        $this->assertNotNull($devis->getDateReponse());
    }

    public function testSetStatusRefuseSetsDateReponse(): void
    {
        $devis = new Devis();
        $this->assertNull($devis->getDateReponse());

        $devis->setStatus(Devis::STATUS_REFUSE);

        $this->assertNotNull($devis->getDateReponse());
    }

    // ===== UPDATE STATUS BASED ON DEADLINE TESTS =====

    public function testUpdateStatusBasedOnDeadlineChangesToARelancer(): void
    {
        $devis = new Devis();
        $devis->setStatus(Devis::STATUS_ENVOYE);
        $devis->setDateValidite(new \DateTimeImmutable('-1 day'));

        $changed = $devis->updateStatusBasedOnDeadline();

        $this->assertTrue($changed);
        $this->assertEquals(Devis::STATUS_A_RELANCER, $devis->getStatus());
    }

    public function testUpdateStatusBasedOnDeadlineDoesNothingIfNotPast(): void
    {
        $devis = new Devis();
        $devis->setStatus(Devis::STATUS_ENVOYE);
        $devis->setDateValidite(new \DateTimeImmutable('+1 day'));

        $changed = $devis->updateStatusBasedOnDeadline();

        $this->assertFalse($changed);
        $this->assertEquals(Devis::STATUS_ENVOYE, $devis->getStatus());
    }

    public function testUpdateStatusBasedOnDeadlineDoesNothingIfAccepted(): void
    {
        $devis = new Devis();
        $devis->setStatus(Devis::STATUS_ACCEPTE);
        $devis->setDateValidite(new \DateTimeImmutable('-1 day'));

        $changed = $devis->updateStatusBasedOnDeadline();

        $this->assertFalse($changed);
        $this->assertEquals(Devis::STATUS_ACCEPTE, $devis->getStatus());
    }

    public function testUpdateStatusBasedOnDeadlineDoesNothingIfExpire(): void
    {
        $devis = new Devis();
        $devis->setStatus(Devis::STATUS_EXPIRE);
        $devis->setDateValidite(new \DateTimeImmutable('-1 day'));

        $changed = $devis->updateStatusBasedOnDeadline();

        $this->assertFalse($changed);
    }

    // ===== STATUS LABEL TESTS =====

    public function testGetStatusLabelReturnsCorrectLabel(): void
    {
        $devis = new Devis();

        $devis->setStatus(Devis::STATUS_BROUILLON);
        $this->assertEquals('Brouillon', $devis->getStatusLabel());

        $devis->setStatus(Devis::STATUS_ACCEPTE);
        $this->assertEquals('Accepté', $devis->getStatusLabel());

        $devis->setStatus(Devis::STATUS_A_RELANCER);
        $this->assertEquals('À relancer', $devis->getStatusLabel());
    }

    // ===== COLLECTION TESTS =====

    public function testAddItemSetsDevisOnItem(): void
    {
        $devis = new Devis();
        $item = new DevisItem();

        $devis->addItem($item);

        $this->assertTrue($devis->getItems()->contains($item));
        $this->assertSame($devis, $item->getDevis());
    }

    public function testRemoveItemRemovesDevisFromItem(): void
    {
        $devis = new Devis();
        $item = new DevisItem();
        $devis->addItem($item);

        $devis->removeItem($item);

        $this->assertFalse($devis->getItems()->contains($item));
        $this->assertNull($item->getDevis());
    }

    public function testAddFactureSetsDevisOnFacture(): void
    {
        $devis = new Devis();
        $facture = new Facture();

        $devis->addFacture($facture);

        $this->assertTrue($devis->getFactures()->contains($facture));
        $this->assertSame($devis, $facture->getDevis());
    }

    // ===== FACTURE TYPE HELPERS TESTS =====

    public function testGetFactureAcompteReturnsCorrectFacture(): void
    {
        $devis = new Devis();

        $factureStandard = new Facture();
        $factureStandard->setType(Facture::TYPE_STANDARD);
        $devis->addFacture($factureStandard);

        $factureAcompte = new Facture();
        $factureAcompte->setType(Facture::TYPE_ACOMPTE);
        $devis->addFacture($factureAcompte);

        $this->assertSame($factureAcompte, $devis->getFactureAcompte());
    }

    public function testGetFactureSoldeReturnsCorrectFacture(): void
    {
        $devis = new Devis();

        $factureSolde = new Facture();
        $factureSolde->setType(Facture::TYPE_SOLDE);
        $devis->addFacture($factureSolde);

        $this->assertSame($factureSolde, $devis->getFactureSolde());
    }

    public function testGetFactureReturnsStandardFacture(): void
    {
        $devis = new Devis();

        $factureStandard = new Facture();
        $factureStandard->setType(Facture::TYPE_STANDARD);
        $devis->addFacture($factureStandard);

        $this->assertSame($factureStandard, $devis->getFacture());
    }

    public function testHasFactureStandardReturnsTrue(): void
    {
        $devis = new Devis();

        $facture = new Facture();
        $facture->setType(Facture::TYPE_STANDARD);
        $devis->addFacture($facture);

        $this->assertTrue($devis->hasFactureStandard());
    }

    public function testHasFactureStandardReturnsFalse(): void
    {
        $devis = new Devis();
        $this->assertFalse($devis->hasFactureStandard());
    }

    // ===== TO STRING TESTS =====

    public function testToStringReturnsNumberAndTitle(): void
    {
        $devis = new Devis();
        $devis->setNumber('DEV-2026-0001');
        $devis->setTitle('Site Web Client');

        $this->assertEquals('DEV-2026-0001 - Site Web Client', (string) $devis);
    }

    // ===== STATIC METHODS TESTS =====

    public function testGetStatusChoicesReturnsAllStatuses(): void
    {
        $choices = Devis::getStatusChoices();

        $this->assertIsArray($choices);
        $this->assertArrayHasKey('Brouillon', $choices);
        $this->assertArrayHasKey('Accepté', $choices);
        $this->assertArrayHasKey('Refusé', $choices);
        $this->assertArrayHasKey('Expiré', $choices);
        $this->assertEquals(Devis::STATUS_BROUILLON, $choices['Brouillon']);
    }
}
