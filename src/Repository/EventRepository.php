<?php

namespace App\Repository;

use App\Entity\Event;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Event>
 */
class EventRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Event::class);
    }

    /**
     * Récupère les événements entre deux dates (pour le calendrier)
     */
    public function findByDateRange(\DateTimeInterface $start, \DateTimeInterface $end): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.startAt >= :start')
            ->andWhere('e.startAt <= :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->orderBy('e.startAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère les événements à venir (7 prochains jours)
     */
    public function findUpcoming(int $days = 7): array
    {
        $now = new \DateTimeImmutable();
        $end = $now->modify("+{$days} days");

        return $this->createQueryBuilder('e')
            ->andWhere('e.startAt >= :now')
            ->andWhere('e.startAt <= :end')
            ->setParameter('now', $now)
            ->setParameter('end', $end)
            ->orderBy('e.startAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère les événements d'aujourd'hui
     */
    public function findToday(): array
    {
        $today = new \DateTimeImmutable('today');
        $tomorrow = $today->modify('+1 day');

        return $this->createQueryBuilder('e')
            ->andWhere('e.startAt >= :today')
            ->andWhere('e.startAt < :tomorrow')
            ->setParameter('today', $today)
            ->setParameter('tomorrow', $tomorrow)
            ->orderBy('e.startAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère les événements par type
     */
    public function findByType(string $type): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.type = :type')
            ->setParameter('type', $type)
            ->orderBy('e.startAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte les événements par type pour une période
     */
    public function countByTypeForPeriod(\DateTimeInterface $start, \DateTimeInterface $end): array
    {
        $qb = $this->createQueryBuilder('e')
            ->select('e.type, COUNT(e.id) as count')
            ->andWhere('e.startAt >= :start')
            ->andWhere('e.startAt <= :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->groupBy('e.type');

        $results = $qb->getQuery()->getResult();

        $counts = [];
        foreach ($results as $row) {
            $counts[$row['type']] = $row['count'];
        }

        return $counts;
    }

    /**
     * Trouve les événements qui commencent dans une fenêtre de temps
     * Utilisé pour les rappels de notifications push
     */
    public function findEventsStartingBetween(
        \DateTimeInterface $start,
        \DateTimeInterface $end
    ): array {
        return $this->createQueryBuilder('e')
            ->andWhere('e.startAt >= :start')
            ->andWhere('e.startAt <= :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->orderBy('e.startAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
