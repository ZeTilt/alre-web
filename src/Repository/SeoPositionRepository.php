<?php

namespace App\Repository;

use App\Entity\SeoKeyword;
use App\Entity\SeoPosition;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SeoPosition>
 */
class SeoPositionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SeoPosition::class);
    }

    /**
     * Retourne la dernière position pour un mot-clé.
     */
    public function findLatestForKeyword(SeoKeyword $keyword): ?SeoPosition
    {
        return $this->createQueryBuilder('p')
            ->where('p.keyword = :keyword')
            ->setParameter('keyword', $keyword)
            ->orderBy('p.date', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Retourne les positions d'un mot-clé sur une période.
     *
     * @return SeoPosition[]
     */
    public function findByKeywordAndPeriod(
        SeoKeyword $keyword,
        \DateTimeImmutable $startDate,
        \DateTimeImmutable $endDate
    ): array {
        return $this->createQueryBuilder('p')
            ->where('p.keyword = :keyword')
            ->andWhere('p.date >= :startDate')
            ->andWhere('p.date <= :endDate')
            ->setParameter('keyword', $keyword)
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('p.date', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Retourne la position d'un mot-clé à une date donnée.
     */
    public function findByKeywordAndDate(SeoKeyword $keyword, \DateTimeImmutable $date): ?SeoPosition
    {
        // Comparer uniquement la partie date (ignorer l'heure)
        $startOfDay = $date->setTime(0, 0, 0);
        $endOfDay = $date->setTime(23, 59, 59);

        return $this->createQueryBuilder('p')
            ->where('p.keyword = :keyword')
            ->andWhere('p.date >= :startOfDay')
            ->andWhere('p.date <= :endOfDay')
            ->setParameter('keyword', $keyword)
            ->setParameter('startOfDay', $startOfDay)
            ->setParameter('endOfDay', $endOfDay)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Retourne les positions de tous les mots-clés actifs pour une date.
     *
     * @return SeoPosition[]
     */
    public function findAllForDate(\DateTimeImmutable $date): array
    {
        return $this->createQueryBuilder('p')
            ->join('p.keyword', 'k')
            ->where('k.isActive = :active')
            ->andWhere('p.date = :date')
            ->setParameter('active', true)
            ->setParameter('date', $date)
            ->orderBy('p.position', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Calcule les totaux de clics et impressions sur une période.
     *
     * @return array{clicks: int, impressions: int}
     */
    public function getTotalsForPeriod(\DateTimeImmutable $startDate, \DateTimeImmutable $endDate): array
    {
        $result = $this->createQueryBuilder('p')
            ->select('SUM(p.clicks) as clicks, SUM(p.impressions) as impressions')
            ->join('p.keyword', 'k')
            ->where('k.isActive = :active')
            ->andWhere('p.date >= :startDate')
            ->andWhere('p.date <= :endDate')
            ->setParameter('active', true)
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->getQuery()
            ->getSingleResult();

        return [
            'clicks' => (int) ($result['clicks'] ?? 0),
            'impressions' => (int) ($result['impressions'] ?? 0),
        ];
    }

    /**
     * Calcule la position moyenne d'un mot-clé sur une période.
     */
    public function getAveragePositionForPeriod(
        SeoKeyword $keyword,
        \DateTimeImmutable $startDate,
        \DateTimeImmutable $endDate
    ): ?float {
        $result = $this->createQueryBuilder('p')
            ->select('AVG(p.position) as avgPosition')
            ->where('p.keyword = :keyword')
            ->andWhere('p.date >= :startDate')
            ->andWhere('p.date <= :endDate')
            ->setParameter('keyword', $keyword)
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->getQuery()
            ->getSingleScalarResult();

        return $result !== null ? round((float) $result, 1) : null;
    }

    /**
     * Récupère les positions moyennes de tous les mots-clés actifs pour une période.
     *
     * @return array<int, array{keywordId: int, avgPosition: float, totalClicks: int, totalImpressions: int}>
     */
    public function getAveragePositionsForAllKeywords(
        \DateTimeImmutable $startDate,
        \DateTimeImmutable $endDate
    ): array {
        $results = $this->createQueryBuilder('p')
            ->select(
                'IDENTITY(p.keyword) as keywordId',
                'AVG(p.position) as avgPosition',
                'SUM(p.clicks) as totalClicks',
                'SUM(p.impressions) as totalImpressions'
            )
            ->join('p.keyword', 'k')
            ->where('k.isActive = :active')
            ->andWhere('p.date >= :startDate')
            ->andWhere('p.date <= :endDate')
            ->setParameter('active', true)
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->groupBy('p.keyword')
            ->getQuery()
            ->getResult();

        $data = [];
        foreach ($results as $row) {
            $data[(int) $row['keywordId']] = [
                'keywordId' => (int) $row['keywordId'],
                'avgPosition' => round((float) $row['avgPosition'], 1),
                'totalClicks' => (int) $row['totalClicks'],
                'totalImpressions' => (int) $row['totalImpressions'],
            ];
        }

        return $data;
    }

    /**
     * Récupère les totaux quotidiens de clics et impressions sur une période.
     *
     * @return array<string, array{date: string, clicks: int, impressions: int}>
     */
    public function getDailyTotals(
        \DateTimeImmutable $startDate,
        \DateTimeImmutable $endDate
    ): array {
        $results = $this->createQueryBuilder('p')
            ->select(
                'p.date as dateObj',
                'SUM(p.clicks) as totalClicks',
                'SUM(p.impressions) as totalImpressions'
            )
            ->join('p.keyword', 'k')
            ->where('k.isActive = :active')
            ->andWhere('p.date >= :startDate')
            ->andWhere('p.date <= :endDate')
            ->setParameter('active', true)
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->groupBy('p.date')
            ->orderBy('p.date', 'ASC')
            ->getQuery()
            ->getResult();

        $data = [];
        foreach ($results as $row) {
            $dateKey = $row['dateObj']->format('Y-m-d');
            $data[$dateKey] = [
                'date' => $dateKey,
                'clicks' => (int) $row['totalClicks'],
                'impressions' => (int) $row['totalImpressions'],
            ];
        }

        return $data;
    }

    /**
     * Compte le nombre de jours avec des données sur une période.
     */
    public function countDaysWithData(
        \DateTimeImmutable $startDate,
        \DateTimeImmutable $endDate
    ): int {
        $result = $this->createQueryBuilder('p')
            ->select('COUNT(DISTINCT p.date) as dayCount')
            ->join('p.keyword', 'k')
            ->where('k.isActive = :active')
            ->andWhere('p.date >= :startDate')
            ->andWhere('p.date <= :endDate')
            ->setParameter('active', true)
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $result;
    }

    /**
     * Retourne les N dernières dates distinctes ayant des données de position.
     *
     * @return \DateTimeImmutable[]
     */
    public function findLatestDatesWithData(int $limit = 2): array
    {
        $results = $this->createQueryBuilder('p')
            ->select('p.date')
            ->join('p.keyword', 'k')
            ->where('k.isActive = :active')
            ->setParameter('active', true)
            ->groupBy('p.date')
            ->orderBy('p.date', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return array_map(fn(array $row) => $row['date'], $results);
    }

    /**
     * Retourne les positions quotidiennes de tous les mots-clés actifs sur les N dernières dates avec données.
     * Résultat groupé par keywordId.
     *
     * @return array<int, float[]> keywordId => [position1, position2, ...]
     */
    public function getPositionHistoryByKeyword(int $days = 7): array
    {
        $latestDates = $this->findLatestDatesWithData($days);
        if (empty($latestDates)) {
            return [];
        }

        $startDate = end($latestDates)->setTime(0, 0, 0);
        $endDate = $latestDates[0]->setTime(23, 59, 59);

        $results = $this->createQueryBuilder('p')
            ->select('IDENTITY(p.keyword) as keywordId', 'p.position')
            ->join('p.keyword', 'k')
            ->where('k.isActive = :active')
            ->andWhere('p.date >= :startDate')
            ->andWhere('p.date <= :endDate')
            ->setParameter('active', true)
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('p.date', 'ASC')
            ->getQuery()
            ->getResult();

        $data = [];
        foreach ($results as $row) {
            $data[(int) $row['keywordId']][] = (float) $row['position'];
        }

        return $data;
    }

    /**
     * Retourne toutes les positions depuis une date donnée.
     *
     * @return SeoPosition[]
     */
    public function findAllSince(\DateTimeImmutable $since): array
    {
        return $this->createQueryBuilder('p')
            ->join('p.keyword', 'k')
            ->where('p.date >= :since')
            ->setParameter('since', $since)
            ->orderBy('p.date', 'DESC')
            ->addOrderBy('k.keyword', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Retourne les positions d'un mot-clé depuis une date donnée.
     *
     * @return SeoPosition[]
     */
    public function findByKeywordSince(SeoKeyword $keyword, \DateTimeImmutable $since): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.keyword = :keyword')
            ->andWhere('p.date >= :since')
            ->setParameter('keyword', $keyword)
            ->setParameter('since', $since)
            ->orderBy('p.date', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
