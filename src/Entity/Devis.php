<?php

namespace App\Entity;

use App\Repository\DevisRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DevisRepository::class)]
class Devis
{
    public const STATUS_BROUILLON = 'brouillon';
    public const STATUS_A_ENVOYER = 'a_envoyer';
    public const STATUS_ENVOYE = 'envoye';
    public const STATUS_A_RELANCER = 'a_relancer';
    public const STATUS_RELANCE = 'relance';
    public const STATUS_ACCEPTE = 'accepte';
    public const STATUS_REFUSE = 'refuse';
    public const STATUS_EXPIRE = 'expire';
    public const STATUS_ANNULE = 'annule';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50, unique: true, nullable: true)]
    private ?string $number = null;

    #[ORM\Column(length: 255)]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $additionalInfo = null;

    #[ORM\ManyToOne(inversedBy: 'devis')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Client $client = null;

    #[ORM\Column(length: 50)]
    private string $status = self::STATUS_BROUILLON;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $totalHt = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2)]
    private string $vatRate = '20.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $totalTtc = '0.00';

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private ?\DateTimeImmutable $dateCreation = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $dateValidite = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $dateEnvoi = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $dateReponse = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $conditions = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\OneToMany(mappedBy: 'devis', targetEntity: DevisItem::class, orphanRemoval: true, cascade: ['persist', 'remove'])]
    private Collection $items;

    #[ORM\OneToOne(mappedBy: 'devis', cascade: ['persist'])]
    private ?Facture $facture = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $createdBy = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $acompte = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2, nullable: true)]
    private ?string $acomptePercentage = null;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $acompteVerse = true;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->dateCreation = new \DateTimeImmutable();
        $this->dateValidite = new \DateTimeImmutable('+30 days');
        $this->items = new ArrayCollection();
        // Number will be set by the NumberingService
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getAdditionalInfo(): ?string
    {
        return $this->additionalInfo;
    }

    public function setAdditionalInfo(?string $additionalInfo): static
    {
        $this->additionalInfo = $additionalInfo;

        return $this;
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

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        
        // Auto-set dates based on status changes
        if ($status === self::STATUS_ENVOYE && !$this->dateEnvoi) {
            $this->dateEnvoi = new \DateTimeImmutable();
        }
        
        if (in_array($status, [self::STATUS_ACCEPTE, self::STATUS_REFUSE]) && !$this->dateReponse) {
            $this->dateReponse = new \DateTimeImmutable();
        }

        return $this;
    }

    public function getTotalHt(): ?string
    {
        return $this->totalHt;
    }

    public function setTotalHt(string $totalHt): static
    {
        $this->totalHt = $totalHt;
        $this->calculateTotalTtc();

        return $this;
    }

    public function getVatRate(): string
    {
        return $this->vatRate;
    }

    public function setVatRate(string $vatRate): static
    {
        $this->vatRate = $vatRate;
        $this->calculateTotalTtc();

        return $this;
    }

    public function getTotalTtc(): ?string
    {
        return $this->totalTtc;
    }

    public function setTotalTtc(string $totalTtc): static
    {
        $this->totalTtc = $totalTtc;

        return $this;
    }

    public function getDateCreation(): ?\DateTimeImmutable
    {
        return $this->dateCreation;
    }

    public function setDateCreation(\DateTimeImmutable $dateCreation): static
    {
        $this->dateCreation = $dateCreation;

        return $this;
    }

    public function getDateValidite(): ?\DateTimeImmutable
    {
        return $this->dateValidite;
    }

    public function setDateValidite(?\DateTimeImmutable $dateValidite): static
    {
        $this->dateValidite = $dateValidite;

        return $this;
    }

    public function getDateEnvoi(): ?\DateTimeImmutable
    {
        return $this->dateEnvoi;
    }

    public function setDateEnvoi(?\DateTimeImmutable $dateEnvoi): static
    {
        $this->dateEnvoi = $dateEnvoi;

        return $this;
    }

    public function getDateReponse(): ?\DateTimeImmutable
    {
        return $this->dateReponse;
    }

    public function setDateReponse(?\DateTimeImmutable $dateReponse): static
    {
        $this->dateReponse = $dateReponse;

        return $this;
    }

    public function getConditions(): ?string
    {
        return $this->conditions;
    }

    public function setConditions(?string $conditions): static
    {
        $this->conditions = $conditions;

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
     * @return Collection<int, DevisItem>
     */
    public function getItems(): Collection
    {
        return $this->items;
    }

    public function addItem(DevisItem $item): static
    {
        if (!$this->items->contains($item)) {
            $this->items->add($item);
            $item->setDevis($this);
        }

        return $this;
    }

    public function removeItem(DevisItem $item): static
    {
        if ($this->items->removeElement($item)) {
            // set the owning side to null (unless already changed)
            if ($item->getDevis() === $this) {
                $item->setDevis(null);
            }
        }

        return $this;
    }

    public function getFacture(): ?Facture
    {
        return $this->facture;
    }

    public function setFacture(?Facture $facture): static
    {
        // unset the owning side of the relation if necessary
        if ($facture === null && $this->facture !== null) {
            $this->facture->setDevis(null);
        }

        // set the owning side of the relation if necessary
        if ($facture !== null && $facture->getDevis() !== $this) {
            $facture->setDevis($this);
        }

        $this->facture = $facture;

        return $this;
    }

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?User $createdBy): static
    {
        $this->createdBy = $createdBy;

        return $this;
    }

    public function getAcompte(): ?string
    {
        return $this->acompte;
    }

    public function setAcompte(?string $acompte): static
    {
        $this->acompte = $acompte;

        return $this;
    }

    public function getAcomptePercentage(): ?string
    {
        return $this->acomptePercentage;
    }

    public function setAcomptePercentage(?string $acomptePercentage): static
    {
        $this->acomptePercentage = $acomptePercentage;

        return $this;
    }

    public function isAcompteVerse(): bool
    {
        return $this->acompteVerse;
    }

    public function setAcompteVerse(bool $acompteVerse): static
    {
        $this->acompteVerse = $acompteVerse;

        return $this;
    }

    /**
     * Synchronise acompte et acomptePercentage.
     * Si acompte est renseigné, calcule le pourcentage (arrondi à l'entier).
     * Si acomptePercentage est renseigné, calcule le montant (arrondi au centime).
     */
    public function syncAcompteValues(): void
    {
        $totalTtc = (float) $this->totalTtc;

        if ($totalTtc <= 0) {
            return;
        }

        $acompte = $this->acompte !== null ? (float) $this->acompte : null;
        $percentage = $this->acomptePercentage !== null ? (float) $this->acomptePercentage : null;

        // Si un montant est défini mais pas de pourcentage, calculer le pourcentage (arrondi à l'entier)
        if ($acompte !== null && $acompte > 0 && ($percentage === null || $percentage == 0)) {
            $calculatedPercentage = round(($acompte / $totalTtc) * 100);
            $this->acomptePercentage = (string) $calculatedPercentage;
        }
        // Si un pourcentage est défini mais pas de montant, calculer le montant (arrondi au centime)
        elseif ($percentage !== null && $percentage > 0 && ($acompte === null || $acompte == 0)) {
            $calculatedAcompte = round($totalTtc * $percentage / 100, 2);
            $this->acompte = number_format($calculatedAcompte, 2, '.', '');
        }
        // Si les deux sont définis, recalculer le montant à partir du pourcentage (priorité au %)
        elseif ($percentage !== null && $percentage > 0) {
            $calculatedAcompte = round($totalTtc * $percentage / 100, 2);
            $this->acompte = number_format($calculatedAcompte, 2, '.', '');
        }
    }

    // Business logic methods
    
    private function calculateTotalTtc(): void
    {
        $ht = (float) $this->totalHt;
        $vatRate = (float) $this->vatRate;
        $this->totalTtc = number_format($ht * (1 + $vatRate / 100), 2, '.', '');
    }

    public function calculateTotals(): void
    {
        $totalHt = 0;

        foreach ($this->items as $item) {
            $totalHt += $item->getTotalAfterDiscount();
        }

        // Use the document's VAT rate, not item's individual rates
        $vatRate = (float) $this->vatRate;
        $totalVat = $totalHt * ($vatRate / 100);

        $this->totalHt = number_format($totalHt, 2, '.', '');
        $this->totalTtc = number_format($totalHt + $totalVat, 2, '.', '');
    }

    public function getVatAmount(): string
    {
        $ht = (float) $this->totalHt;
        $ttc = (float) $this->totalTtc;
        return number_format($ttc - $ht, 2, '.', '');
    }

    public function isExpired(): bool
    {
        if (!$this->dateValidite) {
            return false;
        }

        return $this->dateValidite < new \DateTimeImmutable() && !in_array($this->status, [
            self::STATUS_ACCEPTE,
            self::STATUS_REFUSE,
            self::STATUS_ANNULE
        ]);
    }

    public function canBeAccepted(): bool
    {
        return in_array($this->status, [
            self::STATUS_ENVOYE,
            self::STATUS_RELANCE
        ]) && !$this->isExpired();
    }

    public function canBeSent(): bool
    {
        return in_array($this->status, [
            self::STATUS_BROUILLON,
            self::STATUS_A_ENVOYER
        ]);
    }

    public function canBeConverted(): bool
    {
        return $this->status === self::STATUS_ACCEPTE && !$this->facture;
    }


    public function __toString(): string
    {
        return $this->number . ' - ' . $this->title;
    }

    public static function getStatusChoices(): array
    {
        return [
            'Brouillon' => self::STATUS_BROUILLON,
            'À envoyer' => self::STATUS_A_ENVOYER,
            'Envoyé' => self::STATUS_ENVOYE,
            'À relancer' => self::STATUS_A_RELANCER,
            'Relancé' => self::STATUS_RELANCE,
            'Accepté' => self::STATUS_ACCEPTE,
            'Refusé' => self::STATUS_REFUSE,
            'Expiré' => self::STATUS_EXPIRE,
            'Annulé' => self::STATUS_ANNULE,
        ];
    }

    public function getStatusLabel(): string
    {
        $choices = self::getStatusChoices();
        return array_search($this->status, $choices) ?: $this->status;
    }

    /**
     * Vérifie et met à jour automatiquement le statut en fonction de la date de validité
     * Retourne true si le statut a été modifié
     */
    public function updateStatusBasedOnDeadline(): bool
    {
        // Ne rien faire si le devis est déjà accepté, refusé, annulé ou expiré
        if (in_array($this->status, [self::STATUS_ACCEPTE, self::STATUS_REFUSE, self::STATUS_ANNULE, self::STATUS_EXPIRE])) {
            return false;
        }

        $now = new \DateTimeImmutable();

        // Si le devis est envoyé ou relancé et que la date de validité est dépassée
        if (in_array($this->status, [self::STATUS_ENVOYE, self::STATUS_RELANCE])) {
            if ($this->dateValidite && $this->dateValidite < $now) {
                $this->status = self::STATUS_A_RELANCER;
                return true;
            }
        }

        return false;
    }
}