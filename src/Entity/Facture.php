<?php

namespace App\Entity;

use App\Repository\FactureRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FactureRepository::class)]
class Facture
{
    public const STATUS_BROUILLON = 'brouillon';
    public const STATUS_A_ENVOYER = 'a_envoyer';
    public const STATUS_ENVOYE = 'envoye';
    public const STATUS_RELANCE = 'relance';
    public const STATUS_PAYE = 'paye';
    public const STATUS_EN_RETARD = 'en_retard';
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

    #[ORM\ManyToOne(inversedBy: 'factures')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Client $client = null;

    #[ORM\OneToOne(inversedBy: 'facture', cascade: ['persist', 'remove'])]
    private ?Devis $devis = null;

    #[ORM\Column(length: 50)]
    private string $status = self::STATUS_BROUILLON;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $totalHt = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2)]
    private string $vatRate = '20.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $totalTtc = '0.00';

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private ?\DateTimeImmutable $dateFacture = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private ?\DateTimeImmutable $dateEcheance = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $dateEnvoi = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $datePaiement = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $modePaiement = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $conditions = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\OneToMany(mappedBy: 'facture', targetEntity: FactureItem::class, orphanRemoval: true, cascade: ['persist', 'remove'])]
    private Collection $items;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $createdBy = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->dateFacture = new \DateTimeImmutable();
        $this->dateEcheance = new \DateTimeImmutable('+30 days');
        $this->items = new ArrayCollection();
        $this->number = $this->generateNumber();
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

    public function getClient(): ?Client
    {
        return $this->client;
    }

    public function setClient(?Client $client): static
    {
        $this->client = $client;

        return $this;
    }

    public function getDevis(): ?Devis
    {
        return $this->devis;
    }

    public function setDevis(?Devis $devis): static
    {
        $this->devis = $devis;

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
        
        if ($status === self::STATUS_PAYE && !$this->datePaiement) {
            $this->datePaiement = new \DateTimeImmutable();
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

    public function getDateFacture(): ?\DateTimeImmutable
    {
        return $this->dateFacture;
    }

    public function setDateFacture(\DateTimeImmutable $dateFacture): static
    {
        $this->dateFacture = $dateFacture;

        return $this;
    }

    public function getDateEcheance(): ?\DateTimeImmutable
    {
        return $this->dateEcheance;
    }

    public function setDateEcheance(\DateTimeImmutable $dateEcheance): static
    {
        $this->dateEcheance = $dateEcheance;

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

    public function getDatePaiement(): ?\DateTimeImmutable
    {
        return $this->datePaiement;
    }

    public function setDatePaiement(?\DateTimeImmutable $datePaiement): static
    {
        $this->datePaiement = $datePaiement;

        return $this;
    }

    public function getModePaiement(): ?string
    {
        return $this->modePaiement;
    }

    public function setModePaiement(?string $modePaiement): static
    {
        $this->modePaiement = $modePaiement;

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
     * @return Collection<int, FactureItem>
     */
    public function getItems(): Collection
    {
        return $this->items;
    }

    public function addItem(FactureItem $item): static
    {
        if (!$this->items->contains($item)) {
            $this->items->add($item);
            $item->setFacture($this);
        }

        return $this;
    }

    public function removeItem(FactureItem $item): static
    {
        if ($this->items->removeElement($item)) {
            // set the owning side to null (unless already changed)
            if ($item->getFacture() === $this) {
                $item->setFacture(null);
            }
        }

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
        $totalVat = 0;
        
        foreach ($this->items as $item) {
            $totalHt += $item->getTotalAfterDiscount();
            $totalVat += $item->getVatAmount();
        }
        
        $this->totalHt = number_format($totalHt, 2, '.', '');
        $this->totalTtc = number_format($totalHt + $totalVat, 2, '.', '');
    }

    public function getVatAmount(): string
    {
        $ht = (float) $this->totalHt;
        $ttc = (float) $this->totalTtc;
        return number_format($ttc - $ht, 2, '.', '');
    }

    public function isOverdue(): bool
    {
        if ($this->status === self::STATUS_PAYE) {
            return false;
        }

        return $this->dateEcheance < new \DateTimeImmutable();
    }

    public function getDaysOverdue(): int
    {
        if (!$this->isOverdue()) {
            return 0;
        }

        $now = new \DateTimeImmutable();
        return $now->diff($this->dateEcheance)->days;
    }

    public function canBePaid(): bool
    {
        return in_array($this->status, [
            self::STATUS_ENVOYE,
            self::STATUS_RELANCE,
            self::STATUS_EN_RETARD
        ]);
    }

    public function canBeSent(): bool
    {
        return in_array($this->status, [
            self::STATUS_BROUILLON,
            self::STATUS_A_ENVOYER
        ]);
    }

    public function createFromDevis(Devis $devis): void
    {
        $this->devis = $devis;
        $this->client = $devis->getClient();
        $this->title = $devis->getTitle();
        $this->description = $devis->getDescription();
        $this->totalHt = $devis->getTotalHt();
        $this->vatRate = $devis->getVatRate();
        $this->totalTtc = $devis->getTotalTtc();
        $this->conditions = $devis->getConditions();
        
        // Copy items from devis
        foreach ($devis->getItems() as $devisItem) {
            $factureItem = new FactureItem();
            $factureItem->setDescription($devisItem->getDescription());
            $factureItem->setQuantity($devisItem->getQuantity());
            $factureItem->setUnitPrice($devisItem->getUnitPrice());
            $factureItem->setTotal($devisItem->getTotal());
            $this->addItem($factureItem);
        }
    }

    private function generateNumber(): string
    {
        $date = new \DateTimeImmutable();
        return 'FAC-' . $date->format('Y') . '-' . str_pad(random_int(1, 9999), 4, '0', STR_PAD_LEFT);
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
            'Relancé' => self::STATUS_RELANCE,
            'Payé' => self::STATUS_PAYE,
            'En retard' => self::STATUS_EN_RETARD,
            'Annulé' => self::STATUS_ANNULE,
        ];
    }

    public function getStatusLabel(): string
    {
        $choices = self::getStatusChoices();
        return array_search($this->status, $choices) ?: $this->status;
    }

    public static function getModePaiementChoices(): array
    {
        return [
            'Virement bancaire' => 'virement',
            'Chèque' => 'cheque',
            'Espèces' => 'especes',
            'Carte bancaire' => 'carte',
            'PayPal' => 'paypal',
            'Autre' => 'autre',
        ];
    }
}