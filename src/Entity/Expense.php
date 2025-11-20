<?php

namespace App\Entity;

use App\Repository\ExpenseRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ExpenseRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Expense
{
    // Catégories de dépenses
    public const CATEGORY_SOFTWARE = 'software';
    public const CATEGORY_HARDWARE = 'hardware';
    public const CATEGORY_TRAINING = 'training';
    public const CATEGORY_SUBSCRIPTION = 'subscription';
    public const CATEGORY_MARKETING = 'marketing';
    public const CATEGORY_OFFICE = 'office';
    public const CATEGORY_TRAVEL = 'travel';
    public const CATEGORY_OTHER = 'other';

    public const CATEGORIES = [
        self::CATEGORY_SOFTWARE => 'Logiciels',
        self::CATEGORY_HARDWARE => 'Matériel',
        self::CATEGORY_TRAINING => 'Formation',
        self::CATEGORY_SUBSCRIPTION => 'Abonnements',
        self::CATEGORY_MARKETING => 'Marketing',
        self::CATEGORY_OFFICE => 'Bureau',
        self::CATEGORY_TRAVEL => 'Déplacements',
        self::CATEGORY_OTHER => 'Autre',
    ];

    // Récurrence des dépenses
    public const RECURRENCE_PONCTUELLE = 'ponctuelle';
    public const RECURRENCE_MENSUELLE = 'mensuelle';
    public const RECURRENCE_ANNUELLE = 'annuelle';

    public const RECURRENCES = [
        self::RECURRENCE_PONCTUELLE => 'Ponctuelle',
        self::RECURRENCE_MENSUELLE => 'Mensuelle',
        self::RECURRENCE_ANNUELLE => 'Annuelle',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $amount = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private ?\DateTimeImmutable $dateExpense = null;

    #[ORM\Column(length: 50)]
    private ?string $category = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(length: 50)]
    private ?string $recurrence = self::RECURRENCE_PONCTUELLE;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $startDate = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $endDate = null;

    #[ORM\Column(type: Types::BOOLEAN)]
    private ?bool $isActive = true;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getAmount(): ?string
    {
        return $this->amount;
    }

    public function setAmount(string $amount): static
    {
        $this->amount = $amount;

        return $this;
    }

    public function getDateExpense(): ?\DateTimeImmutable
    {
        return $this->dateExpense;
    }

    public function setDateExpense(\DateTimeImmutable $dateExpense): static
    {
        $this->dateExpense = $dateExpense;

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

    public function getCategoryLabel(): string
    {
        return self::CATEGORIES[$this->category] ?? $this->category;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getRecurrence(): ?string
    {
        return $this->recurrence;
    }

    public function setRecurrence(string $recurrence): static
    {
        $this->recurrence = $recurrence;

        return $this;
    }

    public function getRecurrenceLabel(): string
    {
        return self::RECURRENCES[$this->recurrence] ?? $this->recurrence;
    }

    public function getStartDate(): ?\DateTimeImmutable
    {
        return $this->startDate;
    }

    public function setStartDate(?\DateTimeImmutable $startDate): static
    {
        $this->startDate = $startDate;

        return $this;
    }

    public function getEndDate(): ?\DateTimeImmutable
    {
        return $this->endDate;
    }

    public function setEndDate(?\DateTimeImmutable $endDate): static
    {
        $this->endDate = $endDate;

        return $this;
    }

    public function isActive(): ?bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

        return $this;
    }

    public function isRecurring(): bool
    {
        return $this->recurrence !== self::RECURRENCE_PONCTUELLE;
    }

    public function __toString(): string
    {
        return $this->title ?? 'Dépense #' . $this->id;
    }
}
