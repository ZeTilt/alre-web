<?php

namespace App\Controller\Admin;

use App\Entity\ProspectFollowUp;
use App\Entity\ProspectInteraction;
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
            ->setPaginatorPageSize(30)
            ->addFormTheme('admin/form/prospect_contact_filter.html.twig');
    }

    public function configureFields(string $pageName): iterable
    {
        $urgencyField = TextField::new('urgencyLabel', 'Urgence')
            ->formatValue(function ($value, $entity) {
                $badgeClass = $entity->getUrgencyBadgeClass();
                return sprintf('<span class="badge badge-%s">%s</span>', $badgeClass, $value);
            })
            ->renderAsHtml()
            ->onlyOnIndex();

        return [
            $urgencyField,
            AssociationField::new('prospect', 'Prospect')
                ->renderAsNativeWidget()
                ->setFormTypeOption('attr', ['class' => 'prospect-select', 'data-prospect-filter' => 'source'])
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
                ->renderAsNativeWidget()
                ->setFormTypeOption('attr', ['class' => 'contact-select', 'data-prospect-filter' => 'target'])
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
            TextField::new('subject', 'Sujet'),
            TextareaField::new('content', 'Contenu du message')
                ->hideOnIndex()
                ->setHelp('Le message qui sera envoyé lors de la relance'),
            DateField::new('dueAt', 'Date de relance'),
            BooleanField::new('isCompleted', 'Envoyée')
                ->renderAsSwitch(true),
            DateTimeField::new('completedAt', 'Envoyée le')
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
            ->add(ChoiceFilter::new('type')->setChoices(ProspectInteraction::getTypeChoices()))
            ->add(BooleanFilter::new('isCompleted'))
            ->add(DateTimeFilter::new('dueAt'));
    }

    public function configureActions(Actions $actions): Actions
    {
        $markComplete = Action::new('markComplete', 'Marquer envoyée')
            ->linkToCrudAction('markComplete')
            ->setIcon('fas fa-paper-plane')
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

        // Create an interaction from this follow-up
        $interaction = $followUp->toInteraction();
        $entityManager->persist($interaction);

        // Update prospect's lastContactAt
        $prospect = $followUp->getProspect();
        if ($prospect) {
            $prospect->setLastContactAt(new \DateTimeImmutable());
        }

        $entityManager->flush();

        $this->addFlash('success', 'Relance marquée comme envoyée et ajoutée aux interactions.');

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
