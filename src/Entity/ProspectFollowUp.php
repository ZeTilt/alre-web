<?php

namespace App\Entity;

use App\Repository\ProspectFollowUpRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProspectFollowUpRepository::class)]
#[ORM\HasLifecycleCallbacks]
class ProspectFollowUp
{
    // Priority constants
    public const PRIORITY_LOW = 'low';
    public const PRIORITY_MEDIUM = 'medium';
    public const PRIORITY_HIGH = 'high';

    // Urgency levels for display
    public const URGENCY_NORMAL = 'normal';
    public const URGENCY_SOON = 'soon';
    public const URGENCY_OVERDUE = 'overdue';
    public const URGENCY_COMPLETED = 'completed';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Prospect::class, inversedBy: 'followUps')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Prospect $prospect = null;

    #[ORM\Column(length: 255)]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $dueAt = null;

    #[ORM\Column(length: 20)]
    private string $priority = self::PRIORITY_MEDIUM;

    #[ORM\Column]
    private bool $isCompleted = false;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $completedAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    public static function getPriorityChoices(): array
    {
        return [
            'Basse' => self::PRIORITY_LOW,
            'Moyenne' => self::PRIORITY_MEDIUM,
            'Haute' => self::PRIORITY_HIGH,
        ];
    }

    public function getPriorityLabel(): string
    {
        return array_search($this->priority, self::getPriorityChoices()) ?: $this->priority;
    }

    public function getUrgencyLevel(): string
    {
        if ($this->isCompleted) {
            return self::URGENCY_COMPLETED;
        }

        $now = new \DateTime();
        $now->setTime(0, 0, 0);

        $dueDate = clone $this->dueAt;
        $dueDate->setTime(0, 0, 0);

        if ($dueDate < $now) {
            return self::URGENCY_OVERDUE;
        }

        $diff = $now->diff($dueDate);
        if ($diff->days <= 2) {
            return self::URGENCY_SOON;
        }

        return self::URGENCY_NORMAL;
    }

    public function getUrgencyBadgeClass(): string
    {
        return match ($this->getUrgencyLevel()) {
            self::URGENCY_OVERDUE => 'danger',
            self::URGENCY_SOON => 'warning',
            self::URGENCY_NORMAL => 'success',
            self::URGENCY_COMPLETED => 'secondary',
            default => 'secondary',
        };
    }

    public function isOverdue(): bool
    {
        return $this->getUrgencyLevel() === self::URGENCY_OVERDUE;
    }

    public function isDueSoon(): bool
    {
        return $this->getUrgencyLevel() === self::URGENCY_SOON;
    }

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function complete(): void
    {
        $this->isCompleted = true;
        $this->completedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProspect(): ?Prospect
    {
        return $this->prospect;
    }

    public function setProspect(?Prospect $prospect): static
    {
        $this->prospect = $prospect;
        return $this;
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

    public function getDueAt(): ?\DateTimeInterface
    {
        return $this->dueAt;
    }

    public function setDueAt(\DateTimeInterface $dueAt): static
    {
        $this->dueAt = $dueAt;
        return $this;
    }

    public function getPriority(): string
    {
        return $this->priority;
    }

    public function setPriority(string $priority): static
    {
        $this->priority = $priority;
        return $this;
    }

    public function isCompleted(): bool
    {
        return $this->isCompleted;
    }

    public function setIsCompleted(bool $isCompleted): static
    {
        $this->isCompleted = $isCompleted;
        if ($isCompleted && !$this->completedAt) {
            $this->completedAt = new \DateTimeImmutable();
        }
        return $this;
    }

    public function getCompletedAt(): ?\DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function setCompletedAt(?\DateTimeImmutable $completedAt): static
    {
        $this->completedAt = $completedAt;
        return $this;
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

    public function __toString(): string
    {
        return $this->title ?? 'Nouvelle relance';
    }
}
