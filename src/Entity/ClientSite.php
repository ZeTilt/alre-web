<?php

namespace App\Entity;

use App\Repository\ClientSiteRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ClientSiteRepository::class)]
class ClientSite
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Client::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Client $client = null;

    #[ORM\Column(length: 500)]
    private ?string $url = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column]
    private bool $isActive = true;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    /**
     * @var Collection<int, ClientSeoImport>
     */
    #[ORM\OneToMany(targetEntity: ClientSeoImport::class, mappedBy: 'clientSite', orphanRemoval: true)]
    #[ORM\OrderBy(['importedAt' => 'DESC'])]
    private Collection $imports;

    /**
     * @var Collection<int, ClientSeoKeyword>
     */
    #[ORM\OneToMany(targetEntity: ClientSeoKeyword::class, mappedBy: 'clientSite', orphanRemoval: true)]
    private Collection $keywords;

    /**
     * @var Collection<int, ClientSeoDailyTotal>
     */
    #[ORM\OneToMany(targetEntity: ClientSeoDailyTotal::class, mappedBy: 'clientSite', orphanRemoval: true)]
    private Collection $dailyTotals;

    /**
     * @var Collection<int, ClientSeoPage>
     */
    #[ORM\OneToMany(targetEntity: ClientSeoPage::class, mappedBy: 'clientSite', orphanRemoval: true)]
    private Collection $pages;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->imports = new ArrayCollection();
        $this->keywords = new ArrayCollection();
        $this->dailyTotals = new ArrayCollection();
        $this->pages = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getClient(): ?Client
    {
        return $this->client;
    }

    public function setClient(?Client $client): static
    {
        $this->client = $client;
        return $this;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(string $url): static
    {
        $this->url = $url;
        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
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

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    /**
     * @return Collection<int, ClientSeoImport>
     */
    public function getImports(): Collection
    {
        return $this->imports;
    }

    /**
     * @return Collection<int, ClientSeoKeyword>
     */
    public function getKeywords(): Collection
    {
        return $this->keywords;
    }

    /**
     * @return Collection<int, ClientSeoDailyTotal>
     */
    public function getDailyTotals(): Collection
    {
        return $this->dailyTotals;
    }

    /**
     * @return Collection<int, ClientSeoPage>
     */
    public function getPages(): Collection
    {
        return $this->pages;
    }

    public function __toString(): string
    {
        return $this->name ?? 'Nouveau site';
    }
}
