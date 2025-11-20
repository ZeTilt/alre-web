<?php

namespace App\Controller\Admin;

use App\Entity\Expense;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;

class ExpenseCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Expense::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Dépense')
            ->setEntityLabelInPlural('Dépenses')
            ->setDefaultSort(['dateExpense' => 'DESC'])
            ->setSearchFields(['title', 'description', 'category'])
            ->setPageTitle('index', 'Gestion des dépenses')
            ->setPageTitle('new', 'Nouvelle dépense')
            ->setPageTitle('edit', 'Modifier la dépense')
            ->setPageTitle('detail', 'Détail de la dépense');
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(ChoiceFilter::new('category', 'Catégorie')
                ->setChoices(Expense::CATEGORIES))
            ->add(DateTimeFilter::new('dateExpense', 'Date de dépense'));
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id', 'ID')
            ->onlyOnIndex();

        yield TextField::new('title', 'Titre')
            ->setRequired(true)
            ->setHelp('Nom court de la dépense')
            ->setColumns(6);

        yield ChoiceField::new('category', 'Catégorie')
            ->setChoices(array_flip(Expense::CATEGORIES))
            ->setRequired(true)
            ->setColumns(6);

        yield MoneyField::new('amount', 'Montant')
            ->setCurrency('EUR')
            ->setRequired(true)
            ->setStoredAsCents(false)
            ->setColumns(6);

        yield DateField::new('dateExpense', 'Date de dépense')
            ->setRequired(true)
            ->setColumns(6)
            ->setHelp('Pour les dépenses récurrentes, c\'est la date de la première occurrence');

        yield ChoiceField::new('recurrence', 'Récurrence')
            ->setChoices(array_flip(Expense::RECURRENCES))
            ->setRequired(true)
            ->setColumns(4)
            ->setHelp('Ponctuelle = une seule fois, Mensuelle/Annuelle = abonnement');

        yield DateField::new('startDate', 'Date de début')
            ->setRequired(false)
            ->setColumns(4)
            ->setHelp('Pour les dépenses récurrentes, date de début de la récurrence')
            ->hideOnIndex();

        yield DateField::new('endDate', 'Date de fin')
            ->setRequired(false)
            ->setColumns(4)
            ->setHelp('Optionnel : date de fin de la récurrence')
            ->hideOnIndex();

        yield TextareaField::new('description', 'Description')
            ->setRequired(false)
            ->setHelp('Détails ou notes sur cette dépense')
            ->hideOnIndex();

        yield DateField::new('createdAt', 'Créé le')
            ->onlyOnDetail()
            ->setFormTypeOption('disabled', true);

        yield DateField::new('updatedAt', 'Modifié le')
            ->onlyOnDetail()
            ->setFormTypeOption('disabled', true);
    }
}
