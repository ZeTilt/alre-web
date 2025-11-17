<?php

namespace App\Controller\Admin;

use App\Entity\Partner;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TelephoneField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\UrlField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;

class PartnerCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Partner::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Partenaire')
            ->setEntityLabelInPlural('Partenaires')
            ->setPageTitle('index', 'Liste des partenaires')
            ->setPageTitle('new', 'Créer un partenaire')
            ->setPageTitle('edit', 'Modifier le partenaire')
            ->setPageTitle('detail', 'Détails du partenaire')
            ->setDefaultSort(['name' => 'ASC'])
            ->setPaginatorPageSize(20);
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->onlyOnIndex(),
            TextField::new('name', 'Nom')
                ->setHelp('Nom du partenaire ou de l\'entreprise'),
            UrlField::new('url', 'Site web')
                ->setHelp('URL du site web du partenaire'),
            EmailField::new('email', 'Email')->hideOnIndex(),
            TelephoneField::new('phone', 'Téléphone')->hideOnIndex(),
            ArrayField::new('domains', 'Domaines d\'activité')
                ->setHelp('Listez les domaines d\'expertise du partenaire (ex: Design graphique, Photographie, Rédaction)')
                ->hideOnIndex(),
            ImageField::new('logo', 'Logo')
                ->setBasePath('uploads/partners')
                ->setUploadDir('public/uploads/partners')
                ->setUploadedFileNamePattern('[randomhash].[extension]')
                ->setHelp('Logo du partenaire (formats acceptés: JPG, PNG, WebP)')
                ->hideOnIndex(),
            BooleanField::new('isActive', 'Actif')
                ->setHelp('Si décoché, le partenaire n\'apparaîtra pas dans les listes de sélection'),
        ];
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(BooleanFilter::new('isActive'));
    }
}
