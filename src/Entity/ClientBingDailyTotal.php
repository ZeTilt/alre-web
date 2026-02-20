<?php

namespace App\Entity;

use App\Repository\ClientBingDailyTotalRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ClientBingDailyTotalRepository::class)]
#[ORM\UniqueConstraint(name: 'unique_client_bing_daily_total', columns: ['client_site_id', 'date'])]
class ClientBingDailyTotal
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: ClientSite::class, inversedBy: 'bingDailyTotals')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?ClientSite $clientSite = null;

    #[ORM\Column(type: 'date_immutable')]
    private ?\DateTimeImmutable $date = null;

    #[ORM\Column]
    private int $clicks = 0;

    #[ORM\Column]
    private int $impressions = 0;

    #[ORM\Column]
    private float $position = 0;

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

    public function getClientSite(): ?ClientSite
    {
        return $this->clientSite;
    }

    public function setClientSite(?ClientSite $clientSite): static
    {
        $this->clientSite = $clientSite;
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

    public function getPosition(): float
    {
        return $this->position;
    }

    public function setPosition(float $position): static
    {
        $this->position = $position;
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
        $dateStr = $this->date?->format('d/m/Y') ?? '?';
        return sprintf('%s: %d clics, %d impr (Bing)', $dateStr, $this->clicks, $this->impressions);
    }
}
