<?php

namespace App\Entity;

use App\Repository\ProspectRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProspectRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Prospect
{
    // Status constants
    public const STATUS_IDENTIFIED = 'identified';
    public const STATUS_CONTACTED = 'contacted';
    public const STATUS_IN_DISCUSSION = 'in_discussion';
    public const STATUS_QUOTE_SENT = 'quote_sent';
    public const STATUS_WON = 'won';
    public const STATUS_LOST = 'lost';

    // Source constants
    public const SOURCE_LINKEDIN = 'linkedin';
    public const SOURCE_FACEBOOK = 'facebook';
    public const SOURCE_REFERRAL = 'referral';
    public const SOURCE_COLD_EMAIL = 'cold_email';
    public const SOURCE_WEBSITE = 'website';
    public const SOURCE_EVENT = 'event';
    public const SOURCE_OTHER = 'other';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $companyName = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $website = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $activity = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $city = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $postalCode = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $country = 'France';

    #[ORM\Column(length: 50)]
    private string $source = self::SOURCE_OTHER;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $sourceDetail = null;

    #[ORM\Column(length: 50)]
    private string $status = self::STATUS_IDENTIFIED;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $estimatedValue = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastContactAt = null;

    #[ORM\OneToMany(mappedBy: 'prospect', targetEntity: ProspectContact::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $contacts;

    #[ORM\OneToMany(mappedBy: 'prospect', targetEntity: ProspectInteraction::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['createdAt' => 'DESC'])]
    private Collection $interactions;

    #[ORM\OneToMany(mappedBy: 'prospect', targetEntity: ProspectFollowUp::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['dueAt' => 'ASC'])]
    private Collection $followUps;

    #[ORM\ManyToOne(targetEntity: Client::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Client $convertedClient = null;

    #[ORM\ManyToOne(targetEntity: Devis::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Devis $linkedDevis = null;

    public function __construct()
    {
        $this->contacts = new ArrayCollection();
        $this->interactions = new ArrayCollection();
        $this->followUps = new ArrayCollection();
    }

    public static function getStatusChoices(): array
    {
        return [
            'Identifie' => self::STATUS_IDENTIFIED,
            'Contacte' => self::STATUS_CONTACTED,
            'En discussion' => self::STATUS_IN_DISCUSSION,
            'Devis envoye' => self::STATUS_QUOTE_SENT,
            'Gagne' => self::STATUS_WON,
            'Perdu' => self::STATUS_LOST,
        ];
    }

    public static function getSourceChoices(): array
    {
        return [
            'LinkedIn' => self::SOURCE_LINKEDIN,
            'Facebook' => self::SOURCE_FACEBOOK,
            'Recommandation' => self::SOURCE_REFERRAL,
            'Email froid' => self::SOURCE_COLD_EMAIL,
            'Site web' => self::SOURCE_WEBSITE,
            'Evenement' => self::SOURCE_EVENT,
            'Autre' => self::SOURCE_OTHER,
        ];
    }

    public function getStatusLabel(): string
    {
        return array_search($this->status, self::getStatusChoices()) ?: $this->status;
    }

    public function getSourceLabel(): string
    {
        return array_search($this->source, self::getSourceChoices()) ?: $this->source;
    }

    public function getPrimaryContact(): ?ProspectContact
    {
        foreach ($this->contacts as $contact) {
            if ($contact->isPrimary()) {
                return $contact;
            }
        }
        return $this->contacts->first() ?: null;
    }

    public function getNextFollowUp(): ?ProspectFollowUp
    {
        foreach ($this->followUps as $followUp) {
            if (!$followUp->isCompleted()) {
                return $followUp;
            }
        }
        return null;
    }

    public function getLastInteraction(): ?ProspectInteraction
    {
        return $this->interactions->first() ?: null;
    }

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCompanyName(): ?string
    {
        return $this->companyName;
    }

    public function setCompanyName(string $companyName): static
    {
        $this->companyName = $companyName;
        return $this;
    }

    public function getWebsite(): ?string
    {
        return $this->website;
    }

    public function setWebsite(?string $website): static
    {
        $this->website = $website;
        return $this;
    }

    public function getActivity(): ?string
    {
        return $this->activity;
    }

    public function setActivity(?string $activity): static
    {
        $this->activity = $activity;
        return $this;
    }

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function setCity(?string $city): static
    {
        $this->city = $city;
        return $this;
    }

    public function getPostalCode(): ?string
    {
        return $this->postalCode;
    }

    public function setPostalCode(?string $postalCode): static
    {
        $this->postalCode = $postalCode;
        return $this;
    }

    public function getCountry(): ?string
    {
        return $this->country;
    }

    public function setCountry(?string $country): static
    {
        $this->country = $country;
        return $this;
    }

    public function getSource(): string
    {
        return $this->source;
    }

    public function setSource(string $source): static
    {
        $this->source = $source;
        return $this;
    }

    public function getSourceDetail(): ?string
    {
        return $this->sourceDetail;
    }

    public function setSourceDetail(?string $sourceDetail): static
    {
        $this->sourceDetail = $sourceDetail;
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;
        return $this;
    }

    public function getEstimatedValue(): ?string
    {
        return $this->estimatedValue;
    }

    public function setEstimatedValue(?string $estimatedValue): static
    {
        $this->estimatedValue = $estimatedValue;
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

    public function getLastContactAt(): ?\DateTimeImmutable
    {
        return $this->lastContactAt;
    }

    public function setLastContactAt(?\DateTimeImmutable $lastContactAt): static
    {
        $this->lastContactAt = $lastContactAt;
        return $this;
    }

    /**
     * @return Collection<int, ProspectContact>
     */
    public function getContacts(): Collection
    {
        return $this->contacts;
    }

    public function addContact(ProspectContact $contact): static
    {
        if (!$this->contacts->contains($contact)) {
            $this->contacts->add($contact);
            $contact->setProspect($this);
        }
        return $this;
    }

    public function removeContact(ProspectContact $contact): static
    {
        if ($this->contacts->removeElement($contact)) {
            if ($contact->getProspect() === $this) {
                $contact->setProspect(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, ProspectInteraction>
     */
    public function getInteractions(): Collection
    {
        return $this->interactions;
    }

    public function addInteraction(ProspectInteraction $interaction): static
    {
        if (!$this->interactions->contains($interaction)) {
            $this->interactions->add($interaction);
            $interaction->setProspect($this);
        }
        return $this;
    }

    public function removeInteraction(ProspectInteraction $interaction): static
    {
        if ($this->interactions->removeElement($interaction)) {
            if ($interaction->getProspect() === $this) {
                $interaction->setProspect(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, ProspectFollowUp>
     */
    public function getFollowUps(): Collection
    {
        return $this->followUps;
    }

    public function addFollowUp(ProspectFollowUp $followUp): static
    {
        if (!$this->followUps->contains($followUp)) {
            $this->followUps->add($followUp);
            $followUp->setProspect($this);
        }
        return $this;
    }

    public function removeFollowUp(ProspectFollowUp $followUp): static
    {
        if ($this->followUps->removeElement($followUp)) {
            if ($followUp->getProspect() === $this) {
                $followUp->setProspect(null);
            }
        }
        return $this;
    }

    public function getConvertedClient(): ?Client
    {
        return $this->convertedClient;
    }

    public function setConvertedClient(?Client $convertedClient): static
    {
        $this->convertedClient = $convertedClient;
        return $this;
    }

    public function getLinkedDevis(): ?Devis
    {
        return $this->linkedDevis;
    }

    public function setLinkedDevis(?Devis $linkedDevis): static
    {
        $this->linkedDevis = $linkedDevis;
        return $this;
    }

    public function __toString(): string
    {
        return $this->companyName ?? 'Nouveau prospect';
    }
}
