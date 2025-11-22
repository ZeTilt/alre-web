<?php

namespace App\Entity;

use App\Repository\CompanyRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CompanyRepository::class)]
class Company
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 255)]
    private ?string $ownerName = null;

    #[ORM\Column(length: 255)]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $address = null;

    #[ORM\Column(length: 10)]
    private ?string $postalCode = null;

    #[ORM\Column(length: 255)]
    private ?string $city = null;

    #[ORM\Column(length: 20)]
    private ?string $phone = null;

    #[ORM\Column(length: 255)]
    private ?string $email = null;

    #[ORM\Column(length: 20)]
    private ?string $siret = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $website = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $legalStatus = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $legalMentions = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $devisConditions = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $factureConditions = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    // Paramètres auto-entrepreneur pour dashboard
    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $plafondCaAnnuel = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2, nullable: true)]
    private ?string $tauxCotisationsUrssaf = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $objectifCaMensuel = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $objectifCaAnnuel = null;

    #[ORM\Column(nullable: true)]
    private ?int $anneeFiscaleEnCours = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $homePortraitPhoto = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $homePortraitPhotoCredit = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $homePortraitPhotoCreditUrl = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $aboutWidePhoto = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $aboutWidePhotoCredit = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $aboutWidePhotoCreditUrl = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->anneeFiscaleEnCours = (int) date('Y');
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function getOwnerName(): ?string
    {
        return $this->ownerName;
    }

    public function setOwnerName(string $ownerName): static
    {
        $this->ownerName = $ownerName;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(string $address): static
    {
        $this->address = $address;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function getPostalCode(): ?string
    {
        return $this->postalCode;
    }

    public function setPostalCode(string $postalCode): static
    {
        $this->postalCode = $postalCode;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function setCity(string $city): static
    {
        $this->city = $city;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(string $phone): static
    {
        $this->phone = $phone;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function getSiret(): ?string
    {
        return $this->siret;
    }

    public function setSiret(string $siret): static
    {
        $this->siret = $siret;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function getWebsite(): ?string
    {
        return $this->website;
    }

    public function setWebsite(?string $website): static
    {
        $this->website = $website;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function getLegalStatus(): ?string
    {
        return $this->legalStatus;
    }

    public function setLegalStatus(?string $legalStatus): static
    {
        $this->legalStatus = $legalStatus;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function getLegalMentions(): ?string
    {
        return $this->legalMentions;
    }

    public function setLegalMentions(?string $legalMentions): static
    {
        $this->legalMentions = $legalMentions;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function getDevisConditions(): ?string
    {
        return $this->devisConditions;
    }

    public function setDevisConditions(?string $devisConditions): static
    {
        $this->devisConditions = $devisConditions;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function getFactureConditions(): ?string
    {
        return $this->factureConditions;
    }

    public function setFactureConditions(?string $factureConditions): static
    {
        $this->factureConditions = $factureConditions;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?\DateTimeImmutable $createdAt): static
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

    public function getFullAddress(): string
    {
        return $this->address . "\n" . $this->postalCode . ' ' . $this->city;
    }

    public function getPlafondCaAnnuel(): ?string
    {
        return $this->plafondCaAnnuel;
    }

    public function setPlafondCaAnnuel(?string $plafondCaAnnuel): static
    {
        $this->plafondCaAnnuel = $plafondCaAnnuel;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function getTauxCotisationsUrssaf(): ?string
    {
        return $this->tauxCotisationsUrssaf;
    }

    public function setTauxCotisationsUrssaf(?string $tauxCotisationsUrssaf): static
    {
        $this->tauxCotisationsUrssaf = $tauxCotisationsUrssaf;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function getObjectifCaMensuel(): ?string
    {
        return $this->objectifCaMensuel;
    }

    public function setObjectifCaMensuel(?string $objectifCaMensuel): static
    {
        $this->objectifCaMensuel = $objectifCaMensuel;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function getObjectifCaAnnuel(): ?string
    {
        return $this->objectifCaAnnuel;
    }

    public function setObjectifCaAnnuel(?string $objectifCaAnnuel): static
    {
        $this->objectifCaAnnuel = $objectifCaAnnuel;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function getAnneeFiscaleEnCours(): ?int
    {
        return $this->anneeFiscaleEnCours;
    }

    public function setAnneeFiscaleEnCours(?int $anneeFiscaleEnCours): static
    {
        $this->anneeFiscaleEnCours = $anneeFiscaleEnCours;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function getHomePortraitPhoto(): ?string
    {
        return $this->homePortraitPhoto;
    }

    public function setHomePortraitPhoto(?string $homePortraitPhoto): static
    {
        $this->homePortraitPhoto = $homePortraitPhoto;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function getHomePortraitPhotoCredit(): ?string
    {
        return $this->homePortraitPhotoCredit;
    }

    public function setHomePortraitPhotoCredit(?string $homePortraitPhotoCredit): static
    {
        $this->homePortraitPhotoCredit = $homePortraitPhotoCredit;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function getHomePortraitPhotoCreditUrl(): ?string
    {
        return $this->homePortraitPhotoCreditUrl;
    }

    public function setHomePortraitPhotoCreditUrl(?string $homePortraitPhotoCreditUrl): static
    {
        $this->homePortraitPhotoCreditUrl = $homePortraitPhotoCreditUrl;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function getAboutWidePhoto(): ?string
    {
        return $this->aboutWidePhoto;
    }

    public function setAboutWidePhoto(?string $aboutWidePhoto): static
    {
        $this->aboutWidePhoto = $aboutWidePhoto;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function getAboutWidePhotoCredit(): ?string
    {
        return $this->aboutWidePhotoCredit;
    }

    public function setAboutWidePhotoCredit(?string $aboutWidePhotoCredit): static
    {
        $this->aboutWidePhotoCredit = $aboutWidePhotoCredit;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function getAboutWidePhotoCreditUrl(): ?string
    {
        return $this->aboutWidePhotoCreditUrl;
    }

    public function setAboutWidePhotoCreditUrl(?string $aboutWidePhotoCreditUrl): static
    {
        $this->aboutWidePhotoCreditUrl = $aboutWidePhotoCreditUrl;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function __toString(): string
    {
        return $this->name ?: 'Company';
    }
}
