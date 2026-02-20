<?php

namespace App\Entity;

use App\Repository\SeoKeywordRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SeoKeywordRepository::class)]
class SeoKeyword
{
    public const SOURCE_MANUAL = 'manual';
    public const SOURCE_AUTO_GSC = 'auto_gsc';
    public const SOURCE_AUTO_BING = 'auto_bing';

    public const RELEVANCE_HIGH = 'high';
    public const RELEVANCE_MEDIUM = 'medium';
    public const RELEVANCE_LOW = 'low';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $keyword = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $targetUrl = null;

    #[ORM\Column]
    private bool $isActive = true;

    #[ORM\Column(length: 20)]
    private string $source = self::SOURCE_MANUAL;

    #[ORM\Column(length: 20)]
    private string $relevanceLevel = self::RELEVANCE_MEDIUM;

    #[ORM\Column(type: 'smallint', options: ['default' => 0])]
    private int $relevanceScore = 0;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastSeenInGsc = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastSyncAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $deactivatedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastOptimizedAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    /**
     * @var Collection<int, SeoPosition>
     */
    #[ORM\OneToMany(targetEntity: SeoPosition::class, mappedBy: 'keyword', orphanRemoval: true)]
    #[ORM\OrderBy(['date' => 'DESC'])]
    private Collection $positions;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->positions = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getKeyword(): ?string
    {
        return $this->keyword;
    }

    public function setKeyword(string $keyword): static
    {
        $this->keyword = $keyword;
        return $this;
    }

    public function getTargetUrl(): ?string
    {
        return $this->targetUrl;
    }

    public function setTargetUrl(?string $targetUrl): static
    {
        $this->targetUrl = $targetUrl;
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

    public function getLastSyncAt(): ?\DateTimeImmutable
    {
        return $this->lastSyncAt;
    }

    public function setLastSyncAt(?\DateTimeImmutable $lastSyncAt): static
    {
        $this->lastSyncAt = $lastSyncAt;
        return $this;
    }

    public function getDeactivatedAt(): ?\DateTimeImmutable
    {
        return $this->deactivatedAt;
    }

    public function setDeactivatedAt(?\DateTimeImmutable $deactivatedAt): static
    {
        $this->deactivatedAt = $deactivatedAt;
        return $this;
    }

    public function getLastOptimizedAt(): ?\DateTimeImmutable
    {
        return $this->lastOptimizedAt;
    }

    public function setLastOptimizedAt(?\DateTimeImmutable $lastOptimizedAt): static
    {
        $this->lastOptimizedAt = $lastOptimizedAt;
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

    /**
     * @return Collection<int, SeoPosition>
     */
    public function getPositions(): Collection
    {
        return $this->positions;
    }

    public function addPosition(SeoPosition $position): static
    {
        if (!$this->positions->contains($position)) {
            $this->positions->add($position);
            $position->setKeyword($this);
        }
        return $this;
    }

    public function removePosition(SeoPosition $position): static
    {
        if ($this->positions->removeElement($position)) {
            if ($position->getKeyword() === $this) {
                $position->setKeyword(null);
            }
        }
        return $this;
    }

    /**
     * Retourne la dernière position enregistrée.
     */
    public function getLatestPosition(): ?SeoPosition
    {
        if ($this->positions->isEmpty()) {
            return null;
        }
        return $this->positions->first();
    }

    public function __toString(): string
    {
        return $this->keyword ?? 'Nouveau mot-clé';
    }

    public function getSource(): string
    {
        return $this->source;
    }

    public function setSource(string $source): static
    {
        $this->source = $source;
        return $this;
    }

    public function isManual(): bool
    {
        return $this->source === self::SOURCE_MANUAL;
    }

    public function isAutoImported(): bool
    {
        return $this->source === self::SOURCE_AUTO_GSC;
    }

    public function getRelevanceLevel(): string
    {
        return $this->relevanceLevel;
    }

    public function setRelevanceLevel(string $relevanceLevel): static
    {
        $this->relevanceLevel = $relevanceLevel;
        return $this;
    }

    public function getRelevanceScore(): int
    {
        return $this->relevanceScore;
    }

    public function setRelevanceScore(int $relevanceScore): static
    {
        $this->relevanceScore = max(0, min(5, $relevanceScore));
        return $this;
    }

    public function getLastSeenInGsc(): ?\DateTimeImmutable
    {
        return $this->lastSeenInGsc;
    }

    public function setLastSeenInGsc(?\DateTimeImmutable $lastSeenInGsc): static
    {
        $this->lastSeenInGsc = $lastSeenInGsc;
        return $this;
    }

    public static function getSourceChoices(): array
    {
        return [
            'Manuel' => self::SOURCE_MANUAL,
            'Auto (GSC)' => self::SOURCE_AUTO_GSC,
            'Auto (Bing)' => self::SOURCE_AUTO_BING,
        ];
    }

    public static function getRelevanceLevelChoices(): array
    {
        return [
            'Haute' => self::RELEVANCE_HIGH,
            'Moyenne' => self::RELEVANCE_MEDIUM,
            'Basse' => self::RELEVANCE_LOW,
        ];
    }

    public function getRelevanceLevelLabel(): string
    {
        return match ($this->relevanceLevel) {
            self::RELEVANCE_HIGH => 'Haute',
            self::RELEVANCE_MEDIUM => 'Moyenne',
            self::RELEVANCE_LOW => 'Basse',
            default => $this->relevanceLevel,
        };
    }

    public function getSourceLabel(): string
    {
        return match ($this->source) {
            self::SOURCE_MANUAL => 'Manuel',
            self::SOURCE_AUTO_GSC => 'Auto (GSC)',
            self::SOURCE_AUTO_BING => 'Auto (Bing)',
            default => $this->source,
        };
    }
}
