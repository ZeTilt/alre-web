<?php

namespace App\Controller\Admin;

use App\Entity\ProspectFollowUp;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;

class ProspectFollowUpCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return ProspectFollowUp::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Relance')
            ->setEntityLabelInPlural('Relances')
            ->setPageTitle('index', 'Relances programmées')
            ->setPageTitle('new', 'Nouvelle relance')
            ->setPageTitle('edit', 'Modifier la relance')
            ->setPageTitle('detail', 'Détails de la relance')
            ->setDefaultSort(['dueAt' => 'ASC'])
            ->setPaginatorPageSize(30);
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            TextField::new('urgencyBadge', 'Urgence')
                ->formatValue(function ($value, $entity) {
                    $urgency = $entity->getUrgencyLevel();
                    $labels = [
                        ProspectFollowUp::URGENCY_OVERDUE => 'En retard',
                        ProspectFollowUp::URGENCY_SOON => 'Bientôt',
                        ProspectFollowUp::URGENCY_NORMAL => 'Normal',
                        ProspectFollowUp::URGENCY_COMPLETED => 'Terminé',
                    ];
                    $badgeClass = $entity->getUrgencyBadgeClass();
                    return sprintf('<span class="badge badge-%s">%s</span>', $badgeClass, $labels[$urgency] ?? $urgency);
                })
                ->renderAsHtml()
                ->onlyOnIndex(),
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
            TextField::new('title', 'Titre'),
            TextareaField::new('description', 'Description')
                ->hideOnIndex(),
            DateField::new('dueAt', 'Échéance'),
            ChoiceField::new('priority', 'Priorité')
                ->setChoices(ProspectFollowUp::getPriorityChoices())
                ->renderAsBadges([
                    ProspectFollowUp::PRIORITY_LOW => 'secondary',
                    ProspectFollowUp::PRIORITY_MEDIUM => 'warning',
                    ProspectFollowUp::PRIORITY_HIGH => 'danger',
                ]),
            BooleanField::new('isCompleted', 'Terminée')
                ->renderAsSwitch(true),
            DateTimeField::new('completedAt', 'Terminée le')
                ->hideOnForm()
                ->hideOnIndex(),
            DateTimeField::new('createdAt', 'Créée le')
                ->hideOnForm()
                ->hideOnIndex(),
        ];
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add('prospect')
            ->add(ChoiceFilter::new('priority')->setChoices(ProspectFollowUp::getPriorityChoices()))
            ->add(BooleanFilter::new('isCompleted'))
            ->add(DateTimeFilter::new('dueAt'));
    }

    public function configureActions(Actions $actions): Actions
    {
        $markComplete = Action::new('markComplete', 'Marquer terminée')
            ->linkToCrudAction('markComplete')
            ->setIcon('fas fa-check')
            ->addCssClass('btn btn-sm btn-success')
            ->displayIf(fn($entity) => !$entity->isCompleted());

        $markPending = Action::new('markPending', 'Réactiver')
            ->linkToCrudAction('markPending')
            ->setIcon('fas fa-undo')
            ->addCssClass('btn btn-sm btn-warning')
            ->displayIf(fn($entity) => $entity->isCompleted());

        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_INDEX, $markComplete)
            ->add(Crud::PAGE_INDEX, $markPending)
            ->add(Crud::PAGE_DETAIL, $markComplete)
            ->add(Crud::PAGE_DETAIL, $markPending)
            ->reorder(Crud::PAGE_INDEX, [Action::DETAIL, 'markComplete', 'markPending', Action::EDIT, Action::DELETE]);
    }

    public function markComplete(EntityManagerInterface $entityManager): \Symfony\Component\HttpFoundation\RedirectResponse
    {
        $followUp = $this->getContext()->getEntity()->getInstance();
        $followUp->complete();
        $entityManager->flush();

        $this->addFlash('success', 'Relance marquée comme terminée.');

        return $this->redirectToRoute('admin', [
            'crudAction' => 'index',
            'crudControllerFqcn' => self::class
        ]);
    }

    public function markPending(EntityManagerInterface $entityManager): \Symfony\Component\HttpFoundation\RedirectResponse
    {
        $followUp = $this->getContext()->getEntity()->getInstance();
        $followUp->setIsCompleted(false);
        $followUp->setCompletedAt(null);
        $entityManager->flush();

        $this->addFlash('success', 'Relance réactivée.');

        return $this->redirectToRoute('admin', [
            'crudAction' => 'index',
            'crudControllerFqcn' => self::class
        ]);
    }
}
