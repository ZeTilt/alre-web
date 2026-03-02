<?php

namespace App\Repository;

use App\Entity\Offer;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Offer>
 */
class OfferRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Offer::class);
    }

    /**
     * @return array<string, Offer> Active offers indexed by slug
     */
    public function findAllIndexedBySlug(): array
    {
        $offers = $this->createQueryBuilder('o')
            ->where('o.isActive = true')
            ->orderBy('o.sortOrder', 'ASC')
            ->getQuery()
            ->getResult();

        $indexed = [];
        foreach ($offers as $offer) {
            $indexed[$offer->getSlug()] = $offer;
        }

        return $indexed;
    }

    public function hasAnyActivePromo(): bool
    {
        $offers = $this->findAllIndexedBySlug();
        foreach ($offers as $offer) {
            if ($offer->hasActivePromo()) {
                return true;
            }
        }

        return false;
    }

    public function getLatestPromoEndDate(): ?\DateTimeInterface
    {
        $result = $this->createQueryBuilder('o')
            ->select('MAX(o.promoEndDate) as latestDate')
            ->where('o.isActive = true')
            ->andWhere('o.promoPrice IS NOT NULL')
            ->andWhere('o.promoEndDate IS NOT NULL')
            ->getQuery()
            ->getSingleScalarResult();

        return $result ? new \DateTime($result) : null;
    }
}
