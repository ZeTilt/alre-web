<?php

namespace App\Controller\Admin;

use App\Entity\Client;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TelephoneField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;

class ClientCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Client::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Client')
            ->setEntityLabelInPlural('Clients')
            ->setPageTitle('index', 'Liste des clients')
            ->setPageTitle('new', 'Créer un client')
            ->setPageTitle('edit', 'Modifier le client')
            ->setPageTitle('detail', 'Détails du client')
            ->setDefaultSort(['name' => 'ASC'])
            ->setPaginatorPageSize(20);
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            TextField::new('name', 'Nom')
                ->formatValue(function ($value, $entity) {
                    if ($this->getContext()->getCrud()->getCurrentPage() === Crud::PAGE_INDEX) {
                        $url = $this->generateUrl('admin', [
                            'crudAction' => 'detail',
                            'crudControllerFqcn' => self::class,
                            'entityId' => $entity->getId()
                        ]);
                        return sprintf('<a href="%s">%s</a>', $url, htmlspecialchars($value));
                    }
                    return $value;
                })
                ->renderAsHtml(),
            ChoiceField::new('type', 'Type')
                ->setChoices(Client::getTypeChoices())
                ->renderAsBadges([
                    Client::TYPE_ENTREPRISE => 'primary',
                    Client::TYPE_ASSOCIATION => 'warning',
                ]),
            TextField::new('companyName', 'Raison sociale')->hideOnIndex(),
            TextField::new('siret', 'SIRET')->hideOnIndex(),
            TextField::new('vatNumber', 'N° TVA')->hideOnIndex(),
            TextField::new('contactFirstName', 'Prénom contact')->hideOnIndex(),
            TextField::new('contactLastName', 'Nom contact')->hideOnIndex(),
            EmailField::new('email', 'Email'),
            TelephoneField::new('phone', 'Téléphone'),
            TextareaField::new('address', 'Adresse')->hideOnIndex(),
            TextField::new('postalCode', 'Code postal')->hideOnIndex(),
            TextField::new('city', 'Ville'),
            TextField::new('country', 'Pays')->hideOnIndex(),
            BooleanField::new('isActive', 'Actif'),
            TextareaField::new('notes', 'Notes')->onlyOnForms(),
        ];
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(ChoiceFilter::new('type')->setChoices(Client::getTypeChoices()))
            ->add(BooleanFilter::new('isActive'))
            ->add('city')
            ->add('country');
    }
}