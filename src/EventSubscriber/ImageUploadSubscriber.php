<?php

namespace App\EventSubscriber;

use App\Entity\Company;
use App\Service\ImageResizerService;
use EasyCorp\Bundle\EasyAdminBundle\Event\AfterEntityPersistedEvent;
use EasyCorp\Bundle\EasyAdminBundle\Event\AfterEntityUpdatedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ImageUploadSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private ImageResizerService $imageResizer
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            AfterEntityPersistedEvent::class => 'onEntitySaved',
            AfterEntityUpdatedEvent::class => 'onEntitySaved',
        ];
    }

    public function onEntitySaved(AfterEntityPersistedEvent|AfterEntityUpdatedEvent $event): void
    {
        $entity = $event->getEntityInstance();

        if ($entity instanceof Company) {
            $this->resizeCompanyImages($entity);
        }

        // Ajouter d'autres entités avec images ici si nécessaire
    }

    private function resizeCompanyImages(Company $company): void
    {
        // Photo de profil - format standard
        if ($company->getProfilePhoto()) {
            $this->imageResizer->resize('uploads/profile/' . $company->getProfilePhoto());
        }

        // Photo portrait pour l'accueil - format portrait
        if ($company->getHomePortraitPhoto()) {
            $this->imageResizer->resizePortrait('uploads/profile/' . $company->getHomePortraitPhoto());
        }

        // Photo plan large pour "À propos" - format paysage
        if ($company->getAboutWidePhoto()) {
            $this->imageResizer->resizeWide('uploads/profile/' . $company->getAboutWidePhoto());
        }
    }
}
