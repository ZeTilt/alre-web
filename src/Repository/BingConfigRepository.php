<?php

namespace App\Repository;

use App\Entity\BingConfig;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<BingConfig>
 */
class BingConfigRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BingConfig::class);
    }

    /**
     * Retourne la configuration Bing (singleton).
     * Crée l'enregistrement s'il n'existe pas.
     */
    public function getOrCreate(): BingConfig
    {
        $config = $this->findOneBy([]);

        if ($config === null) {
            $config = new BingConfig();
            $this->getEntityManager()->persist($config);
            $this->getEntityManager()->flush();
        }

        return $config;
    }
}
