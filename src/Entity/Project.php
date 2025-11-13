<?php

namespace App\Entity;

use App\Repository\ProjectRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProjectRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Project
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $title = null;

    #[ORM\Column(length: 255, unique: true)]
    private ?string $slug = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $clientName = null;

    #[ORM\Column(length: 50)]
    private ?string $category = null; // vitrine, ecommerce, sur-mesure

    #[ORM\Column(type: Types::TEXT)]
    private ?string $shortDescription = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $fullDescription = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private array $technologies = [];

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private array $partners = [];

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $context = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $solutions = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $results = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $imageFilename = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $projectUrl = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $completionDate = null;

    #[ORM\Column]
    private ?bool $featured = false;

    #[ORM\Column]
    private ?bool $isPublished = true;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $updatedAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;

        return $this;
    }

    public function getClientName(): ?string
    {
        return $this->clientName;
    }

    public function setClientName(?string $clientName): static
    {
        $this->clientName = $clientName;

        return $this;
    }

    public function getCategory(): ?string
    {
        return $this->category;
    }

    public function setCategory(string $category): static
    {
        $this->category = $category;

        return $this;
    }

    public function getShortDescription(): ?string
    {
        return $this->shortDescription;
    }

    public function setShortDescription(string $shortDescription): static
    {
        $this->shortDescription = $shortDescription;

        return $this;
    }

    public function getFullDescription(): ?string
    {
        return $this->fullDescription;
    }

    public function setFullDescription(?string $fullDescription): static
    {
        $this->fullDescription = $fullDescription;

        return $this;
    }

    public function getTechnologies(): array
    {
        return $this->technologies;
    }

    public function setTechnologies(?array $technologies): static
    {
        $this->technologies = $technologies ?? [];

        return $this;
    }

    public function getPartners(): array
    {
        return $this->partners;
    }

    public function setPartners(?array $partners): static
    {
        $this->partners = $partners ?? [];

        return $this;
    }

    public function getContext(): ?string
    {
        return $this->context;
    }

    public function setContext(?string $context): static
    {
        $this->context = $context;

        return $this;
    }

    public function getSolutions(): ?string
    {
        return $this->solutions;
    }

    public function setSolutions(?string $solutions): static
    {
        $this->solutions = $solutions;

        return $this;
    }

    public function getResults(): ?string
    {
        return $this->results;
    }

    public function setResults(?string $results): static
    {
        $this->results = $results;

        return $this;
    }

    public function getImageFilename(): ?string
    {
        return $this->imageFilename;
    }

    public function setImageFilename(?string $imageFilename): static
    {
        $this->imageFilename = $imageFilename;

        return $this;
    }

    public function getProjectUrl(): ?string
    {
        return $this->projectUrl;
    }

    public function setProjectUrl(?string $projectUrl): static
    {
        $this->projectUrl = $projectUrl;

        return $this;
    }

    public function getCompletionDate(): ?\DateTimeInterface
    {
        return $this->completionDate;
    }

    public function setCompletionDate(?\DateTimeInterface $completionDate): static
    {
        $this->completionDate = $completionDate;

        return $this;
    }

    public function isFeatured(): ?bool
    {
        return $this->featured;
    }

    public function setFeatured(bool $featured): static
    {
        $this->featured = $featured;

        return $this;
    }

    public function isPublished(): ?bool
    {
        return $this->isPublished;
    }

    public function setIsPublished(bool $isPublished): static
    {
        $this->isPublished = $isPublished;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeInterface $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();

        // Générer le slug automatiquement si non fourni
        if (!$this->slug && $this->title) {
            $this->slug = $this->generateSlug($this->title);
        }
    }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTime();

        // Regénérer le slug si le titre a changé et que le slug est vide
        if (!$this->slug && $this->title) {
            $this->slug = $this->generateSlug($this->title);
        }
    }

    /**
     * Génère un slug à partir d'une chaîne de caractères
     */
    private function generateSlug(string $string): string
    {
        // Remplacer les caractères accentués
        $string = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $string);
        // Tout en minuscule
        $string = strtolower($string);
        // Remplacer les caractères non alphanumériques par des tirets
        $string = preg_replace('/[^a-z0-9]+/', '-', $string);
        // Supprimer les tirets en début et fin
        $string = trim($string, '-');

        return $string;
    }

    public function __toString(): string
    {
        return $this->title ?? 'Nouveau projet';
    }
}
