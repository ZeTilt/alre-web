<?php

namespace App\Repository;

use App\Entity\ClientSeoImport;
use App\Entity\ClientSite;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ClientSeoImport>
 */
class ClientSeoImportRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ClientSeoImport::class);
    }

    /**
     * @return ClientSeoImport[]
     */
    public function findByClientSite(ClientSite $clientSite): array
    {
        return $this->createQueryBuilder('i')
            ->where('i.clientSite = :site')
            ->setParameter('site', $clientSite)
            ->orderBy('i.importedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function getLastImportDate(ClientSite $clientSite): ?\DateTimeImmutable
    {
        $result = $this->createQueryBuilder('i')
            ->select('MAX(i.importedAt) as lastImport')
            ->where('i.clientSite = :site')
            ->andWhere('i.status = :status')
            ->setParameter('site', $clientSite)
            ->setParameter('status', ClientSeoImport::STATUS_SUCCESS)
            ->getQuery()
            ->getSingleScalarResult();

        return $result ? new \DateTimeImmutable($result) : null;
    }
}
