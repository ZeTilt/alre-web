<?php

namespace App\Controller\Admin;

use App\Entity\Offer;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;

class OfferCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Offer::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Offre')
            ->setEntityLabelInPlural('Offres & Tarifs')
            ->setDefaultSort(['sortOrder' => 'ASC'])
            ->setSearchFields(['name', 'slug', 'category']);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(ChoiceFilter::new('category')->setChoices([
                'Création' => 'creation',
                'Hébergement' => 'hebergement',
                'SEO' => 'seo',
                'Ponctuel' => 'ponctuel',
            ]));
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('name', 'Nom')
            ->setColumns(6);

        yield TextField::new('slug', 'Slug')
            ->setHelp('Identifiant unique (snake_case)')
            ->setColumns(6);

        yield ChoiceField::new('category', 'Catégorie')
            ->setChoices([
                'Création' => 'creation',
                'Hébergement' => 'hebergement',
                'SEO' => 'seo',
                'Ponctuel' => 'ponctuel',
            ])
            ->setColumns(4);

        yield MoneyField::new('price', 'Prix normal')
            ->setCurrency('EUR')
            ->setStoredAsCents(false)
            ->setColumns(4);

        yield MoneyField::new('promoPrice', 'Prix promo')
            ->setCurrency('EUR')
            ->setStoredAsCents(false)
            ->setHelp('Laisser vide = pas de promo')
            ->setColumns(4);

        yield DateTimeField::new('promoEndDate', 'Fin promo')
            ->setHelp('Laisser vide = promo sans date de fin')
            ->hideOnIndex();

        yield TextField::new('promoLabel', 'Badge promo')
            ->setHelp('Ex: "Offre Lancement"')
            ->hideOnIndex();

        yield BooleanField::new('isPromoActive', 'Promo active')
            ->setColumns(3)
            ->hideOnIndex();

        yield BooleanField::new('isRecurring', 'Mensuel')
            ->setColumns(3);

        yield TextField::new('priceSuffix', 'Suffixe prix')
            ->setHelp('Ex: "/page", "/langue/page"')
            ->hideOnIndex();

        yield TextField::new('shortDescription', 'Description courte')
            ->hideOnIndex();

        yield BooleanField::new('isFeatured', 'Mis en avant')
            ->setColumns(3);

        yield BooleanField::new('isActive', 'Actif')
            ->setColumns(3);

        yield IntegerField::new('sortOrder', 'Ordre')
            ->setColumns(3);

        // Colonnes calculées en index uniquement
        if ($pageName === Crud::PAGE_INDEX) {
            yield TextField::new('formattedCurrentPrice', 'Prix actuel')
                ->formatValue(function ($value, Offer $entity) {
                    $suffix = $entity->isRecurring() ? ' €/mois' : ' €';
                    $priceSuffix = $entity->getPriceSuffix() ? ' ' . $entity->getPriceSuffix() : '';
                    return $value . $suffix . $priceSuffix;
                });

            yield BooleanField::new('hasActivePromo', 'Promo active')
                ->renderAsSwitch(false);
        }
    }
}
