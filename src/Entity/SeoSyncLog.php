<?php

namespace App\Entity;

use App\Repository\SeoSyncLogRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SeoSyncLogRepository::class)]
class SeoSyncLog
{
    public const STATUS_SUCCESS = 'success';
    public const STATUS_ERROR = 'error';
    public const STATUS_PARTIAL = 'partial';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    private string $command;

    #[ORM\Column]
    private \DateTimeImmutable $startedAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $finishedAt = null;

    #[ORM\Column(nullable: true)]
    private ?int $durationMs = null;

    #[ORM\Column(length: 20)]
    private string $status = self::STATUS_SUCCESS;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $details = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $errorMessage = null;

    #[ORM\ManyToOne(targetEntity: ClientSite::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?ClientSite $clientSite = null;

    public function __construct(string $command)
    {
        $this->command = $command;
        $this->startedAt = new \DateTimeImmutable();
    }

    public function finish(array $details, string $status, ?string $errorMessage = null): void
    {
        $this->finishedAt = new \DateTimeImmutable();
        $this->durationMs = (int) (($this->finishedAt->format('U.u') - $this->startedAt->format('U.u')) * 1000);
        $this->details = $details;
        $this->status = $status;
        $this->errorMessage = $errorMessage;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCommand(): string
    {
        return $this->command;
    }

    public function getStartedAt(): \DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function getFinishedAt(): ?\DateTimeImmutable
    {
        return $this->finishedAt;
    }

    public function getDurationMs(): ?int
    {
        return $this->durationMs;
    }

    public function getDurationFormatted(): string
    {
        if ($this->durationMs === null) {
            return '-';
        }

        if ($this->durationMs < 1000) {
            return $this->durationMs . 'ms';
        }

        $seconds = $this->durationMs / 1000;
        if ($seconds < 60) {
            return round($seconds, 1) . 's';
        }

        $minutes = floor($seconds / 60);
        $remainingSeconds = round($seconds - ($minutes * 60));

        return $minutes . 'min ' . $remainingSeconds . 's';
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getDetails(): ?array
    {
        return $this->details;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
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

    public function getCommandLabel(): string
    {
        return match ($this->command) {
            'seo-sync' => 'SEO Own Site',
            'client-seo-auto-sync' => 'SEO Clients',
            default => $this->command,
        };
    }

    public function getStatusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_SUCCESS => 'Succes',
            self::STATUS_ERROR => 'Erreur',
            self::STATUS_PARTIAL => 'Partiel',
            default => $this->status,
        };
    }

    public function __toString(): string
    {
        return sprintf('%s - %s', $this->getCommandLabel(), $this->startedAt->format('d/m/Y H:i'));
    }
}
