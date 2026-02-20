<?php

namespace App\Entity;

use App\Repository\ClientBingKeywordRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ClientBingKeywordRepository::class)]
#[ORM\UniqueConstraint(name: 'unique_client_bing_keyword', columns: ['client_site_id', 'keyword'])]
class ClientBingKeyword
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: ClientSite::class, inversedBy: 'bingKeywords')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?ClientSite $clientSite = null;

    #[ORM\Column(length: 255)]
    private ?string $keyword = null;

    #[ORM\Column]
    private bool $isActive = true;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    /**
     * @var Collection<int, ClientBingPosition>
     */
    #[ORM\OneToMany(targetEntity: ClientBingPosition::class, mappedBy: 'clientBingKeyword', orphanRemoval: true)]
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

    /**
     * @return Collection<int, ClientBingPosition>
     */
    public function getPositions(): Collection
    {
        return $this->positions;
    }

    public function getLatestPosition(): ?ClientBingPosition
    {
        if ($this->positions->isEmpty()) {
            return null;
        }
        return $this->positions->first();
    }

    public function __toString(): string
    {
        return $this->keyword ?? 'Nouveau mot-cle Bing';
    }
}
