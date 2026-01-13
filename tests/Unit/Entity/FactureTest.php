<?php

namespace App\Tests\Unit\Entity;

use App\Entity\Client;
use App\Entity\Devis;
use App\Entity\DevisItem;
use App\Entity\Facture;
use App\Entity\FactureItem;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour l'entité Facture.
 *
 * Couvre:
 * - calculateTotals() - calcul des totaux depuis les items
 * - getVatAmount() - montant de la TVA
 * - isOverdue() / getDaysOverdue() - retard de paiement
 * - canBePaid() / canBeSent() - workflow status
 * - createFromDevis() - création depuis un devis
 * - isAcompte() / isSolde() / isStandard() - helpers de type
 * - updateStatusBasedOnDeadline() - mise à jour automatique du statut
 */
class FactureTest extends TestCase
{
    // ===== CONSTRUCTION TESTS =====

    public function testDefaultValues(): void
    {
        $facture = new Facture();

        $this->assertEquals(Facture::STATUS_BROUILLON, $facture->getStatus());
        $this->assertEquals(Facture::TYPE_STANDARD, $facture->getType());
        $this->assertEquals('0.00', $facture->getTotalHt());
        $this->assertEquals('20.00', $facture->getVatRate());
        $this->assertEquals('0.00', $facture->getTotalTtc());
        $this->assertTrue($facture->isAcomptePaye());
        $this->assertNotNull($facture->getCreatedAt());
        $this->assertNotNull($facture->getDateFacture());
        $this->assertNotNull($facture->getDateEcheance());
        $this->assertNotNull($facture->getNumber());
    }

    public function testDateEcheanceIsThirtyDaysInFuture(): void
    {
        $facture = new Facture();
        $now = new \DateTimeImmutable();

        $diff = $facture->getDateEcheance()->diff($now)->days;
        $this->assertLessThanOrEqual(31, $diff);
        $this->assertGreaterThanOrEqual(29, $diff);
    }

    public function testGeneratedNumberStartsWithFAC(): void
    {
        $facture = new Facture();

        $this->assertStringStartsWith('FAC-', $facture->getNumber());
    }

    // ===== FLUENT SETTERS TESTS =====

    public function testFluentSetters(): void
    {
        $client = new Client();
        $user = new User();
        $facture = new Facture();

        $result = $facture->setTitle('Facture Test')
            ->setDescription('Description')
            ->setClient($client)
            ->setCreatedBy($user)
            ->setNumber('FAC-2026-0001')
            ->setConditions('Conditions générales')
            ->setNotes('Notes internes')
            ->setModePaiement('virement');

        $this->assertSame($facture, $result);
        $this->assertEquals('Facture Test', $facture->getTitle());
        $this->assertEquals('Description', $facture->getDescription());
        $this->assertSame($client, $facture->getClient());
        $this->assertEquals('virement', $facture->getModePaiement());
    }

    // ===== CALCULATE TOTALS TESTS =====

    public function testSetTotalHtCalculatesTotalTtc(): void
    {
        $facture = new Facture();
        $facture->setVatRate('20.00');

        $facture->setTotalHt('1000.00');

        $this->assertEquals('1200.00', $facture->getTotalTtc());
    }

    public function testSetVatRateRecalculatesTotalTtc(): void
    {
        $facture = new Facture();
        $facture->setTotalHt('1000.00');

        $facture->setVatRate('10.00');

        $this->assertEquals('1100.00', $facture->getTotalTtc());
    }

    public function testCalculateTotalsFromItems(): void
    {
        $facture = new Facture();
        $facture->setVatRate('20.00');

        $item1 = $this->createMock(FactureItem::class);
        $item1->method('getTotalAfterDiscount')->willReturn(500.0);

        $item2 = $this->createMock(FactureItem::class);
        $item2->method('getTotalAfterDiscount')->willReturn(300.0);

        $reflection = new \ReflectionClass($facture);
        $itemsProperty = $reflection->getProperty('items');
        $itemsProperty->setAccessible(true);
        $items = $itemsProperty->getValue($facture);
        $items->add($item1);
        $items->add($item2);

        $facture->calculateTotals();

        $this->assertEquals('800.00', $facture->getTotalHt());
        $this->assertEquals('960.00', $facture->getTotalTtc());
    }

    public function testGetVatAmount(): void
    {
        $facture = new Facture();
        $facture->setTotalHt('1000.00');
        $facture->setVatRate('20.00');

        $this->assertEquals('200.00', $facture->getVatAmount());
    }

    // ===== IS OVERDUE TESTS =====

    public function testIsOverdueReturnsFalseWhenPaid(): void
    {
        $facture = new Facture();
        $facture->setStatus(Facture::STATUS_PAYE);
        $facture->setDateEcheance(new \DateTimeImmutable('-1 day'));

        $this->assertFalse($facture->isOverdue());
    }

