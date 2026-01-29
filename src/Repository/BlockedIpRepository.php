<?php

namespace App\Repository;

use App\Entity\BlockedIp;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<BlockedIp>
 */
class BlockedIpRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BlockedIp::class);
    }

    /**
     * Check if an IP is currently blocked
     */
    public function isIpBlocked(string $ipAddress): bool
    {
        $blockedIp = $this->findActiveByIp($ipAddress);
        return $blockedIp !== null && $blockedIp->isEffectivelyBlocked();
    }

    /**
     * Find active block for IP
     */
    public function findActiveByIp(string $ipAddress): ?BlockedIp
    {
        return $this->createQueryBuilder('b')
            ->where('b.ipAddress = :ip')
            ->andWhere('b.isActive = :active')
            ->andWhere('b.expiresAt IS NULL OR b.expiresAt > :now')
            ->setParameter('ip', $ipAddress)
            ->setParameter('active', true)
            ->setParameter('now', new \DateTimeImmutable())
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Get all active blocks (for caching)
     * @return array<string> List of blocked IPs
     */
    public function findAllActiveIps(): array
    {
        $results = $this->createQueryBuilder('b')
            ->select('b.ipAddress')
            ->where('b.isActive = :active')
            ->andWhere('b.expiresAt IS NULL OR b.expiresAt > :now')
            ->setParameter('active', true)
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->getScalarResult();

        return array_column($results, 'ipAddress');
    }

    /**
     * Clean up expired blocks
     */
    public function deactivateExpired(): int
    {
        return $this->createQueryBuilder('b')
            ->update()
            ->set('b.isActive', ':inactive')
            ->where('b.expiresAt IS NOT NULL')
            ->andWhere('b.expiresAt < :now')
            ->andWhere('b.isActive = :active')
            ->setParameter('inactive', false)
            ->setParameter('active', true)
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->execute();
    }

    /**
     * Get statistics for dashboard
     * @return array{totalActive: int, totalAutomatic: int, totalManual: int, blocked24h: int}
     */
    public function getStats(): array
    {
        $last24h = new \DateTimeImmutable('-24 hours');

        $blocked24h = $this->createQueryBuilder('b')
            ->select('SUM(b.hitCount)')
            ->where('b.lastHitAt >= :since')
            ->setParameter('since', $last24h)
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'totalActive' => $this->count(['isActive' => true]),
            'totalAutomatic' => $this->count(['isActive' => true, 'isAutomatic' => true]),
            'totalManual' => $this->count(['isActive' => true, 'isAutomatic' => false]),
            'blocked24h' => (int) ($blocked24h ?? 0),
        ];
    }
}
