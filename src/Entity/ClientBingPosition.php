<?php

namespace App\Entity;

use App\Repository\ClientBingPositionRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ClientBingPositionRepository::class)]
#[ORM\UniqueConstraint(name: 'unique_client_bing_position', columns: ['client_bing_keyword_id', 'date'])]
#[ORM\Index(columns: ['date'], name: 'idx_client_bing_position_date')]
class ClientBingPosition
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: ClientBingKeyword::class, inversedBy: 'positions')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?ClientBingKeyword $clientBingKeyword = null;

    #[ORM\Column]
    private ?float $position = null;

    #[ORM\Column]
    private int $clicks = 0;

    #[ORM\Column]
    private int $impressions = 0;

    #[ORM\Column(type: 'date_immutable')]
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

    public function getClientBingKeyword(): ?ClientBingKeyword
    {
        return $this->clientBingKeyword;
    }

    public function setClientBingKeyword(?ClientBingKeyword $clientBingKeyword): static
    {
        $this->clientBingKeyword = $clientBingKeyword;
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

    public function getCtr(): float
    {
        if ($this->impressions === 0) {
            return 0.0;
        }
        return round(($this->clicks / $this->impressions) * 100, 2);
    }

    public function __toString(): string
    {
        $keywordName = $this->clientBingKeyword?->getKeyword() ?? '?';
        $dateStr = $this->date?->format('d/m/Y') ?? '?';
        return sprintf('%s - Position %.1f (%s)', $keywordName, $this->position ?? 0, $dateStr);
    }
}
