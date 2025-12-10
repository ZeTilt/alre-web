<?php

namespace App\Entity;

use App\Entity\Traits\DocumentItemTrait;
use App\Repository\FactureItemRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FactureItemRepository::class)]
class FactureItem
{
    use DocumentItemTrait;

    #[ORM\ManyToOne(inversedBy: 'items')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Facture $facture = null;

    public function getFacture(): ?Facture
    {
        return $this->facture;
    }

    public function setFacture(?Facture $facture): static
    {
        $this->facture = $facture;

        return $this;
    }

    /**
     * Override calculateTotal to include VAT in total for invoices
     */
    protected function calculateTotal(): void
    {
        $quantity = (float) $this->quantity;
        $unitPrice = (float) $this->unitPrice;
        $discount = (float) ($this->discount ?? 0);
        $vatRate = (float) ($this->vatRate ?? 20);

        $subtotal = $quantity * $unitPrice;
        $discountAmount = $subtotal * ($discount / 100);
        $totalAfterDiscount = $subtotal - $discountAmount;
        $vatAmount = $totalAfterDiscount * ($vatRate / 100);

        $this->total = number_format($totalAfterDiscount + $vatAmount, 2, '.', '');
    }
}
