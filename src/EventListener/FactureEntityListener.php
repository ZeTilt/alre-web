<?php

namespace App\EventListener;

use App\Entity\Facture;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Event\PostLoadEventArgs;
use Doctrine\ORM\Events;

#[AsEntityListener(event: Events::postLoad, entity: Facture::class)]
class FactureEntityListener
{
    public function postLoad(Facture $facture, PostLoadEventArgs $args): void
    {
        // Vérifie et met à jour le statut si nécessaire
        if ($facture->updateStatusBasedOnDeadline()) {
            // Si le statut a changé, on persiste l'entité
            $entityManager = $args->getObjectManager();
            $entityManager->persist($facture);
            $entityManager->flush();
        }
    }
}
