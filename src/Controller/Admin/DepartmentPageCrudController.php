<?php

namespace App\Controller\Admin;

use App\Entity\DepartmentPage;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\SlugField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;

class DepartmentPageCrudController extends AbstractCrudController
{
    public function __construct(
        private CsrfTokenManagerInterface $csrfTokenManager,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return DepartmentPage::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Département')
            ->setEntityLabelInPlural('Départements (SEO)')
            ->setPageTitle('index', 'Gestion des départements - Pages SEO')
            ->setPageTitle('new', 'Ajouter un département')
            ->setPageTitle('edit', 'Modifier le département')
            ->setDefaultSort(['name' => 'ASC'])
            ->setSearchFields(['name', 'slug', 'number'])
            ->showEntityActionsInlined()
            ->setHelp('index', 'Chaque département génère 4 pages SEO : développeur-web-{slug}, creation-site-internet-{slug}, agence-web-{slug}, referencement-local-{slug}');
    }

    public function configureActions(Actions $actions): Actions
    {
        $previewAction = Action::new('preview', 'Voir les pages')
            ->linkToUrl(function (DepartmentPage $dept) {
                return '/creation-site-internet-' . $dept->getSlug();
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
            ->add(BooleanFilter::new('isActive', 'Actif'));
    }

    public function configureFields(string $pageName): iterable
    {
        // --- Informations générales ---
        yield FormField::addPanel('Informations générales')
            ->setIcon('fa fa-map')
            ->onlyOnForms();

        yield TextField::new('name', 'Nom du département')
            ->setRequired(true)
            ->setHelp('Ex: Morbihan, Finistère');

        yield SlugField::new('slug', 'Slug URL')
            ->setTargetFieldName('name')
            ->setRequired(true)
            ->setHelp('Utilisé dans les URLs: /creation-site-internet-{slug}');

        yield TextField::new('number', 'Numéro')
            ->setRequired(true)
            ->setHelp('Ex: 56, 29, 22, 35');

        yield TextareaField::new('description', 'Description (par défaut)')
            ->setRequired(true)
            ->setHelp('Texte par défaut utilisé si les descriptions spécifiques ci-dessous sont vides')
            ->hideOnIndex();

        // --- Page Développeur Web ---
        yield FormField::addPanel('Page : Développeur Web')
            ->setIcon('fa fa-code')
            ->setHelp('/developpeur-web-{slug}')
            ->onlyOnForms();

        yield TextField::new('titleDeveloppeur', 'Title SEO')
            ->setRequired(false)
            ->setHelp('50-60 car. Vide = title auto.')
            ->setFormTypeOption('attr', ['data-char-min' => 50, 'data-char-max' => 65, 'maxlength' => 70])
            ->hideOnIndex();

        yield TextareaField::new('descriptionDeveloppeur', 'Description courte')
            ->setRequired(false)
            ->setHelp('Hero + meta description. 120-145 car. Vide = description par défaut.')
            ->setFormTypeOption('attr', ['data-char-min' => 120, 'data-char-max' => 155, 'rows' => 3])
            ->hideOnIndex();

        yield TextareaField::new('descriptionDeveloppeurLong', 'Description longue')
            ->setRequired(false)
            ->setHelp('Texte de présentation. 900-1200 car. Vide = texte générique.')
            ->setFormTypeOption('attr', ['data-char-min' => 900, 'data-char-max' => 1200, 'rows' => 8])
            ->hideOnIndex();

        // --- Page Création Site Internet ---
        yield FormField::addPanel('Page : Création Site Internet')
            ->setIcon('fa fa-paint-brush')
            ->setHelp('/creation-site-internet-{slug}')
            ->onlyOnForms();

        yield TextField::new('titleCreation', 'Title SEO')
            ->setRequired(false)
            ->setHelp('50-60 car. Vide = title auto.')
            ->setFormTypeOption('attr', ['data-char-min' => 50, 'data-char-max' => 65, 'maxlength' => 70])
            ->hideOnIndex();

        yield TextareaField::new('descriptionCreation', 'Description courte')
            ->setRequired(false)
            ->setHelp('Hero + meta description. 120-145 car. Vide = description par défaut.')
            ->setFormTypeOption('attr', ['data-char-min' => 120, 'data-char-max' => 155, 'rows' => 3])
            ->hideOnIndex();

        yield TextareaField::new('descriptionCreationLong', 'Description longue')
            ->setRequired(false)
            ->setHelp('Texte de présentation. 900-1200 car. Vide = texte générique.')
            ->setFormTypeOption('attr', ['data-char-min' => 900, 'data-char-max' => 1200, 'rows' => 8])
            ->hideOnIndex();

        // --- Page Agence Web ---
        yield FormField::addPanel('Page : Agence Web')
            ->setIcon('fa fa-building')
            ->setHelp('/agence-web-{slug}')
            ->onlyOnForms();

        yield TextField::new('titleAgence', 'Title SEO')
            ->setRequired(false)
            ->setHelp('50-60 car. Vide = title auto.')
            ->setFormTypeOption('attr', ['data-char-min' => 50, 'data-char-max' => 65, 'maxlength' => 70])
            ->hideOnIndex();

        yield TextareaField::new('descriptionAgence', 'Description courte')
            ->setRequired(false)
            ->setHelp('Hero + meta description. 120-145 car. Vide = description par défaut.')
            ->setFormTypeOption('attr', ['data-char-min' => 120, 'data-char-max' => 155, 'rows' => 3])
            ->hideOnIndex();

        yield TextareaField::new('descriptionAgenceLong', 'Description longue')
            ->setRequired(false)
            ->setHelp('Texte de présentation. 900-1200 car. Vide = texte générique.')
            ->setFormTypeOption('attr', ['data-char-min' => 900, 'data-char-max' => 1200, 'rows' => 8])
            ->hideOnIndex();

        // --- Page Référencement Local ---
        yield FormField::addPanel('Page : Référencement Local')
            ->setIcon('fa fa-search-location')
            ->setHelp('/referencement-local-{slug}')
            ->onlyOnForms();

        yield TextField::new('titleReferencement', 'Title SEO')
            ->setRequired(false)
            ->setHelp('50-60 car. Vide = title auto.')
            ->setFormTypeOption('attr', ['data-char-min' => 50, 'data-char-max' => 65, 'maxlength' => 70])
            ->hideOnIndex();

        yield TextareaField::new('descriptionReferencement', 'Description courte')
            ->setRequired(false)
            ->setHelp('Hero + meta description. 120-145 car. Vide = description par défaut.')
            ->setFormTypeOption('attr', ['data-char-min' => 120, 'data-char-max' => 155, 'rows' => 3])
            ->hideOnIndex();

        yield TextareaField::new('descriptionReferencementLong', 'Description longue')
            ->setRequired(false)
            ->setHelp('Texte de présentation. 900-1200 car. Vide = texte générique.')
            ->setFormTypeOption('attr', ['data-char-min' => 900, 'data-char-max' => 1200, 'rows' => 8])
            ->hideOnIndex();

        // --- Métadonnées ---
        yield FormField::addPanel('Configuration')
            ->setIcon('fa fa-cog')
            ->onlyOnForms();

        yield TextField::new('lastOptimizedLabel', 'Dernière optimisation')
            ->formatValue(function ($value, DepartmentPage $dept) {
                $date = $dept->getLastOptimizedAt();
                if ($date === null) {
                    return '<span style="color:#9ca3af">jamais</span>';
                }

                return $date->format('d/m/Y');
            })
            ->setVirtual(true)
            ->renderAsHtml()
            ->onlyOnIndex();

        yield BooleanField::new('isActive', 'Actif')
            ->renderAsSwitch(true)
            ->setHelp('Seuls les départements actifs génèrent des pages');

        // URLs des 4 landing pages + bouton "Optimisé" (sur index seulement)
        if ($pageName === Crud::PAGE_INDEX) {
            $csrfManager = $this->csrfTokenManager;
            yield TextField::new('pageUrls', 'Pages')
                ->formatValue(function ($value, DepartmentPage $dept) use ($csrfManager) {
                    if (!$dept->isActive()) {
                        return '-';
                    }
                    $slug = $dept->getSlug();
                    $base = 'https://alre-web.bzh';
                    $id = $dept->getId();
                    $token = $csrfManager->getToken('dept-optimize-' . $id)->getValue();

                    return sprintf(
                        '<a href="%1$s/developpeur-web-%2$s" target="_blank">%1$s/developpeur-web-%2$s</a><br>'
                        . '<a href="%1$s/creation-site-internet-%2$s" target="_blank">%1$s/creation-site-internet-%2$s</a><br>'
                        . '<a href="%1$s/agence-web-%2$s" target="_blank">%1$s/agence-web-%2$s</a><br>'
                        . '<a href="%1$s/referencement-local-%2$s" target="_blank">%1$s/referencement-local-%2$s</a><br>'
                        . '<button type="button" class="btn btn-sm btn-outline-success mt-1 dept-mark-optimized" '
                        . 'data-dept-id="%3$d" data-token="%4$s" style="font-size:0.75rem">'
                        . '<i class="fas fa-check"></i> Optimisé</button>'
                        . '<span class="dept-optimized-result ms-2" data-dept-id="%3$d" style="font-size:0.75rem"></span>',
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
