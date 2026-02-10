<?php

namespace App\Entity;

use App\Repository\CityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CityRepository::class)]
#[ORM\Table(name: 'city')]
#[UniqueEntity(fields: ['slug'], message: 'Ce slug est déjà utilisé')]
class City
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'Le nom est obligatoire')]
    private ?string $name = null;

    #[ORM\Column(length: 100, unique: true)]
    #[Assert\NotBlank(message: 'Le slug est obligatoire')]
    #[Assert\Regex(
        pattern: '/^[a-z0-9-]+$/',
        message: 'Le slug ne doit contenir que des lettres minuscules, chiffres et tirets'
    )]
    private ?string $slug = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'La région est obligatoire')]
    private ?string $region = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: 'La description est obligatoire')]
    private ?string $description = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $descriptionDeveloppeur = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $descriptionCreation = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $descriptionAgence = null;

    #[ORM\Column(type: Types::JSON)]
    private array $nearby = [];

    #[ORM\Column(type: Types::JSON)]
    private array $keywords = [];

    #[ORM\Column]
    private bool $isActive = true;

    #[ORM\Column]
    private ?int $sortOrder = 0;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;
        return $this;
    }

    public function getRegion(): ?string
    {
        return $this->region;
    }

    public function setRegion(string $region): static
    {
        $this->region = $region;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getDescriptionForService(string $serviceSlug): string
    {
        return match ($serviceSlug) {
            'developpeur-web' => $this->descriptionDeveloppeur ?? $this->description,
            'creation-site-internet' => $this->descriptionCreation ?? $this->description,
            'agence-web' => $this->descriptionAgence ?? $this->description,
            default => $this->description,
        };
    }

    public function getDescriptionDeveloppeur(): ?string
    {
        return $this->descriptionDeveloppeur;
    }

    public function setDescriptionDeveloppeur(?string $descriptionDeveloppeur): static
    {
        $this->descriptionDeveloppeur = $descriptionDeveloppeur;
        return $this;
    }

    public function getDescriptionCreation(): ?string
    {
        return $this->descriptionCreation;
    }

    public function setDescriptionCreation(?string $descriptionCreation): static
    {
        $this->descriptionCreation = $descriptionCreation;
        return $this;
    }

    public function getDescriptionAgence(): ?string
    {
        return $this->descriptionAgence;
    }

    public function setDescriptionAgence(?string $descriptionAgence): static
    {
        $this->descriptionAgence = $descriptionAgence;
        return $this;
    }

    public function getNearby(): array
    {
        return $this->nearby;
    }

    public function setNearby(array $nearby): static
    {
        $this->nearby = $nearby;
        return $this;
    }

    public function getKeywords(): array
    {
        return $this->keywords;
    }

    public function setKeywords(array $keywords): static
    {
        $this->keywords = $keywords;
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

    public function getSortOrder(): ?int
    {
        return $this->sortOrder;
    }

    public function setSortOrder(int $sortOrder): static
    {
        $this->sortOrder = $sortOrder;
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

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function __toString(): string
    {
        return $this->name ?? '';
    }
}
