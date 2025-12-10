<?php

namespace App\Tests\Unit\Entity;

use App\Entity\DevisItem;
use App\Entity\FactureItem;
use PHPUnit\Framework\TestCase;

class DocumentItemTraitTest extends TestCase
{
    // ===== DEVIS ITEM TESTS (trait without VAT in total) =====

    public function testDevisItemSimpleCalculation(): void
    {
        $item = new DevisItem();
        $item->setQuantity('2');
        $item->setUnitPrice('100.00');
        $item->setDiscount('0');
        $item->setVatRate('20.00');

        $this->assertEquals(200.0, $item->getSubtotal());
        $this->assertEquals(0.0, $item->getDiscountAmount());
        $this->assertEquals(200.0, $item->getTotalAfterDiscount());
        $this->assertEquals(40.0, $item->getVatAmount()); // 20% of 200
        $this->assertEquals(240.0, $item->getTotalWithVat());

        // DevisItem total is WITHOUT VAT
        $this->assertEquals('200.00', $item->getTotal());
    }

    public function testDevisItemWithDiscount(): void
    {
        $item = new DevisItem();
        $item->setQuantity('10');
        $item->setUnitPrice('50.00');
        $item->setDiscount('10'); // 10% discount
        $item->setVatRate('20.00');

        $this->assertEquals(500.0, $item->getSubtotal()); // 10 * 50
        $this->assertEquals(50.0, $item->getDiscountAmount()); // 10% of 500
        $this->assertEquals(450.0, $item->getTotalAfterDiscount());
        $this->assertEquals(90.0, $item->getVatAmount()); // 20% of 450
        $this->assertEquals(540.0, $item->getTotalWithVat());

        // DevisItem total is WITHOUT VAT
        $this->assertEquals('450.00', $item->getTotal());
    }

    public function testDevisItemWithZeroVat(): void
    {
        $item = new DevisItem();
        $item->setQuantity('1');
        $item->setUnitPrice('1000.00');
        $item->setDiscount('0');
        $item->setVatRate('0'); // Auto-entrepreneur, no VAT

        $this->assertEquals(1000.0, $item->getSubtotal());
        $this->assertEquals(0.0, $item->getVatAmount());
        $this->assertEquals(1000.0, $item->getTotalWithVat());
        $this->assertEquals('1000.00', $item->getTotal());
    }

    public function testDevisItemDecimalQuantity(): void
    {
        $item = new DevisItem();
        $item->setQuantity('2.5'); // 2.5 hours for example
        $item->setUnitPrice('80.00');
        $item->setDiscount('0');

        $this->assertEquals(200.0, $item->getSubtotal()); // 2.5 * 80
        $this->assertEquals('200.00', $item->getTotal());
    }

    // ===== FACTURE ITEM TESTS (trait WITH VAT in total) =====

    public function testFactureItemSimpleCalculation(): void
    {
        $item = new FactureItem();
        $item->setQuantity('2');
        $item->setUnitPrice('100.00');
        $item->setDiscount('0');
        $item->setVatRate('20.00');

        $this->assertEquals(200.0, $item->getSubtotal());
        $this->assertEquals(200.0, $item->getTotalAfterDiscount());
        $this->assertEquals(40.0, $item->getVatAmount());

        // FactureItem total INCLUDES VAT
        $this->assertEquals('240.00', $item->getTotal());
    }

    public function testFactureItemWithDiscount(): void
    {
        $item = new FactureItem();
        $item->setQuantity('10');
        $item->setUnitPrice('50.00');
        $item->setDiscount('10'); // 10% discount
        $item->setVatRate('20.00');

        $this->assertEquals(500.0, $item->getSubtotal());
        $this->assertEquals(50.0, $item->getDiscountAmount());
        $this->assertEquals(450.0, $item->getTotalAfterDiscount());
        $this->assertEquals(90.0, $item->getVatAmount());

        // FactureItem total INCLUDES VAT (450 + 90 = 540)
        $this->assertEquals('540.00', $item->getTotal());
    }

    public function testFactureItemWithZeroVat(): void
    {
        $item = new FactureItem();
        $item->setQuantity('1');
        $item->setUnitPrice('1000.00');
        $item->setDiscount('0');
        $item->setVatRate('0');

        $this->assertEquals(1000.0, $item->getSubtotal());
        $this->assertEquals(0.0, $item->getVatAmount());
        // Even with 0% VAT, total should equal subtotal
        $this->assertEquals('1000.00', $item->getTotal());
    }

    // ===== COMMON TESTS =====

    public function testItemDefaultValues(): void
    {
        $item = new DevisItem();

        $this->assertEquals('1.00', $item->getQuantity());
        $this->assertEquals('0.00', $item->getUnitPrice());
        $this->assertEquals('0.00', $item->getDiscount());
        $this->assertEquals('20.00', $item->getVatRate());
    }

    public function testItemPosition(): void
    {
        $item = new DevisItem();

        $this->assertNull($item->getPosition());

        $item->setPosition(5);
        $this->assertEquals(5, $item->getPosition());
    }

    public function testItemUnit(): void
    {
        $item = new DevisItem();

        $this->assertNull($item->getUnit());

        $item->setUnit('heure');
        $this->assertEquals('heure', $item->getUnit());

        $item->setUnit('jour');
        $this->assertEquals('jour', $item->getUnit());
    }

    public function testItemToString(): void
    {
        $item = new DevisItem();
        $item->setDescription('Développement site web');

        $this->assertEquals('Développement site web', (string) $item);
    }

    public function testItemToStringEmpty(): void
    {
        $item = new DevisItem();

        $this->assertEquals('', (string) $item);
    }

    public function testItemRecalculatesOnQuantityChange(): void
    {
        $item = new DevisItem();
        $item->setUnitPrice('100.00');
        $item->setQuantity('1');
        $this->assertEquals('100.00', $item->getTotal());

        $item->setQuantity('3');
        $this->assertEquals('300.00', $item->getTotal());
    }

    public function testItemRecalculatesOnPriceChange(): void
    {
        $item = new DevisItem();
        $item->setQuantity('2');
        $item->setUnitPrice('50.00');
        $this->assertEquals('100.00', $item->getTotal());

        $item->setUnitPrice('75.00');
        $this->assertEquals('150.00', $item->getTotal());
    }

    public function testItemRecalculatesOnDiscountChange(): void
    {
        $item = new DevisItem();
        $item->setQuantity('1');
        $item->setUnitPrice('100.00');
        $item->setDiscount('0');
        $this->assertEquals('100.00', $item->getTotal());

        $item->setDiscount('25'); // 25% off
        $this->assertEquals('75.00', $item->getTotal());
    }

    public function testLargeDiscount(): void
    {
        $item = new DevisItem();
        $item->setQuantity('1');
        $item->setUnitPrice('100.00');
        $item->setDiscount('50'); // 50% off

        $this->assertEquals(50.0, $item->getTotalAfterDiscount());
        $this->assertEquals('50.00', $item->getTotal());
    }

    public function testPrecisionMaintained(): void
    {
        $item = new DevisItem();
        $item->setQuantity('3');
        $item->setUnitPrice('33.33');
        $item->setDiscount('0');

        // 3 * 33.33 = 99.99
        $this->assertEquals('99.99', $item->getTotal());
    }
}
