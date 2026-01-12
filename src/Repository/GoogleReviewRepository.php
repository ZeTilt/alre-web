<?php

namespace App\Repository;

use App\Entity\GoogleReview;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<GoogleReview>
 */
class GoogleReviewRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GoogleReview::class);
    }

    /**
     * Retourne tous les avis approuvés, triés par date (plus récent en premier).
     *
     * @return GoogleReview[]
     */
    public function findApproved(int $limit = null): array
    {
        $qb = $this->createQueryBuilder('r')
            ->where('r.isApproved = :approved')
            ->setParameter('approved', true)
            ->orderBy('r.reviewDate', 'DESC');

        if ($limit !== null) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Retourne tous les avis en attente de modération.
     *
     * @return GoogleReview[]
     */
    public function findPending(): array
    {
        return $this->createQueryBuilder('r')
            ->where('r.isApproved = :approved')
            ->andWhere('r.rejectedAt IS NULL')
            ->setParameter('approved', false)
            ->orderBy('r.reviewDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Retourne tous les avis rejetés.
     *
     * @return GoogleReview[]
     */
    public function findRejected(): array
    {
        return $this->createQueryBuilder('r')
            ->where('r.rejectedAt IS NOT NULL')
            ->orderBy('r.rejectedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Recherche un avis par son ID Google (pour éviter les doublons).
     */
    public function findByGoogleReviewId(string $googleReviewId): ?GoogleReview
    {
        return $this->createQueryBuilder('r')
            ->where('r.googleReviewId = :googleReviewId')
            ->setParameter('googleReviewId', $googleReviewId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Calcule la note moyenne des avis approuvés.
     */
    public function getAverageRating(): ?float
    {
        $result = $this->createQueryBuilder('r')
            ->select('AVG(r.rating) as avgRating')
            ->where('r.isApproved = :approved')
            ->setParameter('approved', true)
            ->getQuery()
            ->getSingleScalarResult();

        return $result !== null ? round((float) $result, 1) : null;
    }

    /**
     * Compte le nombre d'avis approuvés.
     */
    public function countApproved(): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->where('r.isApproved = :approved')
            ->setParameter('approved', true)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Compte le nombre d'avis en attente de modération.
     */
    public function countPending(): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->where('r.isApproved = :approved')
            ->andWhere('r.rejectedAt IS NULL')
            ->setParameter('approved', false)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Retourne les statistiques des avis.
     *
     * @return array{total: int, approved: int, pending: int, rejected: int, averageRating: ?float}
     */
    public function getStats(): array
    {
        $total = (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $approved = $this->countApproved();
        $pending = $this->countPending();
        $rejected = $total - $approved - $pending;

        return [
            'total' => $total,
            'approved' => $approved,
            'pending' => $pending,
            'rejected' => $rejected,
            'averageRating' => $this->getAverageRating(),
        ];
    }

    /**
     * Vérifie si la dernière synchronisation date de moins de X heures.
     */
    public function isDataFresh(int $hours = 12): bool
    {
        $lastReview = $this->createQueryBuilder('r')
            ->orderBy('r.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$lastReview) {
            return false;
        }

        $threshold = new \DateTimeImmutable("-{$hours} hours");
        return $lastReview->getCreatedAt() > $threshold;
    }
}
