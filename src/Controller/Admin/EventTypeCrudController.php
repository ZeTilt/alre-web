<?php

namespace App\Controller\Admin;

use App\Entity\EventType;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ColorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;

class EventTypeCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return EventType::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Type d\'événement')
            ->setEntityLabelInPlural('Types d\'événements')
            ->setPageTitle('index', 'Types d\'événements')
            ->setPageTitle('new', 'Nouveau type')
            ->setPageTitle('edit', 'Modifier le type')
            ->setDefaultSort(['position' => 'ASC', 'name' => 'ASC'])
            ->setPaginatorPageSize(20);
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->update(Crud::PAGE_INDEX, Action::NEW, function (Action $action) {
                return $action->setLabel('Nouveau type')->setIcon('fa fa-plus');
            });
    }

    public function configureFields(string $pageName): iterable
    {

        yield TextField::new('name', 'Nom')
            ->setRequired(true)
            ->setColumns(6);

        yield ColorField::new('color', 'Couleur')
            ->setRequired(true)
            ->setColumns(6);

        yield IntegerField::new('position', 'Position')
            ->setHelp('Pour l\'ordre d\'affichage (0 = premier)')
            ->setColumns(6);
    }
}
