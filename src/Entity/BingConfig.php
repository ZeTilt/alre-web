<?php

namespace App\Entity;

use App\Repository\BingConfigRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BingConfigRepository::class)]
class BingConfig
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $siteUrl = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $gscSiteUrl = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $apiKey = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $indexNowKey = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSiteUrl(): ?string
    {
        return $this->siteUrl;
    }

    public function setSiteUrl(?string $siteUrl): static
    {
        $this->siteUrl = $siteUrl;
        return $this;
    }

    public function getGscSiteUrl(): ?string
    {
        return $this->gscSiteUrl;
    }

    public function setGscSiteUrl(?string $gscSiteUrl): static
    {
        $this->gscSiteUrl = $gscSiteUrl;
        return $this;
    }

    public function getApiKey(): ?string
    {
        return $this->apiKey;
    }

    public function setApiKey(?string $apiKey): static
    {
        $this->apiKey = $apiKey;
        return $this;
    }

    public function getIndexNowKey(): ?string
    {
        return $this->indexNowKey;
    }

    public function setIndexNowKey(?string $indexNowKey): static
    {
        $this->indexNowKey = $indexNowKey;
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
}
