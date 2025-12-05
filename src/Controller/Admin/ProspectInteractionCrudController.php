<?php

namespace App\Controller\Admin;

use App\Entity\ProspectInteraction;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;

class ProspectInteractionCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return ProspectInteraction::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Interaction')
            ->setEntityLabelInPlural('Interactions')
            ->setPageTitle('index', 'Historique des interactions')
            ->setPageTitle('new', 'Nouvelle interaction')
            ->setPageTitle('edit', 'Modifier l\'interaction')
            ->setPageTitle('detail', 'Détails de l\'interaction')
            ->setDefaultSort(['createdAt' => 'DESC'])
            ->setPaginatorPageSize(30);
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            AssociationField::new('prospect', 'Prospect')
                ->formatValue(function ($value, $entity) {
                    if ($value && $this->getContext()->getCrud()->getCurrentPage() === Crud::PAGE_INDEX) {
                        $url = $this->generateUrl('admin', [
                            'crudAction' => 'detail',
                            'crudControllerFqcn' => ProspectCrudController::class,
                            'entityId' => $entity->getProspect()->getId()
                        ]);
                        return sprintf('<a href="%s">%s</a>', $url, htmlspecialchars($value));
                    }
                    return $value;
                })
                ->renderAsHtml(),
            AssociationField::new('contact', 'Contact')
                ->hideOnIndex(),
            ChoiceField::new('type', 'Type')
                ->setChoices(ProspectInteraction::getTypeChoices())
                ->renderAsBadges([
                    ProspectInteraction::TYPE_EMAIL => 'info',
                    ProspectInteraction::TYPE_PHONE => 'success',
                    ProspectInteraction::TYPE_LINKEDIN => 'primary',
                    ProspectInteraction::TYPE_FACEBOOK => 'primary',
                    ProspectInteraction::TYPE_MEETING => 'warning',
                    ProspectInteraction::TYPE_VIDEO_CALL => 'warning',
                    ProspectInteraction::TYPE_SMS => 'secondary',
                    ProspectInteraction::TYPE_OTHER => 'dark',
                ]),
            ChoiceField::new('direction', 'Direction')
                ->setChoices(ProspectInteraction::getDirectionChoices())
                ->renderAsBadges([
                    ProspectInteraction::DIRECTION_SENT => 'success',
                    ProspectInteraction::DIRECTION_RECEIVED => 'info',
                ]),
            TextField::new('subject', 'Sujet'),
            TextareaField::new('content', 'Contenu')
                ->hideOnIndex(),
            TextareaField::new('notes', 'Notes')
                ->hideOnIndex(),
            DateTimeField::new('scheduledAt', 'Programmé pour')
                ->hideOnIndex(),
            DateTimeField::new('createdAt', 'Date')
                ->hideOnForm(),
        ];
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add('prospect')
            ->add(ChoiceFilter::new('type')->setChoices(ProspectInteraction::getTypeChoices()))
            ->add(ChoiceFilter::new('direction')->setChoices(ProspectInteraction::getDirectionChoices()))
            ->add(DateTimeFilter::new('createdAt'));
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->reorder(Crud::PAGE_INDEX, [Action::DETAIL, Action::EDIT, Action::DELETE]);
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof ProspectInteraction) {
            // Update lastContactAt on the prospect
            $prospect = $entityInstance->getProspect();
            if ($prospect) {
                $prospect->setLastContactAt(new \DateTimeImmutable());
            }
        }

        parent::persistEntity($entityManager, $entityInstance);
    }
}
