<?php

namespace App\Entity;

use App\Repository\ProspectInteractionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProspectInteractionRepository::class)]
#[ORM\HasLifecycleCallbacks]
class ProspectInteraction
{
    // Type constants
    public const TYPE_EMAIL = 'email';
    public const TYPE_PHONE = 'phone';
    public const TYPE_LINKEDIN = 'linkedin';
    public const TYPE_FACEBOOK = 'facebook';
    public const TYPE_MEETING = 'meeting';
    public const TYPE_VIDEO_CALL = 'video_call';
    public const TYPE_SMS = 'sms';
    public const TYPE_OTHER = 'other';

    // Direction constants
    public const DIRECTION_SENT = 'sent';
    public const DIRECTION_RECEIVED = 'received';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Prospect::class, inversedBy: 'interactions')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Prospect $prospect = null;

    #[ORM\ManyToOne(targetEntity: ProspectContact::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?ProspectContact $contact = null;

    #[ORM\Column(length: 50)]
    private string $type = self::TYPE_EMAIL;

    #[ORM\Column(length: 50)]
    private string $direction = self::DIRECTION_SENT;

    #[ORM\Column(length: 255)]
    private ?string $subject = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $content = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $scheduledAt = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    public static function getTypeChoices(): array
    {
        return [
            'Email' => self::TYPE_EMAIL,
            'Telephone' => self::TYPE_PHONE,
            'LinkedIn' => self::TYPE_LINKEDIN,
            'Facebook' => self::TYPE_FACEBOOK,
            'Reunion' => self::TYPE_MEETING,
            'Visio' => self::TYPE_VIDEO_CALL,
            'SMS' => self::TYPE_SMS,
            'Autre' => self::TYPE_OTHER,
        ];
    }

    public static function getDirectionChoices(): array
    {
        return [
            'Envoye' => self::DIRECTION_SENT,
            'Recu' => self::DIRECTION_RECEIVED,
        ];
    }

    public function getTypeLabel(): string
    {
        return array_search($this->type, self::getTypeChoices()) ?: $this->type;
    }

    public function getDirectionLabel(): string
    {
        return array_search($this->direction, self::getDirectionChoices()) ?: $this->direction;
    }

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        $this->createdAt = new \DateTimeImmutable();
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

    public function getDirection(): string
    {
        return $this->direction;
    }

    public function setDirection(string $direction): static
    {
        $this->direction = $direction;
        return $this;
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

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getScheduledAt(): ?\DateTimeInterface
    {
        return $this->scheduledAt;
    }

    public function setScheduledAt(?\DateTimeInterface $scheduledAt): static
    {
        $this->scheduledAt = $scheduledAt;
        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;
        return $this;
    }

    public function __toString(): string
    {
        return $this->subject ?? 'Nouvelle interaction';
    }
}
