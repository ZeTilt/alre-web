<?php

namespace App\Entity;

use App\Repository\SeoKeywordRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SeoKeywordRepository::class)]
class SeoKeyword
{
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

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastSyncAt = null;

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
}
