<?php

namespace App\Repository;

use App\Entity\ClientSeoKeyword;
use App\Entity\ClientSeoPosition;
use App\Entity\ClientSite;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ClientSeoPosition>
 */
class ClientSeoPositionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ClientSeoPosition::class);
    }

    public function findByKeywordAndDate(ClientSeoKeyword $keyword, \DateTimeImmutable $date, string $source = ClientSeoPosition::SOURCE_GOOGLE): ?ClientSeoPosition
    {
        return $this->createQueryBuilder('p')
            ->where('p.clientSeoKeyword = :keyword')
            ->andWhere('p.date = :date')
            ->andWhere('p.source = :source')
            ->setParameter('keyword', $keyword)
            ->setParameter('date', $date, Types::DATE_IMMUTABLE)
            ->setParameter('source', $source)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findByKeywordDateAndSource(ClientSeoKeyword $keyword, \DateTimeImmutable $date, string $source): ?ClientSeoPosition
    {
        return $this->findByKeywordAndDate($keyword, $date, $source);
    }

    /**
     * @return array<int, array{keywordId: int, avgPosition: float, totalClicks: int, totalImpressions: int}>
     */
    public function getAveragePositionsForAllKeywords(
        ClientSite $clientSite,
        \DateTimeImmutable $startDate,
        \DateTimeImmutable $endDate
    ): array {
        $results = $this->createQueryBuilder('p')
            ->select(
                'IDENTITY(p.clientSeoKeyword) as keywordId',
                'AVG(p.position) as avgPosition',
                'SUM(p.clicks) as totalClicks',
                'SUM(p.impressions) as totalImpressions'
            )
            ->join('p.clientSeoKeyword', 'k')
            ->where('k.clientSite = :site')
            ->andWhere('k.isActive = :active')
            ->andWhere('p.date >= :startDate')
            ->andWhere('p.date <= :endDate')
            ->setParameter('site', $clientSite)
            ->setParameter('active', true)
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->groupBy('p.clientSeoKeyword')
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
     * @return ClientSeoPosition[]
     */
    public function findAllForDateRange(
        ClientSite $clientSite,
        \DateTimeImmutable $startDate,
        \DateTimeImmutable $endDate
    ): array {
        return $this->createQueryBuilder('p')
            ->join('p.clientSeoKeyword', 'k')
            ->where('k.clientSite = :site')
            ->andWhere('p.date >= :startDate')
            ->andWhere('p.date <= :endDate')
            ->setParameter('site', $clientSite)
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('p.date', 'DESC')
            ->addOrderBy('k.keyword', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return \DateTimeImmutable[]
     */
    public function findLatestDatesWithData(ClientSite $site, int $limit = 4): array
    {
        $results = $this->createQueryBuilder('p')
            ->select('p.date')
            ->join('p.clientSeoKeyword', 'k')
            ->where('k.clientSite = :site')
            ->andWhere('k.isActive = :active')
            ->setParameter('site', $site)
            ->setParameter('active', true)
            ->groupBy('p.date')
            ->orderBy('p.date', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return array_map(fn(array $row) => $row['date'], $results);
    }

    /**
     * Recupere toutes les positions brutes des mots-cles actifs pour une plage de dates.
     * Resultat groupe par keywordId puis par date (1 entree par keyword par jour).
     *
     * @return array<int, array<string, array{position: float, clicks: int, impressions: int}>>
     */
    public function getRawPositionsForActiveKeywords(
        ClientSite $site,
        \DateTimeImmutable $startDate,
        \DateTimeImmutable $endDate
    ): array {
        $results = $this->createQueryBuilder('p')
            ->select('IDENTITY(p.clientSeoKeyword) as keywordId', 'p.date as dateObj', 'p.position', 'p.clicks', 'p.impressions')
            ->join('p.clientSeoKeyword', 'k')
            ->where('k.clientSite = :site')
            ->andWhere('k.isActive = :active')
            ->andWhere('p.date >= :startDate')
            ->andWhere('p.date <= :endDate')
            ->setParameter('site', $site)
            ->setParameter('active', true)
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('p.date', 'ASC')
            ->getQuery()
            ->getResult();

        $data = [];
        foreach ($results as $row) {
            $keywordId = (int) $row['keywordId'];
            $dateKey = $row['dateObj']->format('Y-m-d');
            $data[$keywordId][$dateKey] = [
                'position' => (float) $row['position'],
                'clicks' => (int) $row['clicks'],
                'impressions' => (int) $row['impressions'],
            ];
        }

        return $data;
    }

    /**
     * Returns weekly average positions grouped by keyword ID.
     * Each week is identified by its ISO week number.
     *
     * @return array<int, array<string, float>> keywordId => ['2026-W06' => avgPos, ...]
     */
    public function getPositionHistoryByKeyword(ClientSite $site, int $weeks = 4): array
    {
        $startDate = (new \DateTimeImmutable())->modify("-{$weeks} weeks")->setTime(0, 0, 0);

        $results = $this->createQueryBuilder('p')
            ->select(
                'IDENTITY(p.clientSeoKeyword) as keywordId',
                'p.position',
                'p.date'
            )
            ->join('p.clientSeoKeyword', 'k')
            ->where('k.clientSite = :site')
            ->andWhere('k.isActive = :active')
            ->andWhere('p.date >= :startDate')
            ->setParameter('site', $site)
            ->setParameter('active', true)
            ->setParameter('startDate', $startDate)
            ->orderBy('p.date', 'ASC')
            ->getQuery()
            ->getResult();

        // Group by keyword then by ISO week, compute weekly averages
        $raw = [];
        foreach ($results as $row) {
            $keywordId = (int) $row['keywordId'];
            $weekKey = $row['date']->format('o-\\WW'); // ISO year + week
            $raw[$keywordId][$weekKey][] = (float) $row['position'];
        }

        $data = [];
        foreach ($raw as $keywordId => $weeks) {
            foreach ($weeks as $weekKey => $positions) {
                $data[$keywordId][$weekKey] = round(array_sum($positions) / \count($positions), 1);
            }
        }

        return $data;
    }
}
