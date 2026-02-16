<?php

namespace App\Repository;

use App\Entity\DepartmentPage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DepartmentPage>
 */
class DepartmentPageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DepartmentPage::class);
    }

    /**
     * @return DepartmentPage[]
     */
    public function findAllActive(): array
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('d.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findBySlug(string $slug): ?DepartmentPage
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.slug = :slug')
            ->andWhere('d.isActive = :active')
            ->setParameter('slug', $slug)
            ->setParameter('active', true)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return array<string, DepartmentPage> Indexed by name
     */
    public function findAllActiveIndexedByName(): array
    {
        $departments = $this->findAllActive();
        $indexed = [];
        foreach ($departments as $dept) {
            $indexed[$dept->getName()] = $dept;
        }
        return $indexed;
    }
}
