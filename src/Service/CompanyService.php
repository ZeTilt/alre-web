<?php

namespace App\Service;

use App\Entity\Company;
use Doctrine\ORM\EntityManagerInterface;

class CompanyService
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function getCompany(): ?Company
    {
        return $this->entityManager->getRepository(Company::class)->findOneBy([]);
    }

    public function getCompanyOrDefault(): Company
    {
        $company = $this->getCompany();
        
        if (!$company) {
            // Return default company info if none exists
            $company = new Company();
            $company->setName('ZeTilt');
            $company->setOwnerName('DHUICQUE Fabrice');
            $company->setTitle('Développeur Web Full-Stack');
            $company->setAddress('1, impasse de la Forge');
            $company->setPostalCode('56400');
            $company->setCity('Sainte-Anne d\'Auray');
            $company->setPhone('06 95 78 69 84');
            $company->setEmail('contact@zetilt.fr');
            $company->setSiret('90308676700014');
            $company->setLegalStatus('Auto-entrepreneur');
            $company->setLegalMentions('Dispensé d\'immatriculation au registre du commerce et des sociétés (RCS) et au répertoire des métiers (RM)');
        }
        
        return $company;
    }
}