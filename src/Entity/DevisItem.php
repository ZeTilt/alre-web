<?php

namespace App\Entity;

use App\Entity\Traits\DocumentItemTrait;
use App\Repository\DevisItemRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DevisItemRepository::class)]
class DevisItem
{
    use DocumentItemTrait;

    #[ORM\ManyToOne(inversedBy: 'items')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Devis $devis = null;

    public function getDevis(): ?Devis
    {
        return $this->devis;
    }

    public function setDevis(?Devis $devis): static
    {
        $this->devis = $devis;

        return $this;
    }
}
