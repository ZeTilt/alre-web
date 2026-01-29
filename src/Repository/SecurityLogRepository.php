<?php

namespace App\Repository;

use App\Entity\SecurityLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SecurityLog>
 */
class SecurityLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SecurityLog::class);
    }

    /**
     * Count errors from an IP within a time window
     */
    public function countRecentErrorsFromIp(string $ipAddress, \DateTimeImmutable $since): int
    {
        return (int) $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->where('s.ipAddress = :ip')
            ->andWhere('s.createdAt >= :since')
            ->andWhere('s.statusCode >= 400')
            ->andWhere('s.statusCode < 500')
            ->setParameter('ip', $ipAddress)
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Get recent error URLs from an IP (for trigger data)
     * @return array<string>
     */
    public function getRecentErrorUrlsFromIp(string $ipAddress, \DateTimeImmutable $since, int $limit = 10): array
    {
        $results = $this->createQueryBuilder('s')
            ->select('DISTINCT s.requestUrl')
            ->where('s.ipAddress = :ip')
            ->andWhere('s.createdAt >= :since')
            ->andWhere('s.statusCode >= 400')
            ->setParameter('ip', $ipAddress)
            ->setParameter('since', $since)
            ->setMaxResults($limit)
            ->getQuery()
            ->getScalarResult();

        return array_column($results, 'requestUrl');
    }

    /**
     * Delete logs older than specified date (RGPD purge)
     */
    public function deleteOlderThan(\DateTimeImmutable $before): int
    {
        return $this->createQueryBuilder('s')
            ->delete()
            ->where('s.createdAt < :before')
            ->setParameter('before', $before)
            ->getQuery()
            ->execute();
    }

    /**
     * Get top IPs by error count in recent period
     * @return array<array{ipAddress: string, errorCount: int, lastSeen: string}>
     */
    public function getTopSuspiciousIps(\DateTimeImmutable $since, int $limit = 20): array
    {
        return $this->createQueryBuilder('s')
            ->select('s.ipAddress, COUNT(s.id) as errorCount, MAX(s.createdAt) as lastSeen')
            ->where('s.createdAt >= :since')
            ->andWhere('s.statusCode >= 400')
            ->groupBy('s.ipAddress')
            ->orderBy('errorCount', 'DESC')
            ->setParameter('since', $since)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find logs by IP address
     * @return SecurityLog[]
     */
    public function findByIpAddress(string $ipAddress, int $limit = 100): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.ipAddress = :ip')
            ->setParameter('ip', $ipAddress)
            ->orderBy('s.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
