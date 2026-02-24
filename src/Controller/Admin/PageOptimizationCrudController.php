<?php

namespace App\Controller\Admin;

use App\Entity\PageOptimization;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;

class PageOptimizationCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return PageOptimization::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Page principale')
            ->setEntityLabelInPlural('Pages principales (SEO)')
            ->setPageTitle('index', 'Gestion des pages principales - SEO')
            ->setPageTitle('new', 'Ajouter une page')
            ->setPageTitle('edit', 'Modifier la page')
            ->setDefaultSort(['url' => 'ASC'])
            ->setSearchFields(['url', 'label'])
            ->showEntityActionsInlined();
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(BooleanFilter::new('isActive', 'Actif'));
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('label', 'Nom')
            ->setRequired(true);

        yield TextField::new('url', 'URL (chemin relatif)')
            ->setRequired(true)
            ->setHelp('Ex: /tarifs, /portfolio/mon-projet');

        yield BooleanField::new('isActive', 'Actif')
            ->renderAsSwitch(true);

        yield TextField::new('lastOptimizedLabel', 'Dernière optimisation')
            ->formatValue(function ($value, PageOptimization $page) {
                $date = $page->getLastOptimizedAt();
                if ($date === null) {
                    return '<span style="color:#9ca3af">jamais</span>';
                }
                return $date->format('d/m/Y');
            })
            ->setVirtual(true)
            ->renderAsHtml()
            ->onlyOnIndex();

        yield DateTimeField::new('lastOptimizedAt', 'Dernière optimisation')
            ->setFormat('dd/MM/yyyy')
            ->hideOnIndex();

        yield DateTimeField::new('createdAt', 'Créé le')
            ->setFormat('dd/MM/yyyy')
            ->setHelp('Antidater pour que la page apparaisse immédiatement dans le dashboard (filtre 30j)');
    }
}
