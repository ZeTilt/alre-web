<?php

namespace App\Entity;

use App\Repository\GoogleReviewRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: GoogleReviewRepository::class)]
#[ORM\Table(name: 'google_review')]
#[ORM\HasLifecycleCallbacks]
class GoogleReview
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, unique: true)]
    private ?string $googleReviewId = null;

    #[ORM\Column(length: 255)]
    private ?string $authorName = null;

    #[ORM\Column(type: Types::SMALLINT)]
    private ?int $rating = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $comment = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $reviewDate = null;

    #[ORM\Column]
    private bool $isApproved = false;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $rejectedAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

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

    public function getGoogleReviewId(): ?string
    {
        return $this->googleReviewId;
    }

    public function setGoogleReviewId(string $googleReviewId): static
    {
        $this->googleReviewId = $googleReviewId;

        return $this;
    }

    public function getAuthorName(): ?string
    {
        return $this->authorName;
    }

    public function setAuthorName(string $authorName): static
    {
        $this->authorName = $authorName;

        return $this;
    }

    public function getRating(): ?int
    {
        return $this->rating;
    }

    public function setRating(int $rating): static
    {
        $this->rating = max(1, min(5, $rating));

        return $this;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): static
    {
        $this->comment = $comment;

        return $this;
    }

    public function getReviewDate(): ?\DateTimeImmutable
    {
        return $this->reviewDate;
    }

    public function setReviewDate(\DateTimeImmutable $reviewDate): static
    {
        $this->reviewDate = $reviewDate;

        return $this;
    }

    public function isApproved(): bool
    {
        return $this->isApproved;
    }

    public function setIsApproved(bool $isApproved): static
    {
        $this->isApproved = $isApproved;

        // Si on approuve, on efface la date de rejet
        if ($isApproved) {
            $this->rejectedAt = null;
        }

        return $this;
    }

    public function getRejectedAt(): ?\DateTimeImmutable
    {
        return $this->rejectedAt;
    }

    public function setRejectedAt(?\DateTimeImmutable $rejectedAt): static
    {
        $this->rejectedAt = $rejectedAt;

        return $this;
    }

    /**
     * Marque l'avis comme rejeté.
     */
    public function reject(): static
    {
        $this->isApproved = false;
        $this->rejectedAt = new \DateTimeImmutable();

        return $this;
    }

    /**
     * Vérifie si l'avis a été rejeté.
     */
    public function isRejected(): bool
    {
        return $this->rejectedAt !== null;
    }

    /**
     * Vérifie si l'avis est en attente de modération.
     */
    public function isPending(): bool
    {
        return !$this->isApproved && $this->rejectedAt === null;
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

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    /**
     * Retourne la note sous forme de string (pour EasyAdmin).
     */
    public function getRatingStars(): string
    {
        return (string) $this->rating;
    }

    /**
     * Retourne un extrait du commentaire.
     */
    public function getCommentExcerpt(int $maxLength = 100): string
    {
        if (!$this->comment) {
            return '';
        }

        if (mb_strlen($this->comment) <= $maxLength) {
            return $this->comment;
        }

        return mb_substr($this->comment, 0, $maxLength) . '...';
    }
}
