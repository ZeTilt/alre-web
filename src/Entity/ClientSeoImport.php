<?php

namespace App\Entity;

use App\Repository\ClientSeoImportRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ClientSeoImportRepository::class)]
class ClientSeoImport
{
    public const TYPE_BING_API = 'bing_api';
    public const TYPE_GSC_API = 'gsc_api';

    public const STATUS_SUCCESS = 'success';
    public const STATUS_ERROR = 'error';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: ClientSite::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?ClientSite $clientSite = null;

    #[ORM\Column(length: 30)]
    private ?string $type = null;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $periodStart = null;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $periodEnd = null;

    #[ORM\Column(length: 255)]
    private ?string $originalFilename = null;

    #[ORM\Column]
    private int $rowsImported = 0;

    #[ORM\Column]
    private int $rowsSkipped = 0;

    #[ORM\Column]
    private ?\DateTimeImmutable $importedAt = null;

    #[ORM\Column(length: 20)]
    private string $status = self::STATUS_SUCCESS;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $errorMessage = null;

    public function __construct()
    {
        $this->importedAt = new \DateTimeImmutable();
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

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;
        return $this;
    }

    public function getPeriodStart(): ?\DateTimeImmutable
    {
        return $this->periodStart;
    }

    public function setPeriodStart(?\DateTimeImmutable $periodStart): static
    {
        $this->periodStart = $periodStart;
        return $this;
    }

    public function getPeriodEnd(): ?\DateTimeImmutable
    {
        return $this->periodEnd;
    }

    public function setPeriodEnd(?\DateTimeImmutable $periodEnd): static
    {
        $this->periodEnd = $periodEnd;
        return $this;
    }

    public function getOriginalFilename(): ?string
    {
        return $this->originalFilename;
    }

    public function setOriginalFilename(string $originalFilename): static
    {
        $this->originalFilename = $originalFilename;
        return $this;
    }

    public function getRowsImported(): int
    {
        return $this->rowsImported;
    }

    public function setRowsImported(int $rowsImported): static
    {
        $this->rowsImported = $rowsImported;
        return $this;
    }

    public function getRowsSkipped(): int
    {
        return $this->rowsSkipped;
    }

    public function setRowsSkipped(int $rowsSkipped): static
    {
        $this->rowsSkipped = $rowsSkipped;
        return $this;
    }

    public function getImportedAt(): ?\DateTimeImmutable
    {
        return $this->importedAt;
    }

    public function setImportedAt(\DateTimeImmutable $importedAt): static
    {
        $this->importedAt = $importedAt;
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

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function setErrorMessage(?string $errorMessage): static
    {
        $this->errorMessage = $errorMessage;
        return $this;
    }

    public function getTypeLabel(): string
    {
        return match ($this->type) {
            self::TYPE_BING_API => 'Bing API (Auto)',
            self::TYPE_GSC_API => 'GSC API (Auto)',
            default => $this->type ?? '',
        };
    }

    public function getStatusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_SUCCESS => 'Succes',
            self::STATUS_ERROR => 'Erreur',
            default => $this->status,
        };
    }

    public function __toString(): string
    {
        return sprintf('%s - %s', $this->getTypeLabel(), $this->importedAt?->format('d/m/Y H:i') ?? '?');
    }
}
