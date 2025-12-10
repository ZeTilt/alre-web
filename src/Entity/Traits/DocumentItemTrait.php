<?php

namespace App\Entity\Traits;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Trait for common document item properties and methods (Devis, Facture items)
 */
trait DocumentItemTrait
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $description = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $quantity = '1.00';

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $unit = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $unitPrice = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $total = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2, nullable: true)]
    private ?string $discount = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2, nullable: true)]
    private ?string $vatRate = '20.00';

    #[ORM\Column(nullable: true)]
    private ?int $position = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getQuantity(): ?string
    {
        return $this->quantity;
    }

    public function setQuantity(string $quantity): static
    {
        $this->quantity = $quantity;
        $this->calculateTotal();

        return $this;
    }

    public function getUnit(): ?string
    {
        return $this->unit;
    }

    public function setUnit(?string $unit): static
    {
        $this->unit = $unit;

        return $this;
    }

    public function getUnitPrice(): ?string
    {
        return $this->unitPrice;
    }

    public function setUnitPrice(string $unitPrice): static
    {
        $this->unitPrice = $unitPrice;
        $this->calculateTotal();

        return $this;
    }

    public function getTotal(): ?string
    {
        return $this->total;
    }

    public function setTotal(string $total): static
    {
        $this->total = $total;

        return $this;
    }

    public function getPosition(): ?int
    {
        return $this->position;
    }

    public function setPosition(?int $position): static
    {
        $this->position = $position;

        return $this;
    }

    public function getDiscount(): ?string
    {
        return $this->discount;
    }

    public function setDiscount(?string $discount): static
    {
        $this->discount = $discount;
        $this->calculateTotal();

        return $this;
    }

    public function getVatRate(): ?string
    {
        return $this->vatRate;
    }

    public function setVatRate(?string $vatRate): static
    {
        $this->vatRate = $vatRate;
        $this->calculateTotal();

        return $this;
    }

    public function getSubtotal(): float
    {
        return (float) $this->quantity * (float) $this->unitPrice;
    }

    public function getDiscountAmount(): float
    {
        $subtotal = $this->getSubtotal();
        $discount = (float) ($this->discount ?? 0);
        return $subtotal * ($discount / 100);
    }

    public function getTotalAfterDiscount(): float
    {
        return $this->getSubtotal() - $this->getDiscountAmount();
    }

    public function getVatAmount(): float
    {
        $totalAfterDiscount = $this->getTotalAfterDiscount();
        $vatRate = (float) ($this->vatRate ?? 0);
        return $totalAfterDiscount * ($vatRate / 100);
    }

    public function getTotalWithVat(): float
    {
        return $this->getTotalAfterDiscount() + $this->getVatAmount();
    }

    public function __toString(): string
    {
        return $this->description ?: '';
    }

    /**
     * Calculate total - can be overridden in entity if needed
     */
    protected function calculateTotal(): void
    {
        $quantity = (float) $this->quantity;
        $unitPrice = (float) $this->unitPrice;
        $discount = (float) ($this->discount ?? 0);

        $subtotal = $quantity * $unitPrice;
        $discountAmount = $subtotal * ($discount / 100);
        $total = $subtotal - $discountAmount;

        $this->total = number_format($total, 2, '.', '');
    }
}
