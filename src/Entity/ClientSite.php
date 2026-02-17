<?php

namespace App\Entity;

use App\Repository\ClientSiteRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ClientSiteRepository::class)]
class ClientSite
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Client::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Client $client = null;

    #[ORM\Column(length: 500)]
    private ?string $url = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column]
    private bool $isActive = true;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    /**
     * @var Collection<int, ClientSeoImport>
     */
    #[ORM\OneToMany(targetEntity: ClientSeoImport::class, mappedBy: 'clientSite', orphanRemoval: true)]
    #[ORM\OrderBy(['importedAt' => 'DESC'])]
    private Collection $imports;

    /**
     * @var Collection<int, ClientSeoKeyword>
     */
    #[ORM\OneToMany(targetEntity: ClientSeoKeyword::class, mappedBy: 'clientSite', orphanRemoval: true)]
    private Collection $keywords;

    /**
     * @var Collection<int, ClientSeoDailyTotal>
     */
    #[ORM\OneToMany(targetEntity: ClientSeoDailyTotal::class, mappedBy: 'clientSite', orphanRemoval: true)]
    private Collection $dailyTotals;

    /**
     * @var Collection<int, ClientSeoPage>
     */
    #[ORM\OneToMany(targetEntity: ClientSeoPage::class, mappedBy: 'clientSite', orphanRemoval: true)]
    private Collection $pages;

    // Planning import GSC
    #[ORM\Column(type: 'smallint', nullable: true)]
    private ?int $importDay = null; // 1=Lun, 2=Mar, ..., 5=Ven

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $importSlot = null; // 'morning' | 'afternoon'

    // Planning compte rendu
    #[ORM\Column(type: 'smallint', nullable: true)]
    private ?int $reportWeekOfMonth = null; // 1-4

    #[ORM\Column(type: 'smallint', nullable: true)]
    private ?int $reportDayOfWeek = null; // 1=Lun, ..., 5=Ven

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $reportSlot = null; // 'morning' | 'afternoon'

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->imports = new ArrayCollection();
        $this->keywords = new ArrayCollection();
        $this->dailyTotals = new ArrayCollection();
        $this->pages = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(string $url): static
    {
        $this->url = $url;
        return $this;
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

    /**
     * @return Collection<int, ClientSeoImport>
     */
    public function getImports(): Collection
    {
        return $this->imports;
    }

    /**
     * @return Collection<int, ClientSeoKeyword>
     */
    public function getKeywords(): Collection
    {
        return $this->keywords;
    }

    /**
     * @return Collection<int, ClientSeoDailyTotal>
     */
    public function getDailyTotals(): Collection
    {
        return $this->dailyTotals;
    }

    /**
     * @return Collection<int, ClientSeoPage>
     */
    public function getPages(): Collection
    {
        return $this->pages;
    }

    // --- Scheduling getters/setters ---

    public function getImportDay(): ?int
    {
        return $this->importDay;
    }

    public function setImportDay(?int $importDay): static
    {
        $this->importDay = $importDay;
        return $this;
    }

    public function getImportSlot(): ?string
    {
        return $this->importSlot;
    }

    public function setImportSlot(?string $importSlot): static
    {
        $this->importSlot = $importSlot;
        return $this;
    }

    public function getReportWeekOfMonth(): ?int
    {
        return $this->reportWeekOfMonth;
    }

    public function setReportWeekOfMonth(?int $reportWeekOfMonth): static
    {
        $this->reportWeekOfMonth = $reportWeekOfMonth;
        return $this;
    }

    public function getReportDayOfWeek(): ?int
    {
        return $this->reportDayOfWeek;
    }

    public function setReportDayOfWeek(?int $reportDayOfWeek): static
    {
        $this->reportDayOfWeek = $reportDayOfWeek;
        return $this;
    }

    public function getReportSlot(): ?string
    {
        return $this->reportSlot;
    }

    public function setReportSlot(?string $reportSlot): static
    {
        $this->reportSlot = $reportSlot;
        return $this;
    }

    // --- Scheduling helpers ---

    public function getNextReportDate(): ?\DateTimeImmutable
    {
        if ($this->reportWeekOfMonth === null || $this->reportDayOfWeek === null) {
            return null;
        }

        $now = new \DateTimeImmutable('today');

        // Try current month first
        $date = $this->getNthWeekdayOfMonth($this->reportWeekOfMonth, $this->reportDayOfWeek, $now);
        if ($date !== null && $date >= $now) {
            return $date;
        }

        // Otherwise next month
        $nextMonth = $now->modify('first day of next month');
        return $this->getNthWeekdayOfMonth($this->reportWeekOfMonth, $this->reportDayOfWeek, $nextMonth);
    }

    private function getNthWeekdayOfMonth(int $n, int $dayOfWeek, \DateTimeImmutable $reference): ?\DateTimeImmutable
    {
        $firstOfMonth = $reference->modify('first day of this month')->setTime(0, 0, 0);
        $firstDow = (int) $firstOfMonth->format('N');

        $diff = $dayOfWeek - $firstDow;
        if ($diff < 0) {
            $diff += 7;
        }

        $firstOccurrence = $firstOfMonth->modify("+{$diff} days");
        $target = $firstOccurrence->modify('+' . ($n - 1) . ' weeks');

        // Verify still in same month
        if ($target->format('m') !== $firstOfMonth->format('m')) {
            return null;
        }

        return $target;
    }

    public function getNextImportDate(): ?\DateTimeImmutable
    {
        if ($this->importDay === null) {
            return null;
        }

        $daysMap = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'];
        $dayName = $daysMap[$this->importDay];

        $imports = $this->getImports();
        if ($imports->isEmpty()) {
            // No import yet: next occurrence of importDay from today
            $today = new \DateTimeImmutable('today');
            $todayDow = (int) $today->format('N');

            return $todayDow === $this->importDay ? $today : $today->modify("next {$dayName}");
        }

        $lastImportDate = \DateTimeImmutable::createFromInterface($imports->first()->getImportedAt());
        $nextDue = $lastImportDate->modify("next {$dayName}");

        // If next due date is less than 5 days after import, skip to the week after
        if ($lastImportDate->diff($nextDue)->days < 5) {
            $nextDue = $nextDue->modify('+7 days');
        }

        return $nextDue;
    }

    public function isImportDue(): bool
    {
        $nextDue = $this->getNextImportDate();
        if ($nextDue === null) {
            return false;
        }

        $imports = $this->getImports();
        if ($imports->isEmpty()) {
            return true;
        }

        return new \DateTimeImmutable('today') >= $nextDue;
    }

    public function isReportDue(): bool
    {
        $nextReport = $this->getNextReportDate();
        if ($nextReport === null) {
            return false;
        }

        $today = new \DateTimeImmutable('today');

        return $today->format('Y-m-d') === $nextReport->format('Y-m-d');
    }

    public function __toString(): string
    {
        return $this->name ?? 'Nouveau site';
    }
}
