<?php

namespace App\Entity;

use App\Repository\ProspectFollowUpRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProspectFollowUpRepository::class)]
#[ORM\HasLifecycleCallbacks]
class ProspectFollowUp
{
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

    #[ORM\ManyToOne(targetEntity: ProspectContact::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?ProspectContact $contact = null;

    #[ORM\Column(length: 50)]
    private string $type = ProspectInteraction::TYPE_EMAIL;

    #[ORM\Column(length: 255)]
    private ?string $subject = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $content = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $dueAt = null;

    #[ORM\Column]
    private bool $isCompleted = false;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $completedAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    public function getUrgencyLevel(): string
    {
        if ($this->isCompleted) {
            return self::URGENCY_COMPLETED;
        }

        $now = new \DateTime();
        $now->setTime(0, 0, 0);

        if (!$this->dueAt) {
            return self::URGENCY_NORMAL;
        }

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

    public function getUrgencyLabel(): string
    {
        return match ($this->getUrgencyLevel()) {
            self::URGENCY_OVERDUE => 'En retard',
            self::URGENCY_SOON => 'Bientôt',
            self::URGENCY_NORMAL => 'Normal',
            self::URGENCY_COMPLETED => 'Terminé',
            default => 'Normal',
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

    /**
     * Convert this follow-up to an interaction when completed
     */
    public function toInteraction(): ProspectInteraction
    {
        $interaction = new ProspectInteraction();
        $interaction->setProspect($this->prospect);
        $interaction->setContact($this->contact);
        $interaction->setType($this->type);
        $interaction->setDirection(ProspectInteraction::DIRECTION_SENT);
        $interaction->setSubject($this->subject);
        $interaction->setContent($this->content);

        return $interaction;
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

    public function getContact(): ?ProspectContact
    {
        return $this->contact;
    }

    public function setContact(?ProspectContact $contact): static
    {
        $this->contact = $contact;
        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;
        return $this;
    }

    public function getTypeLabel(): string
    {
        return array_search($this->type, ProspectInteraction::getTypeChoices()) ?: $this->type;
    }

    public function getSubject(): ?string
    {
        return $this->subject;
    }

    public function setSubject(string $subject): static
    {
        $this->subject = $subject;
        return $this;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(?string $content): static
    {
        $this->content = $content;
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
        return $this->subject ?? 'Nouvelle relance';
    }
}
