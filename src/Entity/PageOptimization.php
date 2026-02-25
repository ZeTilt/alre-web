<?php

namespace App\Entity;

use App\Repository\PageOptimizationRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: PageOptimizationRepository::class)]
#[ORM\Table(name: 'page_optimization')]
#[UniqueEntity(fields: ['url'], message: 'Cette URL est déjà suivie')]
class PageOptimization
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, unique: true)]
    #[Assert\NotBlank(message: 'L\'URL est obligatoire')]
    private ?string $url = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'Le label est obligatoire')]
    private ?string $label = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastOptimizedAt = null;

    #[ORM\Column]
    private bool $isActive = true;

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

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(string $url): static
    {
        $this->url = $url;
        return $this;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(string $label): static
    {
        $this->label = $label;
        return $this;
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

    public function getLastOptimizedLabel(): ?string
    {
        return null;
    }

    public function getKeywordsList(): ?string
    {
        return null;
    }

    public function getOptimizeAction(): ?string
    {
        return null;
    }

    public function __toString(): string
    {
        return $this->label ?? $this->url ?? '';
    }
}