    public function testIsOverdueReturnsTrueWhenPastDue(): void
    {
        $facture = new Facture();
        $facture->setStatus(Facture::STATUS_ENVOYE);
        $facture->setDateEcheance(new \DateTimeImmutable('-1 day'));

        $this->assertTrue($facture->isOverdue());
    }

    public function testIsOverdueReturnsFalseWhenNotPastDue(): void
    {
        $facture = new Facture();
        $facture->setStatus(Facture::STATUS_ENVOYE);
        $facture->setDateEcheance(new \DateTimeImmutable('+1 day'));

        $this->assertFalse($facture->isOverdue());
    }

    // ===== GET DAYS OVERDUE TESTS =====

    public function testGetDaysOverdueReturnsZeroWhenNotOverdue(): void
    {
        $facture = new Facture();
        $facture->setStatus(Facture::STATUS_ENVOYE);
        $facture->setDateEcheance(new \DateTimeImmutable('+10 days'));

        $this->assertEquals(0, $facture->getDaysOverdue());
    }

    public function testGetDaysOverdueReturnsCorrectDays(): void
    {
        $facture = new Facture();
        $facture->setStatus(Facture::STATUS_ENVOYE);
        $facture->setDateEcheance(new \DateTimeImmutable('-5 days'));

        $this->assertEquals(5, $facture->getDaysOverdue());
    }

    public function testGetDaysOverdueReturnsZeroWhenPaid(): void
    {
        $facture = new Facture();
        $facture->setStatus(Facture::STATUS_PAYE);
        $facture->setDateEcheance(new \DateTimeImmutable('-5 days'));

        $this->assertEquals(0, $facture->getDaysOverdue());
    }

    // ===== CAN BE PAID TESTS =====

    public function testCanBePaidReturnsTrueWhenEnvoye(): void
    {
        $facture = new Facture();
        $facture->setStatus(Facture::STATUS_ENVOYE);

        $this->assertTrue($facture->canBePaid());
    }

    public function testCanBePaidReturnsTrueWhenRelance(): void
    {
        $facture = new Facture();
        $facture->setStatus(Facture::STATUS_RELANCE);

        $this->assertTrue($facture->canBePaid());
    }

    public function testCanBePaidReturnsTrueWhenEnRetard(): void
    {
        $facture = new Facture();
        $facture->setStatus(Facture::STATUS_EN_RETARD);

        $this->assertTrue($facture->canBePaid());
    }

    public function testCanBePaidReturnsFalseWhenBrouillon(): void
    {
        $facture = new Facture();
        $facture->setStatus(Facture::STATUS_BROUILLON);

        $this->assertFalse($facture->canBePaid());
    }

    public function testCanBePaidReturnsFalseWhenPaye(): void
    {
        $facture = new Facture();
        $facture->setStatus(Facture::STATUS_PAYE);

        $this->assertFalse($facture->canBePaid());
    }

    // ===== CAN BE SENT TESTS =====

    public function testCanBeSentReturnsTrueWhenBrouillon(): void
    {
        $facture = new Facture();
        $facture->setStatus(Facture::STATUS_BROUILLON);

        $this->assertTrue($facture->canBeSent());
    }

    public function testCanBeSentReturnsTrueWhenAEnvoyer(): void
    {
        $facture = new Facture();
        $facture->setStatus(Facture::STATUS_A_ENVOYER);

        $this->assertTrue($facture->canBeSent());
    }

    public function testCanBeSentReturnsFalseWhenEnvoye(): void
    {
        $facture = new Facture();
        $facture->setStatus(Facture::STATUS_ENVOYE);

        $this->assertFalse($facture->canBeSent());
    }

    // ===== TYPE HELPERS TESTS =====

    public function testIsAcompteReturnsTrue(): void
    {
        $facture = new Facture();
        $facture->setType(Facture::TYPE_ACOMPTE);

        $this->assertTrue($facture->isAcompte());
        $this->assertFalse($facture->isSolde());
        $this->assertFalse($facture->isStandard());
    }

    public function testIsSoldeReturnsTrue(): void
    {
        $facture = new Facture();
        $facture->setType(Facture::TYPE_SOLDE);

        $this->assertFalse($facture->isAcompte());
        $this->assertTrue($facture->isSolde());
        $this->assertFalse($facture->isStandard());
    }

    public function testIsStandardReturnsTrue(): void
    {
        $facture = new Facture();
        $facture->setType(Facture::TYPE_STANDARD);

        $this->assertFalse($facture->isAcompte());
        $this->assertFalse($facture->isSolde());
        $this->assertTrue($facture->isStandard());
    }

    // ===== GET TYPE LABEL TESTS =====

