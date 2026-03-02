<?php

namespace App\Entity;

use App\Repository\OfferRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: OfferRepository::class)]
class Offer
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private ?string $name = null;

    #[ORM\Column(length: 50, unique: true)]
    private ?string $slug = null;

    #[ORM\Column(length: 30)]
    private ?string $category = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $price = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $promoPrice = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $promoEndDate = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $promoLabel = null;

    #[ORM\Column]
    private bool $isRecurring = false;

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $priceSuffix = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $shortDescription = null;

    #[ORM\Column]
    private bool $isFeatured = false;

    #[ORM\Column]
    private bool $isActive = true;

    #[ORM\Column]
    private int $sortOrder = 0;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;
        return $this;
    }

    public function getCategory(): ?string
    {
        return $this->category;
    }

    public function setCategory(string $category): static
    {
        $this->category = $category;
        return $this;
    }

    public function getPrice(): ?string
    {
        return $this->price;
    }

    public function setPrice(string $price): static
    {
        $this->price = $price;
        return $this;
    }

    public function getPromoPrice(): ?string
    {
        return $this->promoPrice;
    }

    public function setPromoPrice(?string $promoPrice): static
    {
        $this->promoPrice = $promoPrice;
        return $this;
    }

    public function getPromoEndDate(): ?\DateTimeInterface
    {
        return $this->promoEndDate;
    }

    public function setPromoEndDate(?\DateTimeInterface $promoEndDate): static
    {
        $this->promoEndDate = $promoEndDate;
        return $this;
    }

    public function getPromoLabel(): ?string
    {
        return $this->promoLabel;
    }

    public function setPromoLabel(?string $promoLabel): static
    {
        $this->promoLabel = $promoLabel;
        return $this;
    }

    public function isRecurring(): bool
    {
        return $this->isRecurring;
    }

    public function setIsRecurring(bool $isRecurring): static
    {
        $this->isRecurring = $isRecurring;
        return $this;
    }

    public function getPriceSuffix(): ?string
    {
        return $this->priceSuffix;
    }

    public function setPriceSuffix(?string $priceSuffix): static
    {
        $this->priceSuffix = $priceSuffix;
        return $this;
    }

    public function getShortDescription(): ?string
    {
        return $this->shortDescription;
    }

    public function setShortDescription(?string $shortDescription): static
    {
        $this->shortDescription = $shortDescription;
        return $this;
    }

    public function isFeatured(): bool
    {
        return $this->isFeatured;
    }

    public function setIsFeatured(bool $isFeatured): static
    {
        $this->isFeatured = $isFeatured;
        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;
        return $this;
    }

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    public function setSortOrder(int $sortOrder): static
    {
        $this->sortOrder = $sortOrder;
        return $this;
    }

    // === Helper methods ===

    public function hasActivePromo(): bool
    {
        if ($this->promoPrice === null) {
            return false;
        }

        if ($this->promoEndDate === null) {
            return true;
        }

        return $this->promoEndDate > new \DateTime();
    }

    public function getCurrentPrice(): string
    {
        return $this->hasActivePromo() ? $this->promoPrice : $this->price;
    }

    public function getStrikethroughPrice(): ?string
    {
        return $this->hasActivePromo() ? $this->price : null;
    }

    public function getFormattedCurrentPrice(): string
    {
        return number_format((float) $this->getCurrentPrice(), 0, ',', ' ');
    }

    public function getFormattedStrikethroughPrice(): ?string
    {
        $price = $this->getStrikethroughPrice();
        return $price !== null ? number_format((float) $price, 0, ',', ' ') : null;
    }

    public function getSchemaPrice(): string
    {
        return number_format((float) $this->getCurrentPrice(), 0, '.', '');
    }

    public function __toString(): string
    {
        return $this->name ?? 'Nouvelle offre';
    }
}
