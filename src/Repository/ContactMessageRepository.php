<?php

namespace App\Repository;

use App\Entity\ContactMessage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ContactMessage>
 */
class ContactMessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ContactMessage::class);
    }

    /**
     * Find all unread messages
     */
    public function findUnread(): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.isRead = :read')
            ->andWhere('c.isArchived = :archived')
            ->setParameter('read', false)
            ->setParameter('archived', false)
            ->orderBy('c.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Count unread messages
     */
    public function countUnread(): int
    {
        return $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.isRead = :read')
            ->andWhere('c.isArchived = :archived')
            ->setParameter('read', false)
            ->setParameter('archived', false)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
