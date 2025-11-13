<?php

namespace App\Repository;

use App\Entity\Testimonial;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Testimonial>
 */
class TestimonialRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Testimonial::class);
    }

    /**
     * Find all published testimonials ordered by creation date
     *
     * @return Testimonial[]
     */
    public function findPublished(): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.isPublished = :published')
            ->setParameter('published', true)
            ->orderBy('t.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find featured testimonials (best ratings)
     *
     * @return Testimonial[]
     */
    public function findFeatured(int $limit = 6): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.isPublished = :published')
            ->andWhere('t.rating >= :minRating')
            ->setParameter('published', true)
            ->setParameter('minRating', 5)
            ->orderBy('t.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
