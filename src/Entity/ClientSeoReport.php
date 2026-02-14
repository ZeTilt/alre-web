<?php

namespace App\Entity;

use App\Repository\ClientSeoReportRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ClientSeoReportRepository::class)]
class ClientSeoReport
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_SENT = 'sent';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: ClientSite::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?ClientSite $clientSite = null;

    #[ORM\Column(type: 'date_immutable')]
    private ?\DateTimeImmutable $periodStart = null;

    #[ORM\Column(type: 'date_immutable')]
    private ?\DateTimeImmutable $periodEnd = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $actionsHtml = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $nextActionsHtml = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notesHtml = null;

    #[ORM\Column(length: 10, options: ['default' => 'draft'])]
    private string $status = self::STATUS_DRAFT;

    #[ORM\Column]
    private ?\DateTimeImmutable $generatedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $sentAt = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $reportData = null;

    #[ORM\Column(type: 'smallint', options: ['default' => 0])]
    private int $healthScore = 0;

    public function __construct()
    {
        $this->generatedAt = new \DateTimeImmutable();
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

    public function getPeriodStart(): ?\DateTimeImmutable
    {
        return $this->periodStart;
    }

    public function setPeriodStart(\DateTimeImmutable $periodStart): static
    {
        $this->periodStart = $periodStart;
        return $this;
    }

    public function getPeriodEnd(): ?\DateTimeImmutable
    {
        return $this->periodEnd;
    }

    public function setPeriodEnd(\DateTimeImmutable $periodEnd): static
    {
        $this->periodEnd = $periodEnd;
        return $this;
    }

    public function getActionsHtml(): ?string
    {
        return $this->actionsHtml;
    }

    public function setActionsHtml(?string $actionsHtml): static
    {
        $this->actionsHtml = $actionsHtml;
        return $this;
    }

    public function getNextActionsHtml(): ?string
    {
        return $this->nextActionsHtml;
    }

    public function setNextActionsHtml(?string $nextActionsHtml): static
    {
        $this->nextActionsHtml = $nextActionsHtml;
        return $this;
    }

    public function getNotesHtml(): ?string
    {
        return $this->notesHtml;
    }

    public function setNotesHtml(?string $notesHtml): static
    {
        $this->notesHtml = $notesHtml;
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

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isSent(): bool
    {
        return $this->status === self::STATUS_SENT;
    }

    public function getGeneratedAt(): ?\DateTimeImmutable
    {
        return $this->generatedAt;
    }

    public function getSentAt(): ?\DateTimeImmutable
    {
        return $this->sentAt;
    }

    public function setSentAt(?\DateTimeImmutable $sentAt): static
    {
        $this->sentAt = $sentAt;
        return $this;
    }

    public function getReportData(): ?array
    {
        return $this->reportData;
    }

    public function setReportData(?array $reportData): static
    {
        $this->reportData = $reportData;
        return $this;
    }

    public function getHealthScore(): int
    {
        return $this->healthScore;
    }

    public function setHealthScore(int $healthScore): static
    {
        $this->healthScore = max(0, min(100, $healthScore));
        return $this;
    }

    public function markSent(): static
    {
        $this->status = self::STATUS_SENT;
        $this->sentAt = new \DateTimeImmutable();
        return $this;
    }

    public function getPeriodLabel(): string
    {
        $months = ['', 'janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];

        if ($this->periodStart && $this->periodEnd) {
            $startMonth = $months[(int) $this->periodStart->format('n')];
            $endMonth = $months[(int) $this->periodEnd->format('n')];

            if ($startMonth === $endMonth && $this->periodStart->format('Y') === $this->periodEnd->format('Y')) {
                return ucfirst($startMonth) . ' ' . $this->periodStart->format('Y');
            }

            return ucfirst($startMonth) . ' - ' . $endMonth . ' ' . $this->periodEnd->format('Y');
        }

        return '';
    }
}
