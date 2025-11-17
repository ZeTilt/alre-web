<?php

namespace App\Controller\Admin;

use App\Entity\Project;
use App\Form\ProjectPartnerType;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\UrlField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;

class ProjectCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Project::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->hideOnForm(),
            TextField::new('title', 'Titre du projet'),
            TextField::new('slug', 'Slug (URL)')
                ->setHelp('Généré automatiquement si laissé vide')
                ->setRequired(false),
            AssociationField::new('client', 'Client')
                ->setHelp('Le client pour lequel le projet a été réalisé')
                ->setRequired(false),
            ChoiceField::new('category', 'Catégorie')
                ->setChoices([
                    'Site Vitrine' => 'vitrine',
                    'E-commerce' => 'ecommerce',
                    'Sur Mesure' => 'sur-mesure',
                    'Application Métier' => 'application',
                    'Refonte' => 'refonte',
                ]),
            TextareaField::new('shortDescription', 'Description courte')
                ->setHelp('Description affichée sur la page portfolio'),
            TextEditorField::new('fullDescription', 'Description complète')->hideOnIndex(),
            ArrayField::new('technologies', 'Technologies utilisées')
                ->setHelp('Ex: Symfony, React, MySQL...'),
            CollectionField::new('projectPartners', 'Partenaires')
                ->setEntryType(ProjectPartnerType::class)
                ->setFormTypeOption('by_reference', false)
                ->setHelp('Ajoutez les partenaires qui ont collaboré sur ce projet')
                ->hideOnIndex()
                ->onlyOnForms(),
            TextEditorField::new('context', 'Contexte & Besoin')->hideOnIndex(),
            TextEditorField::new('solutions', 'Solutions apportées')->hideOnIndex(),
            TextEditorField::new('results', 'Résultats obtenus')->hideOnIndex(),
            UrlField::new('projectUrl', 'URL du projet')->hideOnIndex(),
            IntegerField::new('completionYear', 'Année de réalisation')
                ->setHelp('Ex: 2024')
                ->setRequired(false),
            BooleanField::new('featured', 'Projet mis en avant'),
            BooleanField::new('isPublished', 'Publié'),
        ];
    }
}
