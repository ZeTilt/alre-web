<?php

namespace App\Entity;

use App\Repository\SeoPositionRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SeoPositionRepository::class)]
#[ORM\Index(columns: ['date'], name: 'idx_seo_position_date')]
class SeoPosition
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: SeoKeyword::class, inversedBy: 'positions')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?SeoKeyword $keyword = null;

    #[ORM\Column]
    private ?float $position = null;

    #[ORM\Column]
    private int $clicks = 0;

    #[ORM\Column]
    private int $impressions = 0;

    #[ORM\Column]
    private ?\DateTimeImmutable $date = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getKeyword(): ?SeoKeyword
    {
        return $this->keyword;
    }

    public function setKeyword(?SeoKeyword $keyword): static
    {
        $this->keyword = $keyword;
        return $this;
    }

    public function getPosition(): ?float
    {
        return $this->position;
    }

    public function setPosition(float $position): static
    {
        $this->position = $position;
        return $this;
    }

    public function getClicks(): int
    {
        return $this->clicks;
    }

    public function setClicks(int $clicks): static
    {
        $this->clicks = $clicks;
        return $this;
    }

    public function getImpressions(): int
    {
        return $this->impressions;
    }

    public function setImpressions(int $impressions): static
    {
        $this->impressions = $impressions;
        return $this;
    }

    public function getDate(): ?\DateTimeImmutable
    {
        return $this->date;
    }

    public function setDate(\DateTimeImmutable $date): static
    {
        $this->date = $date;
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
     * Calcule le CTR (Click-Through Rate).
     */
    public function getCtr(): float
    {
        if ($this->impressions === 0) {
            return 0.0;
        }
        return round(($this->clicks / $this->impressions) * 100, 2);
    }

    public function __toString(): string
    {
        $keywordName = $this->keyword?->getKeyword() ?? '?';
        $dateStr = $this->date?->format('d/m/Y') ?? '?';
        return sprintf('%s - Position %.1f (%s)', $keywordName, $this->position ?? 0, $dateStr);
    }
}
