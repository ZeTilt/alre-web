<?php

namespace App\Repository;

use App\Entity\ClientBingKeyword;
use App\Entity\ClientBingPosition;
use App\Entity\ClientSite;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ClientBingPosition>
 */
class ClientBingPositionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ClientBingPosition::class);
    }

    public function findByKeywordAndDate(ClientBingKeyword $keyword, \DateTimeImmutable $date): ?ClientBingPosition
    {
        return $this->createQueryBuilder('p')
            ->where('p.clientBingKeyword = :keyword')
            ->andWhere('p.date = :date')
            ->setParameter('keyword', $keyword)
            ->setParameter('date', $date, Types::DATE_IMMUTABLE)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
