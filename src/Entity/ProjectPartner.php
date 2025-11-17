<?php

namespace App\Entity;

use App\Repository\ProjectPartnerRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProjectPartnerRepository::class)]
#[ORM\HasLifecycleCallbacks]
class ProjectPartner
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'projectPartners')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Project $project = null;

    #[ORM\ManyToOne(inversedBy: 'projectPartners')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Partner $partner = null;

    #[ORM\Column(type: Types::JSON)]
    private array $selectedDomains = [];

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProject(): ?Project
    {
        return $this->project;
    }

    public function setProject(?Project $project): static
    {
        $this->project = $project;

        return $this;
    }

    public function getPartner(): ?Partner
    {
        return $this->partner;
    }

    public function setPartner(?Partner $partner): static
    {
        $this->partner = $partner;

        return $this;
    }

    public function getSelectedDomains(): array
    {
        return $this->selectedDomains;
    }

    public function setSelectedDomains(array $selectedDomains): static
    {
        $this->selectedDomains = $selectedDomains;

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

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        $this->createdAt = new \DateTime();
    }

    public function __toString(): string
    {
        $partnerName = $this->partner ? $this->partner->getName() : 'Partenaire';
        $domains = !empty($this->selectedDomains) ? ' (' . implode(', ', $this->selectedDomains) . ')' : '';

        return $partnerName . $domains;
    }
}
