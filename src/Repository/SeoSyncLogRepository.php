<?php

namespace App\Repository;

use App\Entity\SeoSyncLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SeoSyncLog>
 */
class SeoSyncLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SeoSyncLog::class);
    }

    /**
     * @return SeoSyncLog[]
     */
    public function findLatest(int $limit = 20): array
    {
        return $this->createQueryBuilder('l')
            ->orderBy('l.startedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return SeoSyncLog[]
     */
    public function findLatestByCommand(string $command, int $limit = 10): array
    {
        return $this->createQueryBuilder('l')
            ->where('l.command = :command')
            ->setParameter('command', $command)
            ->orderBy('l.startedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
