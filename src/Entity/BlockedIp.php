<?php

namespace App\Entity;

use App\Repository\BlockedIpRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BlockedIpRepository::class)]
#[ORM\Table(name: 'blocked_ip')]
#[ORM\Index(columns: ['ip_address'], name: 'idx_blocked_ip_address')]
#[ORM\Index(columns: ['expires_at'], name: 'idx_blocked_ip_expires')]
#[ORM\Index(columns: ['is_active'], name: 'idx_blocked_ip_active')]
class BlockedIp
{
    public const REASON_AUTO_THRESHOLD = 'auto_threshold';
    public const REASON_MANUAL = 'manual';
    public const REASON_ATTACK_PATTERN = 'attack_pattern';

    public const DURATION_1_HOUR = 3600;
    public const DURATION_24_HOURS = 86400;
    public const DURATION_7_DAYS = 604800;
    public const DURATION_30_DAYS = 2592000;
    public const DURATION_PERMANENT = null;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 45, unique: true)]
    private ?string $ipAddress = null;

    #[ORM\Column(length: 50)]
    private ?string $reason = self::REASON_MANUAL;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column]
    private bool $isAutomatic = false;

    #[ORM\Column]
    private bool $isActive = true;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $expiresAt = null;

    #[ORM\Column]
    private int $hitCount = 0;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastHitAt = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $triggerData = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function setIpAddress(string $ipAddress): static
    {
        $this->ipAddress = $ipAddress;
        return $this;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }

    public function setReason(string $reason): static
    {
        $this->reason = $reason;
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

    public function isAutomatic(): bool
    {
        return $this->isAutomatic;
    }

    public function setIsAutomatic(bool $isAutomatic): static
    {
        $this->isAutomatic = $isAutomatic;
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

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getExpiresAt(): ?\DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(?\DateTimeImmutable $expiresAt): static
    {
        $this->expiresAt = $expiresAt;
        return $this;
    }

    public function getHitCount(): int
    {
        return $this->hitCount;
    }

    public function setHitCount(int $hitCount): static
    {
        $this->hitCount = $hitCount;
        return $this;
    }

    public function incrementHitCount(): static
    {
        $this->hitCount++;
        $this->lastHitAt = new \DateTimeImmutable();
        return $this;
    }

    public function getLastHitAt(): ?\DateTimeImmutable
    {
        return $this->lastHitAt;
    }

    public function setLastHitAt(?\DateTimeImmutable $lastHitAt): static
    {
        $this->lastHitAt = $lastHitAt;
        return $this;
    }

    public function getTriggerData(): ?array
    {
        return $this->triggerData;
    }

    public function setTriggerData(?array $triggerData): static
    {
        $this->triggerData = $triggerData;
        return $this;
    }

    public function isExpired(): bool
    {
        if ($this->expiresAt === null) {
            return false;
        }
        return $this->expiresAt < new \DateTimeImmutable();
    }

    public function isEffectivelyBlocked(): bool
    {
        return $this->isActive && !$this->isExpired();
    }

    public function getReasonLabel(): string
    {
        return match ($this->reason) {
            self::REASON_AUTO_THRESHOLD => 'Seuil automatique',
            self::REASON_MANUAL => 'Blocage manuel',
            self::REASON_ATTACK_PATTERN => 'Pattern d\'attaque',
            default => $this->reason,
        };
    }

    public function __toString(): string
    {
        return $this->ipAddress ?? '';
    }
}
