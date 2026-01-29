<?php

namespace App\Service;

use App\Entity\BlockedIp;
use App\Entity\SecurityLog;
use App\Repository\BlockedIpRepository;
use App\Repository\SecurityLogRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;

class IpSecurityService
{
    private const CACHE_KEY_BLOCKED_IPS = 'security.blocked_ips';
    private const CACHE_TTL = 300; // 5 minutes

    // Auto-blacklist thresholds
    private const THRESHOLD_ERRORS = 10;
    private const THRESHOLD_WINDOW_MINUTES = 5;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private SecurityLogRepository $securityLogRepository,
        private BlockedIpRepository $blockedIpRepository,
        private CacheItemPoolInterface $cache,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Check if IP is blocked (uses cache for performance)
     */
    public function isIpBlocked(string $ipAddress): bool
    {
        $blockedIps = $this->getBlockedIpsFromCache();
        return in_array($ipAddress, $blockedIps, true);
    }

    /**
     * Get blocked IP entity if exists
     */
    public function getBlockedIp(string $ipAddress): ?BlockedIp
    {
        return $this->blockedIpRepository->findActiveByIp($ipAddress);
    }

    /**
     * Increment hit counter for blocked IP
     */
    public function recordBlockedHit(BlockedIp $blockedIp): void
    {
        $blockedIp->incrementHitCount();
        $this->entityManager->flush();
    }

    /**
     * Log a 4xx error and check for auto-blacklist
     */
    public function logSecurityEvent(
        string $ipAddress,
        string $url,
        string $method,
        int $statusCode,
        ?string $userAgent = null,
        ?string $referer = null,
        ?array $extraData = null
    ): SecurityLog {
        $log = new SecurityLog();
        $log->setIpAddress($ipAddress)
            ->setRequestUrl($url)
            ->setRequestMethod($method)
            ->setStatusCode($statusCode)
            ->setUserAgent($userAgent)
            ->setReferer($referer)
            ->setExtraData($extraData);

        $this->entityManager->persist($log);
        $this->entityManager->flush();

        // Check if auto-blacklist threshold is reached
        $this->checkAutoBlacklist($ipAddress);

        return $log;
    }

    /**
     * Check if IP should be auto-blacklisted
     */
    private function checkAutoBlacklist(string $ipAddress): void
    {
        // Skip if already blocked
        if ($this->blockedIpRepository->isIpBlocked($ipAddress)) {
            return;
        }

        $since = new \DateTimeImmutable(sprintf('-%d minutes', self::THRESHOLD_WINDOW_MINUTES));
        $errorCount = $this->securityLogRepository->countRecentErrorsFromIp($ipAddress, $since);

        if ($errorCount >= self::THRESHOLD_ERRORS) {
            $this->autoBlockIp($ipAddress, $since);
        }
    }

    /**
     * Automatically block an IP (permanent)
     */
    private function autoBlockIp(string $ipAddress, \DateTimeImmutable $since): void
    {
        $triggerUrls = $this->securityLogRepository->getRecentErrorUrlsFromIp($ipAddress, $since);

        $blockedIp = new BlockedIp();
        $blockedIp->setIpAddress($ipAddress)
            ->setReason(BlockedIp::REASON_AUTO_THRESHOLD)
            ->setDescription(sprintf(
                'Auto-bloqué : %d erreurs 4xx en %d minutes',
                self::THRESHOLD_ERRORS,
                self::THRESHOLD_WINDOW_MINUTES
            ))
            ->setIsAutomatic(true)
            ->setExpiresAt(null) // Permanent
            ->setTriggerData(['urls' => $triggerUrls]);

        $this->entityManager->persist($blockedIp);
        $this->entityManager->flush();

        // Invalidate cache
        $this->invalidateCache();

        $this->logger->warning('IP auto-blocked (permanent)', [
            'ip' => $ipAddress,
            'trigger_urls' => $triggerUrls,
        ]);
    }

    /**
     * Manually block an IP
     */
    public function blockIp(
        string $ipAddress,
        string $reason = BlockedIp::REASON_MANUAL,
        ?string $description = null,
        ?int $durationSeconds = null
    ): BlockedIp {
        // Check if already exists
        $existing = $this->blockedIpRepository->findOneBy(['ipAddress' => $ipAddress]);
        if ($existing) {
            $existing->setIsActive(true)
                ->setReason($reason)
                ->setDescription($description)
                ->setExpiresAt($durationSeconds ? new \DateTimeImmutable("+{$durationSeconds} seconds") : null);
            $this->entityManager->flush();
            $this->invalidateCache();
            return $existing;
        }

        $blockedIp = new BlockedIp();
        $blockedIp->setIpAddress($ipAddress)
            ->setReason($reason)
            ->setDescription($description)
            ->setIsAutomatic(false)
            ->setExpiresAt($durationSeconds ? new \DateTimeImmutable("+{$durationSeconds} seconds") : null);

        $this->entityManager->persist($blockedIp);
        $this->entityManager->flush();
        $this->invalidateCache();

        return $blockedIp;
    }

    /**
     * Unblock an IP
     */
    public function unblockIp(string $ipAddress): bool
    {
        $blockedIp = $this->blockedIpRepository->findOneBy(['ipAddress' => $ipAddress]);
        if (!$blockedIp) {
            return false;
        }

        $blockedIp->setIsActive(false);
        $this->entityManager->flush();
        $this->invalidateCache();

        return true;
    }

    /**
     * Get blocked IPs from cache
     * @return array<string>
     */
    private function getBlockedIpsFromCache(): array
    {
        $cacheItem = $this->cache->getItem(self::CACHE_KEY_BLOCKED_IPS);

        if (!$cacheItem->isHit()) {
            $blockedIps = $this->blockedIpRepository->findAllActiveIps();
            $cacheItem->set($blockedIps);
            $cacheItem->expiresAfter(self::CACHE_TTL);
            $this->cache->save($cacheItem);
            return $blockedIps;
        }

        return $cacheItem->get();
    }

    /**
     * Invalidate the blocked IPs cache
     */
    public function invalidateCache(): void
    {
        $this->cache->deleteItem(self::CACHE_KEY_BLOCKED_IPS);
    }

    /**
     * Get the client's real IP address
     */
    public static function getClientIp(Request $request): string
    {
        // Check trusted headers in order of preference
        $trustedHeaders = [
            'CF-Connecting-IP',     // Cloudflare
            'X-Real-IP',            // Nginx proxy
            'X-Forwarded-For',      // Standard proxy header
        ];

        foreach ($trustedHeaders as $header) {
            $ip = $request->headers->get($header);
            if ($ip) {
                // X-Forwarded-For can contain multiple IPs, take the first
                if (str_contains($ip, ',')) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return $request->getClientIp() ?? '0.0.0.0';
    }
}
