<?php

namespace App\Entity;

use App\Repository\ClientSeoKeywordRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ClientSeoKeywordRepository::class)]
#[ORM\UniqueConstraint(name: 'unique_client_seo_keyword', columns: ['client_site_id', 'keyword'])]
class ClientSeoKeyword
{
    public const RELEVANCE_HIGH = 'high';
    public const RELEVANCE_MEDIUM = 'medium';
    public const RELEVANCE_LOW = 'low';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: ClientSite::class, inversedBy: 'keywords')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?ClientSite $clientSite = null;

    #[ORM\Column(length: 255)]
    private ?string $keyword = null;

    #[ORM\Column]
    private bool $isActive = true;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastOptimizedAt = null;

    #[ORM\Column(length: 10, options: ['default' => 'medium'])]
    private string $relevanceLevel = 'medium';

    #[ORM\Column(type: 'smallint', options: ['default' => 0])]
    private int $relevanceScore = 0;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastSeenInGsc = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastSeenInBing = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $deactivatedAt = null;

    /**
     * @var Collection<int, ClientSeoPosition>
     */
    #[ORM\OneToMany(targetEntity: ClientSeoPosition::class, mappedBy: 'clientSeoKeyword', orphanRemoval: true)]
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

    public function getClientSite(): ?ClientSite
    {
        return $this->clientSite;
    }

    public function setClientSite(?ClientSite $clientSite): static
    {
        $this->clientSite = $clientSite;
        return $this;
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

    /**
     * @return Collection<int, ClientSeoPosition>
     */
    public function getPositions(): Collection
    {
        return $this->positions;
    }

    public function addPosition(ClientSeoPosition $position): static
    {
        if (!$this->positions->contains($position)) {
            $this->positions->add($position);
            $position->setClientSeoKeyword($this);
        }
        return $this;
    }

    public function removePosition(ClientSeoPosition $position): static
    {
        if ($this->positions->removeElement($position)) {
            if ($position->getClientSeoKeyword() === $this) {
                $position->setClientSeoKeyword(null);
            }
        }
        return $this;
    }

    public function getLatestPosition(): ?ClientSeoPosition
    {
        if ($this->positions->isEmpty()) {
            return null;
        }
        return $this->positions->first();
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

    public function getLastSeenInBing(): ?\DateTimeImmutable
    {
        return $this->lastSeenInBing;
    }

    public function setLastSeenInBing(?\DateTimeImmutable $lastSeenInBing): static
    {
        $this->lastSeenInBing = $lastSeenInBing;
        return $this;
    }

    public function getLastSeen(): ?\DateTimeImmutable
    {
        if ($this->lastSeenInGsc === null) {
            return $this->lastSeenInBing;
        }
        if ($this->lastSeenInBing === null) {
            return $this->lastSeenInGsc;
        }
        return $this->lastSeenInGsc > $this->lastSeenInBing ? $this->lastSeenInGsc : $this->lastSeenInBing;
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

    public function __toString(): string
    {
        return $this->keyword ?? 'Nouveau mot-cle';
    }
}
