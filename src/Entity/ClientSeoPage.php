<?php

namespace App\Entity;

use App\Repository\ClientSeoPageRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ClientSeoPageRepository::class)]
#[ORM\UniqueConstraint(name: 'unique_client_seo_page', columns: ['client_site_id', 'url_hash', 'date'])]
#[ORM\Index(columns: ['url_hash'], name: 'idx_client_seo_page_url_hash')]
class ClientSeoPage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: ClientSite::class, inversedBy: 'pages')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?ClientSite $clientSite = null;

    #[ORM\Column(length: 2000)]
    private ?string $url = null;

    #[ORM\Column(length: 64)]
    private ?string $urlHash = null;

    #[ORM\Column]
    private int $clicks = 0;

    #[ORM\Column]
    private int $impressions = 0;

    #[ORM\Column]
    private float $ctr = 0;

    #[ORM\Column]
    private float $position = 0;

    #[ORM\Column(type: 'date_immutable')]
    private ?\DateTimeImmutable $date = null;

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

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(string $url): static
    {
        $this->url = $url;
        $this->urlHash = hash('sha256', $url);
        return $this;
    }

    public function getUrlHash(): ?string
    {
        return $this->urlHash;
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

    public function getCtr(): float
    {
        return $this->ctr;
    }

    public function setCtr(float $ctr): static
    {
        $this->ctr = $ctr;
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

    public function getDate(): ?\DateTimeImmutable
    {
        return $this->date;
    }

    public function setDate(\DateTimeImmutable $date): static
    {
        $this->date = $date;
        return $this;
    }

    public function __toString(): string
    {
        $dateStr = $this->date?->format('d/m/Y') ?? '?';
        return sprintf('%s (%s)', $this->url ?? '?', $dateStr);
    }
}
