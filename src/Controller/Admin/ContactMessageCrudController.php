<?php

namespace App\Controller\Admin;

use App\Entity\ContactMessage;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TelephoneField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;

class ContactMessageCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return ContactMessage::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Message de contact')
            ->setEntityLabelInPlural('Messages de contact')
            ->setDefaultSort(['createdAt' => 'DESC'])
            ->setPageTitle('index', 'Messages de contact')
            ->setPageTitle('detail', fn (ContactMessage $message) => (string) $message);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(BooleanFilter::new('isRead', 'Lu'))
            ->add(BooleanFilter::new('isArchived', 'Archivé'))
            ->add('projectType')
            ->add('budget');
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            TextField::new('firstName', 'Prénom'),
            TextField::new('lastName', 'Nom'),
            EmailField::new('email', 'Email'),
            TelephoneField::new('phone', 'Téléphone')->hideOnIndex(),
            TextField::new('company', 'Entreprise')->hideOnIndex(),
            ChoiceField::new('projectType', 'Type de projet')
                ->setChoices([
                    'Site Vitrine' => 'vitrine',
                    'E-commerce' => 'ecommerce',
                    'Application sur mesure' => 'sur-mesure',
                    'Refonte' => 'refonte',
                    'Maintenance' => 'maintenance',
                    'Autre' => 'autre'
                ]),
            ChoiceField::new('budget', 'Budget')
                ->setChoices([
                    'Moins de 1 000 €' => 'moins-1000',
                    '1 000 - 2 000 €' => '1000-2000',
                    '2 000 - 5 000 €' => '2000-5000',
                    '5 000 - 10 000 €' => '5000-10000',
                    'Plus de 10 000 €' => 'plus-10000',
                    'Non défini' => 'non-defini'
                ])
                ->hideOnIndex(),
            TextareaField::new('message', 'Message')
                ->hideOnIndex()
                ->setFormTypeOptions(['attr' => ['rows' => 5]]),
            BooleanField::new('rgpdConsent', 'Consentement RGPD')->hideOnIndex(),
            DateTimeField::new('createdAt', 'Date de réception')
                ->setFormat('dd/MM/yyyy HH:mm')
                ->hideOnForm(),
            BooleanField::new('isRead', 'Lu'),
            BooleanField::new('isArchived', 'Archivé')->hideOnIndex(),
        ];
    }
}