    public function testGetTypeLabelReturnsCorrectLabel(): void
    {
        $facture = new Facture();

        $facture->setType(Facture::TYPE_STANDARD);
        $this->assertEquals('Standard', $facture->getTypeLabel());

        $facture->setType(Facture::TYPE_ACOMPTE);
        $this->assertEquals('Acompte', $facture->getTypeLabel());

        $facture->setType(Facture::TYPE_SOLDE);
        $this->assertEquals('Solde', $facture->getTypeLabel());
    }

    // ===== SET STATUS AUTO-DATE TESTS =====

    public function testSetStatusEnvoyeSetsDateEnvoi(): void
    {
        $facture = new Facture();
        $this->assertNull($facture->getDateEnvoi());

        $facture->setStatus(Facture::STATUS_ENVOYE);

        $this->assertNotNull($facture->getDateEnvoi());
    }

    public function testSetStatusPayeSetsDatePaiement(): void
    {
        $facture = new Facture();
        $this->assertNull($facture->getDatePaiement());

        $facture->setStatus(Facture::STATUS_PAYE);

        $this->assertNotNull($facture->getDatePaiement());
    }

    public function testSetStatusEnvoyeDoesNotOverwriteDateEnvoi(): void
    {
        $facture = new Facture();
        $existingDate = new \DateTimeImmutable('-5 days');
        $facture->setDateEnvoi($existingDate);

        $facture->setStatus(Facture::STATUS_ENVOYE);

        $this->assertSame($existingDate, $facture->getDateEnvoi());
    }

    // ===== UPDATE STATUS BASED ON DEADLINE TESTS =====

    public function testUpdateStatusBasedOnDeadlineChangesToARelancer(): void
    {
        $facture = new Facture();
        $facture->setStatus(Facture::STATUS_ENVOYE);
        $facture->setDateEcheance(new \DateTimeImmutable('-1 day'));

        $changed = $facture->updateStatusBasedOnDeadline();

        $this->assertTrue($changed);
        $this->assertEquals(Facture::STATUS_A_RELANCER, $facture->getStatus());
    }

    public function testUpdateStatusBasedOnDeadlineDoesNothingIfNotPast(): void
    {
        $facture = new Facture();
        $facture->setStatus(Facture::STATUS_ENVOYE);
        $facture->setDateEcheance(new \DateTimeImmutable('+1 day'));

        $changed = $facture->updateStatusBasedOnDeadline();

        $this->assertFalse($changed);
        $this->assertEquals(Facture::STATUS_ENVOYE, $facture->getStatus());
    }

    public function testUpdateStatusBasedOnDeadlineDoesNothingIfPaid(): void
    {
        $facture = new Facture();
        $facture->setStatus(Facture::STATUS_PAYE);
        $facture->setDateEcheance(new \DateTimeImmutable('-1 day'));

        $changed = $facture->updateStatusBasedOnDeadline();

        $this->assertFalse($changed);
        $this->assertEquals(Facture::STATUS_PAYE, $facture->getStatus());
    }

    public function testUpdateStatusBasedOnDeadlineDoesNothingIfAnnule(): void
    {
        $facture = new Facture();
        $facture->setStatus(Facture::STATUS_ANNULE);
        $facture->setDateEcheance(new \DateTimeImmutable('-1 day'));

        $changed = $facture->updateStatusBasedOnDeadline();

        $this->assertFalse($changed);
        $this->assertEquals(Facture::STATUS_ANNULE, $facture->getStatus());
    }

    // ===== CREATE FROM DEVIS TESTS =====

    public function testCreateFromDevisCopiesBasicProperties(): void
    {
        $client = new Client();
        $client->setName('Test Client');

        $devis = new Devis();
        $devis->setClient($client);
        $devis->setTitle('Création site web');
        $devis->setDescription('Description du projet');
        $devis->setAdditionalInfo('Infos complémentaires');
        $devis->setTotalHt('1000.00');
        $devis->setVatRate('20.00');
        $devis->setConditions('Conditions générales');

        $facture = new Facture();
        $facture->createFromDevis($devis);

        $this->assertSame($devis, $facture->getDevis());
        $this->assertSame($client, $facture->getClient());
        $this->assertEquals('Création site web', $facture->getTitle());
        $this->assertEquals('Description du projet', $facture->getDescription());
        $this->assertEquals('Infos complémentaires', $facture->getAdditionalInfo());
        $this->assertEquals('1000.00', $facture->getTotalHt());
        $this->assertEquals('20.00', $facture->getVatRate());
        $this->assertEquals('Conditions générales', $facture->getConditions());
    }

