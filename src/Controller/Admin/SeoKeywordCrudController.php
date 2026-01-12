<?php

namespace App\Controller\Admin;

use App\Entity\SeoKeyword;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\UrlField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;

class SeoKeywordCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return SeoKeyword::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Mot-clé SEO')
            ->setEntityLabelInPlural('Mots-clés SEO')
            ->setPageTitle('index', 'Gestion des mots-clés SEO')
            ->setPageTitle('new', 'Ajouter un mot-clé')
            ->setPageTitle('edit', 'Modifier le mot-clé')
            ->setDefaultSort(['keyword' => 'ASC'])
            ->setSearchFields(['keyword', 'targetUrl'])
            ->showEntityActionsInlined();
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->reorder(Crud::PAGE_INDEX, [Action::DETAIL, Action::EDIT, Action::DELETE]);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(BooleanFilter::new('isActive', 'Actif'));
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id', 'ID')
            ->hideOnForm();

        yield TextField::new('keyword', 'Mot-clé')
            ->setRequired(true)
            ->setHelp('Le mot-clé à suivre dans Google Search Console');

        yield UrlField::new('targetUrl', 'URL cible')
            ->setRequired(false)
            ->setHelp('URL spécifique à suivre pour ce mot-clé (optionnel)')
            ->hideOnIndex();

        yield BooleanField::new('isActive', 'Actif')
            ->renderAsSwitch(true)
            ->setHelp('Seuls les mots-clés actifs sont synchronisés');

        // Afficher la dernière position sur la page index
        if ($pageName === Crud::PAGE_INDEX || $pageName === Crud::PAGE_DETAIL) {
            yield NumberField::new('latestPosition.position', 'Position')
                ->setNumDecimals(1)
                ->formatValue(function ($value, $entity) {
                    $latest = $entity->getLatestPosition();
                    if (!$latest) {
                        return '-';
                    }
                    $pos = $latest->getPosition();
                    if ($pos <= 10) {
                        return sprintf('<span style="color: #10b981; font-weight: bold;">%.1f</span>', $pos);
                    } elseif ($pos <= 20) {
                        return sprintf('<span style="color: #f59e0b;">%.1f</span>', $pos);
                    } else {
                        return sprintf('<span style="color: #ef4444;">%.1f</span>', $pos);
                    }
                })
                ->onlyOnIndex();

            yield NumberField::new('latestPosition.clicks', 'Clics')
                ->formatValue(function ($value, $entity) {
                    $latest = $entity->getLatestPosition();
                    return $latest ? $latest->getClicks() : '-';
                })
                ->onlyOnIndex();

            yield NumberField::new('latestPosition.impressions', 'Impressions')
                ->formatValue(function ($value, $entity) {
                    $latest = $entity->getLatestPosition();
                    return $latest ? number_format($latest->getImpressions(), 0, ',', ' ') : '-';
                })
                ->onlyOnIndex();
        }

        yield DateTimeField::new('lastSyncAt', 'Dernière sync')
            ->setFormat('dd/MM/yyyy HH:mm')
            ->hideOnForm();

        yield DateTimeField::new('createdAt', 'Créé le')
            ->setFormat('dd/MM/yyyy')
            ->hideOnForm()
            ->hideOnIndex();
    }
}
