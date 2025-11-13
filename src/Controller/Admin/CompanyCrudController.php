<?php

namespace App\Controller\Admin;

use App\Entity\Company;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TelephoneField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\UrlField;
use Doctrine\ORM\EntityManagerInterface;

class CompanyCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Company::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Informations Entreprise')
            ->setEntityLabelInPlural('Informations Entreprise')
            ->setPageTitle('index', 'Informations de l\'entreprise')
            ->setPageTitle('new', 'Ajouter les informations de l\'entreprise')
            ->setPageTitle('edit', 'Modifier les informations de l\'entreprise')
            ->setPageTitle('detail', 'Détails de l\'entreprise')
            ->setDefaultSort(['id' => 'ASC'])
            ->setPaginatorPageSize(1);
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->onlyOnIndex(),
            TextField::new('name', 'Nom de l\'entreprise')
                ->setRequired(true)
                ->setHelp('Ex: ZeTilt'),
            TextField::new('ownerName', 'Nom du dirigeant')
                ->setRequired(true)
                ->setHelp('Ex: Fabrice DHUICQUE'),
            TextField::new('title', 'Titre/Fonction')
                ->setRequired(true)
                ->setHelp('Ex: Développeur Web Full-Stack'),
            TextareaField::new('address', 'Adresse')
                ->setRequired(true)
                ->setHelp('Ex: 1, impasse de la Forge')
                ->setNumOfRows(2),
            TextField::new('postalCode', 'Code postal')
                ->setRequired(true)
                ->setHelp('Ex: 56400'),
            TextField::new('city', 'Ville')
                ->setRequired(true)
                ->setHelp('Ex: Sainte-Anne d\'Auray'),
            TelephoneField::new('phone', 'Téléphone')
                ->setRequired(true)
                ->setHelp('Ex: 06 95 78 69 84'),
            EmailField::new('email', 'Email')
                ->setRequired(true)
                ->setHelp('Ex: contact@zetilt.fr'),
            TextField::new('siret', 'SIRET')
                ->setRequired(true)
                ->setHelp('Ex: 90308676700014'),
            UrlField::new('website', 'Site web')
                ->setRequired(false)
                ->setHelp('Ex: https://zetilt.fr'),
            TextField::new('legalStatus', 'Statut juridique')
                ->setRequired(false)
                ->setHelp('Ex: Auto-entrepreneur'),
            TextareaField::new('legalMentions', 'Mentions légales')
                ->setRequired(false)
                ->setHelp('Mentions légales à afficher sur les documents')
                ->setNumOfRows(3),
        ];
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof Company) {
            // Ensure only one company record exists
            $existingCompany = $entityManager->getRepository(Company::class)->findOneBy([]);
            if ($existingCompany) {
                $this->addFlash('error', 'Une entreprise existe déjà. Vous ne pouvez en avoir qu\'une seule.');
                return;
            }
        }

        parent::persistEntity($entityManager, $entityInstance);
    }
}