    public function testCreateFromDevisCopiesItems(): void
    {
        $devis = new Devis();
        $devis->setClient(new Client());

        $devisItem = new DevisItem();
        $devisItem->setDescription('Prestation 1');
        $devisItem->setQuantity('2.00');
        $devisItem->setUnitPrice('500.00');
        $devisItem->setTotal('1000.00');
        $devis->addItem($devisItem);

        $facture = new Facture();
        $facture->createFromDevis($devis);

        $this->assertCount(1, $facture->getItems());
        $factureItem = $facture->getItems()->first();
        $this->assertEquals('Prestation 1', $factureItem->getDescription());
        $this->assertEquals('2.00', $factureItem->getQuantity());
        $this->assertEquals('500.00', $factureItem->getUnitPrice());
        $this->assertEquals('1000.00', $factureItem->getTotal());
    }

    // ===== ACOMPTE HELPERS TESTS =====

    public function testGetAcompteAmountReturnsFromDevis(): void
    {
        $devis = new Devis();
        $devis->setAcompte('500.00');

        $facture = new Facture();
        $facture->setDevis($devis);

        $this->assertEquals(500.0, $facture->getAcompteAmount());
    }

    public function testGetAcompteAmountReturnsNullWithoutDevis(): void
    {
        $facture = new Facture();

        $this->assertNull($facture->getAcompteAmount());
    }

    public function testGetAcomptePercentageReturnsFromDevis(): void
    {
        $devis = new Devis();
        $devis->setAcomptePercentage('30.00');

        $facture = new Facture();
        $facture->setDevis($devis);

        $this->assertEquals(30.0, $facture->getAcomptePercentage());
    }

    public function testGetNetAPayerReturnsTotalMinusAcompte(): void
    {
        $devis = new Devis();
        $devis->setAcompte('200.00');

        $facture = new Facture();
        $facture->setTotalHt('1000.00');
        $facture->setVatRate('20.00'); // totalTtc = 1200.00
        $facture->setDevis($devis);

        $this->assertEquals(1000.0, $facture->getNetAPayer()); // 1200 - 200
    }

    public function testGetNetAPayerReturnsTotalWhenNoAcompte(): void
    {
        $facture = new Facture();
        $facture->setTotalHt('1000.00');
        $facture->setVatRate('20.00'); // totalTtc = 1200.00

        $this->assertEquals(1200.0, $facture->getNetAPayer());
    }

    // ===== COLLECTION TESTS =====

    public function testAddItemSetsFactureOnItem(): void
    {
        $facture = new Facture();
        $item = new FactureItem();

        $facture->addItem($item);

        $this->assertTrue($facture->getItems()->contains($item));
        $this->assertSame($facture, $item->getFacture());
    }

    public function testRemoveItemRemovesFactureFromItem(): void
    {
        $facture = new Facture();
        $item = new FactureItem();
        $facture->addItem($item);

        $facture->removeItem($item);

        $this->assertFalse($facture->getItems()->contains($item));
        $this->assertNull($item->getFacture());
    }

    // ===== STATUS LABEL TESTS =====

    public function testGetStatusLabelReturnsCorrectLabel(): void
    {
        $facture = new Facture();

        $facture->setStatus(Facture::STATUS_BROUILLON);
        $this->assertEquals('Brouillon', $facture->getStatusLabel());

        $facture->setStatus(Facture::STATUS_PAYE);
        $this->assertEquals('Payé', $facture->getStatusLabel());

        $facture->setStatus(Facture::STATUS_EN_RETARD);
        $this->assertEquals('En retard', $facture->getStatusLabel());
    }

    // ===== TO STRING TESTS =====

    public function testToStringReturnsNumberAndTitle(): void
    {
        $facture = new Facture();
        $facture->setNumber('FAC-2026-0001');
        $facture->setTitle('Facture Site Web');

        $this->assertEquals('FAC-2026-0001 - Facture Site Web', (string) $facture);
    }

    // ===== STATIC METHODS TESTS =====

    public function testGetStatusChoicesReturnsAllStatuses(): void
    {
        $choices = Facture::getStatusChoices();

        $this->assertIsArray($choices);
        $this->assertArrayHasKey('Brouillon', $choices);
        $this->assertArrayHasKey('Payé', $choices);
        $this->assertArrayHasKey('En retard', $choices);
        $this->assertEquals(Facture::STATUS_PAYE, $choices['Payé']);
    }

    public function testGetTypeChoicesReturnsAllTypes(): void
    {
        $choices = Facture::getTypeChoices();

        $this->assertIsArray($choices);
        $this->assertArrayHasKey('Standard', $choices);
        $this->assertArrayHasKey('Acompte', $choices);
        $this->assertArrayHasKey('Solde', $choices);
    }

    public function testGetModePaiementChoicesReturnsAllModes(): void
    {
        $choices = Facture::getModePaiementChoices();

        $this->assertIsArray($choices);
        $this->assertArrayHasKey('Virement bancaire', $choices);
        $this->assertArrayHasKey('Chèque', $choices);
        $this->assertArrayHasKey('Carte bancaire', $choices);
    }
}
