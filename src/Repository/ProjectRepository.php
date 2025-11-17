<?php

namespace App\Repository;

use App\Entity\Project;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Project>
 */
class ProjectRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Project::class);
    }

    /**
     * Find all published projects ordered by completion year
     *
     * @return Project[]
     */
    public function findPublished(): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.isPublished = :published')
            ->setParameter('published', true)
            ->orderBy('p.completionYear', 'DESC')
            ->addOrderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find featured projects
     *
     * @return Project[]
     */
    public function findFeatured(int $limit = 3): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.isPublished = :published')
            ->andWhere('p.featured = :featured')
            ->setParameter('published', true)
            ->setParameter('featured', true)
            ->orderBy('p.completionYear', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find projects by category
     *
     * @return Project[]
     */
    public function findByCategory(string $category): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.isPublished = :published')
            ->andWhere('p.category = :category')
            ->setParameter('published', true)
            ->setParameter('category', $category)
            ->orderBy('p.completionYear', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
