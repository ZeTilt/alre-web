<?php

namespace App\Repository;

use App\Entity\ClientSeoKeyword;
use App\Entity\ClientSite;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ClientSeoKeyword>
 */
class ClientSeoKeywordRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ClientSeoKeyword::class);
    }

    /**
     * @return ClientSeoKeyword[]
     */
    public function findByClientSite(ClientSite $clientSite): array
    {
        return $this->createQueryBuilder('k')
            ->where('k.clientSite = :site')
            ->andWhere('k.isActive = :active')
            ->setParameter('site', $clientSite)
            ->setParameter('active', true)
            ->orderBy('k.keyword', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findByKeywordAndSite(string $keyword, ClientSite $clientSite): ?ClientSeoKeyword
    {
        return $this->createQueryBuilder('k')
            ->where('LOWER(k.keyword) = LOWER(:keyword)')
            ->andWhere('k.clientSite = :site')
            ->setParameter('keyword', $keyword)
            ->setParameter('site', $clientSite)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return ClientSeoKeyword[]
     */
    public function findAllWithLatestPosition(ClientSite $clientSite): array
    {
        return $this->createQueryBuilder('k')
            ->leftJoin('k.positions', 'p')
            ->addSelect('p')
            ->where('k.clientSite = :site')
            ->setParameter('site', $clientSite)
            ->orderBy('k.keyword', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return array<array{relevanceLevel: string, cnt: int}>
     */
    public function getActiveCount(ClientSite $clientSite): int
    {
        return (int) $this->createQueryBuilder('k')
            ->select('COUNT(k.id)')
            ->where('k.clientSite = :site')
            ->andWhere('k.isActive = :active')
            ->setParameter('site', $clientSite)
            ->setParameter('active', true)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return array<array{id: int, firstSeen: string}>
     */
    public function getKeywordFirstAppearances(ClientSite $clientSite): array
    {
        return $this->createQueryBuilder('k')
            ->select('k.id, SUBSTRING(MIN(p.date), 1, 10) as firstSeen')
            ->join('k.positions', 'p')
            ->where('k.clientSite = :site')
            ->setParameter('site', $clientSite)
            ->groupBy('k.id')
            ->orderBy('firstSeen', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Retourne la date de premiere apparition pour TOUS les mots-cles (actifs + inactifs),
     * avec le score de pertinence.
     *
     * @return array<array{id: int, relevanceScore: int, firstSeen: string}>
     */
    public function getKeywordFirstAppearancesAll(ClientSite $clientSite): array
    {
        return $this->createQueryBuilder('k')
            ->select('k.id, k.relevanceScore, SUBSTRING(MIN(p.date), 1, 10) as firstSeen')
            ->join('k.positions', 'p')
            ->where('k.clientSite = :site')
            ->setParameter('site', $clientSite)
            ->groupBy('k.id, k.relevanceScore')
            ->orderBy('firstSeen', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Retourne les mots-cles desactives avec leur date de desactivation.
     *
     * @return array<array{id: int, deactivatedDate: string}>
     */
    public function getKeywordDeactivations(ClientSite $clientSite): array
    {
        return $this->createQueryBuilder('k')
            ->select('k.id, SUBSTRING(k.deactivatedAt, 1, 10) as deactivatedDate')
            ->where('k.clientSite = :site')
            ->andWhere('k.isActive = :active')
            ->andWhere('k.deactivatedAt IS NOT NULL')
            ->setParameter('site', $clientSite)
            ->setParameter('active', false)
            ->getQuery()
            ->getResult();
    }

    /**
     * Retourne le nombre de mots-cles inactifs pour un site.
     */
    public function countInactive(ClientSite $clientSite): int
    {
        return (int) $this->createQueryBuilder('k')
            ->select('COUNT(k.id)')
            ->where('k.clientSite = :site')
            ->andWhere('k.isActive = :active')
            ->setParameter('site', $clientSite)
            ->setParameter('active', false)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Retourne le decompte des mots-cles actifs par score de pertinence (0-5).
     *
     * @return array<array{relevanceScore: int, cnt: int}>
     */
    public function getRelevanceCounts(ClientSite $clientSite): array
    {
        return $this->createQueryBuilder('k')
            ->select('k.relevanceScore, COUNT(k.id) as cnt')
            ->where('k.clientSite = :site')
            ->andWhere('k.isActive = :active')
            ->setParameter('site', $clientSite)
            ->setParameter('active', true)
            ->groupBy('k.relevanceScore')
            ->getQuery()
            ->getResult();
    }

    /**
     * Retourne les mots-cles actifs non vus dans GSC depuis $threshold.
     *
     * @return ClientSeoKeyword[]
     */
    public function findKeywordsToDeactivate(ClientSite $clientSite, \DateTimeImmutable $threshold): array
    {
        return $this->createQueryBuilder('k')
            ->where('k.clientSite = :site')
            ->andWhere('k.isActive = :active')
            ->andWhere('k.lastSeenInGsc IS NOT NULL')
            ->andWhere('k.lastSeenInGsc < :threshold')
            ->setParameter('site', $clientSite)
            ->setParameter('active', true)
            ->setParameter('threshold', $threshold)
            ->getQuery()
            ->getResult();
    }
}
