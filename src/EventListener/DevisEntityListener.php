<?php

namespace App\EventListener;

use App\Entity\Devis;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Event\PostLoadEventArgs;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;

#[AsEntityListener(event: Events::postLoad, entity: Devis::class)]
class DevisEntityListener
{
    public function postLoad(Devis $devis, PostLoadEventArgs $args): void
    {
        // Vérifie et met à jour le statut si nécessaire
        if ($devis->updateStatusBasedOnDeadline()) {
            // Si le statut a changé, on persiste l'entité
            $entityManager = $args->getObjectManager();
            $entityManager->persist($devis);
            $entityManager->flush();
        }
    }
}
