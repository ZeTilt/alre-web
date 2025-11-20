<?php

namespace App\Entity;

use App\Repository\ExpenseGenerationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ExpenseGenerationRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\UniqueConstraint(name: 'unique_template_date', columns: ['template_expense_id', 'generated_for_date'])]
class ExpenseGeneration
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Expense::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Expense $templateExpense = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private ?\DateTimeImmutable $generatedForDate = null;

    #[ORM\ManyToOne(targetEntity: Expense::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Expense $generatedExpense = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $generatedAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTemplateExpense(): ?Expense
    {
        return $this->templateExpense;
    }

    public function setTemplateExpense(?Expense $templateExpense): static
    {
        $this->templateExpense = $templateExpense;

        return $this;
    }

    public function getGeneratedForDate(): ?\DateTimeImmutable
    {
        return $this->generatedForDate;
    }

    public function setGeneratedForDate(\DateTimeImmutable $generatedForDate): static
    {
        $this->generatedForDate = $generatedForDate;

        return $this;
    }

    public function getGeneratedExpense(): ?Expense
    {
        return $this->generatedExpense;
    }

    public function setGeneratedExpense(?Expense $generatedExpense): static
    {
        $this->generatedExpense = $generatedExpense;

        return $this;
    }

    public function getGeneratedAt(): ?\DateTimeImmutable
    {
        return $this->generatedAt;
    }

    public function setGeneratedAt(\DateTimeImmutable $generatedAt): static
    {
        $this->generatedAt = $generatedAt;

        return $this;
    }

    #[ORM\PrePersist]
    public function setGeneratedAtValue(): void
    {
        $this->generatedAt = new \DateTimeImmutable();
    }
}
