<?php

namespace App\Controller\Admin;

use App\Entity\City;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\SlugField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;

class CityCrudController extends AbstractCrudController
{
    public function __construct(
        private CsrfTokenManagerInterface $csrfTokenManager,
    ) {
    }

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

        yield TextareaField::new('description', 'Description (par defaut)')
            ->setRequired(true)
            ->setHelp('Texte par defaut utilise si les descriptions specifiques ci-dessous sont vides')
            ->hideOnIndex();

        yield TextareaField::new('descriptionDeveloppeur', 'Short - Developpeur Web')
            ->setRequired(false)
            ->setHelp('Hero + meta description pour /developpeur-web-{slug}. Vide = description par defaut.')
            ->setFormTypeOption('attr', ['data-char-min' => 120, 'data-char-max' => 155, 'rows' => 3])
            ->hideOnIndex();

        yield TextareaField::new('descriptionDeveloppeurLong', 'Long - Developpeur Web')
            ->setRequired(false)
            ->setHelp('Texte de presentation pour /developpeur-web-{slug}. Vide = texte generique.')
            ->setFormTypeOption('attr', ['data-char-min' => 900, 'data-char-max' => 1200, 'rows' => 8])
            ->hideOnIndex();

        yield TextareaField::new('descriptionCreation', 'Short - Creation Site Internet')
            ->setRequired(false)
            ->setHelp('Hero + meta description pour /creation-site-internet-{slug}. Vide = description par defaut.')
            ->setFormTypeOption('attr', ['data-char-min' => 120, 'data-char-max' => 155, 'rows' => 3])
            ->hideOnIndex();

        yield TextareaField::new('descriptionCreationLong', 'Long - Creation Site Internet')
            ->setRequired(false)
            ->setHelp('Texte de presentation pour /creation-site-internet-{slug}. Vide = texte generique.')
            ->setFormTypeOption('attr', ['data-char-min' => 900, 'data-char-max' => 1200, 'rows' => 8])
            ->hideOnIndex();

        yield TextareaField::new('descriptionAgence', 'Short - Agence Web')
            ->setRequired(false)
            ->setHelp('Hero + meta description pour /agence-web-{slug}. Vide = description par defaut.')
            ->setFormTypeOption('attr', ['data-char-min' => 120, 'data-char-max' => 155, 'rows' => 3])
            ->hideOnIndex();

        yield TextareaField::new('descriptionAgenceLong', 'Long - Agence Web')
            ->setRequired(false)
            ->setHelp('Texte de presentation pour /agence-web-{slug}. Vide = texte generique.')
            ->setFormTypeOption('attr', ['data-char-min' => 900, 'data-char-max' => 1200, 'rows' => 8])
            ->hideOnIndex();

        yield ArrayField::new('nearby', 'Villes proches')
            ->setHelp('Liste des villes/communes environnantes (une par ligne)')
            ->hideOnIndex();

        yield ArrayField::new('keywords', 'Mots-clés SEO')
            ->setHelp('Mots-clés cibles pour cette ville (un par ligne)')
            ->hideOnIndex();

        yield IntegerField::new('sortOrder', 'Ordre')
            ->setHelp('Pour trier l\'affichage (0 = premier)')
            ->hideOnIndex();

        yield TextField::new('lastOptimizedLabel', 'Dernière optimisation')
            ->formatValue(function ($value, City $city) {
                $date = $city->getLastOptimizedAt();
                if ($date === null) {
                    return '<span style="color:#9ca3af">jamais</span>';
                }

                return $date->format('d/m/Y');
            })
            ->setVirtual(true)
            ->renderAsHtml()
            ->onlyOnIndex();

        yield BooleanField::new('isActive', 'Active')
            ->renderAsSwitch(true)
            ->setHelp('Seules les villes actives génèrent des pages');

        // URLs des 3 landing pages + bouton "Optimisé" (sur index seulement)
        if ($pageName === Crud::PAGE_INDEX) {
            $csrfManager = $this->csrfTokenManager;
            yield TextField::new('pageUrls', 'Pages')
                ->formatValue(function ($value, City $city) use ($csrfManager) {
                    if (!$city->isActive()) {
                        return '-';
                    }
                    $slug = $city->getSlug();
                    $base = 'https://alre-web.bzh';
                    $id = $city->getId();
                    $token = $csrfManager->getToken('city-optimize-' . $id)->getValue();

                    return sprintf(
                        '<a href="%1$s/developpeur-web-%2$s" target="_blank">%1$s/developpeur-web-%2$s</a><br>'
                        . '<a href="%1$s/creation-site-internet-%2$s" target="_blank">%1$s/creation-site-internet-%2$s</a><br>'
                        . '<a href="%1$s/agence-web-%2$s" target="_blank">%1$s/agence-web-%2$s</a><br>'
                        . '<button type="button" class="btn btn-sm btn-outline-success mt-1 city-mark-optimized" '
                        . 'data-city-id="%3$d" data-token="%4$s" style="font-size:0.75rem">'
                        . '<i class="fas fa-check"></i> Optimisé</button>'
                        . '<span class="city-optimized-result ms-2" data-city-id="%3$d" style="font-size:0.75rem"></span>',
                        $base,
                        $slug,
                        $id,
                        $token
                    );
                })
                ->setVirtual(true)
                ->renderAsHtml();
        }

        yield DateTimeField::new('createdAt', 'Créé le')
            ->setFormat('dd/MM/yyyy')
            ->hideOnForm()
            ->hideOnIndex();
    }
}
