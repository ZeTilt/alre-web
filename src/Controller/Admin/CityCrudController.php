<?php

namespace App\Controller\Admin;

use App\Entity\City;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\SlugField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;

class CityCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return City::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Ville')
            ->setEntityLabelInPlural('Villes (SEO Local)')
            ->setPageTitle('index', 'Gestion des villes - Landing Pages SEO')
            ->setPageTitle('new', 'Ajouter une ville')
            ->setPageTitle('edit', 'Modifier la ville')
            ->setDefaultSort(['sortOrder' => 'ASC', 'name' => 'ASC'])
            ->setSearchFields(['name', 'slug', 'region'])
            ->showEntityActionsInlined()
            ->setHelp('index', 'Chaque ville génère automatiquement 3 pages SEO locales : développeur-web-{slug}, creation-site-internet-{slug}, agence-web-{slug}');
    }

    public function configureActions(Actions $actions): Actions
    {
        $previewAction = Action::new('preview', 'Voir les pages')
            ->linkToUrl(function (City $city) {
                return '/developpeur-web-' . $city->getSlug();
            })
            ->setIcon('fa fa-eye')
            ->setHtmlAttributes(['target' => '_blank']);

        return $actions
            ->add(Crud::PAGE_INDEX, $previewAction)
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->reorder(Crud::PAGE_INDEX, ['preview', Action::DETAIL, Action::EDIT, Action::DELETE]);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(BooleanFilter::new('isActive', 'Active'))
            ->add('region');
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id', 'ID')
            ->hideOnForm();

        yield TextField::new('name', 'Nom de la ville')
            ->setRequired(true)
            ->setHelp('Ex: Vannes, Lorient, Carnac');

        yield SlugField::new('slug', 'Slug URL')
            ->setTargetFieldName('name')
            ->setRequired(true)
            ->setHelp('Utilisé dans les URLs: /developpeur-web-{slug}');

        yield TextField::new('region', 'Région/Département')
            ->setRequired(true)
            ->setHelp('Ex: Morbihan, Finistère');

        yield TextareaField::new('description', 'Description')
            ->setRequired(true)
            ->setHelp('Texte personnalisé pour cette ville (utilisé dans le contenu SEO)')
            ->hideOnIndex();

        yield ArrayField::new('nearby', 'Villes proches')
            ->setHelp('Liste des villes/communes environnantes (une par ligne)')
            ->hideOnIndex();

        yield ArrayField::new('keywords', 'Mots-clés SEO')
            ->setHelp('Mots-clés cibles pour cette ville (un par ligne)')
            ->hideOnIndex();

        yield IntegerField::new('sortOrder', 'Ordre')
            ->setHelp('Pour trier l\'affichage (0 = premier)');

        yield BooleanField::new('isActive', 'Active')
            ->renderAsSwitch(true)
            ->setHelp('Seules les villes actives génèrent des pages');

        // Compteur de pages générées (sur index seulement)
        if ($pageName === Crud::PAGE_INDEX) {
            yield TextField::new('pageCount', 'Pages')
                ->formatValue(function ($value, City $city) {
                    return $city->isActive() ? '3 pages' : '-';
                })
                ->setVirtual(true);
        }

        yield DateTimeField::new('createdAt', 'Créé le')
            ->setFormat('dd/MM/yyyy')
            ->hideOnForm()
            ->hideOnIndex();
    }
}
