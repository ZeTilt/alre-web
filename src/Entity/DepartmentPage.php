<?php

namespace App\Entity;

use App\Repository\DepartmentPageRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: DepartmentPageRepository::class)]
#[ORM\Table(name: 'department_page')]
#[UniqueEntity(fields: ['slug'], message: 'Ce slug est déjà utilisé')]
class DepartmentPage
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

    #[ORM\Column(length: 5)]
    #[Assert\NotBlank(message: 'Le numéro de département est obligatoire')]
    private ?string $number = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: 'La description est obligatoire')]
    private ?string $description = null;

    #[ORM\Column(type: Types::STRING, length: 70, nullable: true)]
    private ?string $titleDeveloppeur = null;

    #[ORM\Column(type: Types::STRING, length: 70, nullable: true)]
    private ?string $titleCreation = null;

    #[ORM\Column(type: Types::STRING, length: 70, nullable: true)]
    private ?string $titleAgence = null;

    #[ORM\Column(type: Types::STRING, length: 70, nullable: true)]
    private ?string $titleReferencement = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $descriptionDeveloppeur = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $descriptionCreation = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $descriptionAgence = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $descriptionReferencement = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $descriptionDeveloppeurLong = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $descriptionCreationLong = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $descriptionAgenceLong = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $descriptionReferencementLong = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastOptimizedAt = null;

    #[ORM\Column]
    private bool $isActive = true;

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

    public function getNumber(): ?string
    {
        return $this->number;
    }

    public function setNumber(string $number): static
    {
        $this->number = $number;
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

    public function getTitleForService(string $serviceSlug): ?string
    {
        return match ($serviceSlug) {
            'developpeur-web' => $this->titleDeveloppeur,
            'creation-site-internet' => $this->titleCreation,
            'agence-web' => $this->titleAgence,
            'referencement-local' => $this->titleReferencement,
            default => null,
        };
    }

    public function getDescriptionForService(string $serviceSlug): string
    {
        return match ($serviceSlug) {
            'developpeur-web' => $this->descriptionDeveloppeur ?? $this->description,
            'creation-site-internet' => $this->descriptionCreation ?? $this->description,
            'agence-web' => $this->descriptionAgence ?? $this->description,
            'referencement-local' => $this->descriptionReferencement ?? $this->description,
            default => $this->description,
        };
    }

    public function getLongDescriptionForService(string $serviceSlug): ?string
    {
        return match ($serviceSlug) {
            'developpeur-web' => $this->descriptionDeveloppeurLong,
            'creation-site-internet' => $this->descriptionCreationLong,
            'agence-web' => $this->descriptionAgenceLong,
            'referencement-local' => $this->descriptionReferencementLong,
            default => null,
        };
    }

    // --- Title getters/setters ---

    public function getTitleDeveloppeur(): ?string
    {
        return $this->titleDeveloppeur;
    }

    public function setTitleDeveloppeur(?string $titleDeveloppeur): static
    {
        $this->titleDeveloppeur = $titleDeveloppeur;
        return $this;
    }

    public function getTitleCreation(): ?string
    {
        return $this->titleCreation;
    }

    public function setTitleCreation(?string $titleCreation): static
    {
        $this->titleCreation = $titleCreation;
        return $this;
    }

    public function getTitleAgence(): ?string
    {
        return $this->titleAgence;
    }

    public function setTitleAgence(?string $titleAgence): static
    {
        $this->titleAgence = $titleAgence;
        return $this;
    }

    public function getTitleReferencement(): ?string
    {
        return $this->titleReferencement;
    }

    public function setTitleReferencement(?string $titleReferencement): static
    {
        $this->titleReferencement = $titleReferencement;
        return $this;
    }

    // --- Description courte getters/setters ---

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

    public function getDescriptionReferencement(): ?string
    {
        return $this->descriptionReferencement;
    }

    public function setDescriptionReferencement(?string $descriptionReferencement): static
    {
        $this->descriptionReferencement = $descriptionReferencement;
        return $this;
    }

    // --- Description longue getters/setters ---

    public function getDescriptionDeveloppeurLong(): ?string
    {
        return $this->descriptionDeveloppeurLong;
    }

    public function setDescriptionDeveloppeurLong(?string $descriptionDeveloppeurLong): static
    {
        $this->descriptionDeveloppeurLong = $descriptionDeveloppeurLong;
        return $this;
    }

    public function getDescriptionCreationLong(): ?string
    {
        return $this->descriptionCreationLong;
    }

    public function setDescriptionCreationLong(?string $descriptionCreationLong): static
    {
        $this->descriptionCreationLong = $descriptionCreationLong;
        return $this;
    }

    public function getDescriptionAgenceLong(): ?string
    {
        return $this->descriptionAgenceLong;
    }

    public function setDescriptionAgenceLong(?string $descriptionAgenceLong): static
    {
        $this->descriptionAgenceLong = $descriptionAgenceLong;
        return $this;
    }

    public function getDescriptionReferencementLong(): ?string
    {
        return $this->descriptionReferencementLong;
    }

    public function setDescriptionReferencementLong(?string $descriptionReferencementLong): static
    {
        $this->descriptionReferencementLong = $descriptionReferencementLong;
        return $this;
    }

    // --- Metadata ---

    public function getLastOptimizedAt(): ?\DateTimeImmutable
    {
        return $this->lastOptimizedAt;
    }

    public function setLastOptimizedAt(?\DateTimeImmutable $lastOptimizedAt): static
    {
        $this->lastOptimizedAt = $lastOptimizedAt;
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

    public function getLastOptimizedLabel(): ?string
    {
        return null;
    }

    public function getPageUrls(): ?string
    {
        return null;
    }

    public function __toString(): string
    {
        return $this->name ?? '';
    }
}
