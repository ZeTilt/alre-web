<?php

namespace App\Controller\Admin;

use App\Entity\Event;
use App\Filter\ShowPastEventsFilter;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ColorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\HttpFoundation\RequestStack;

class EventCrudController extends AbstractCrudController
{
    public function __construct(
        private RequestStack $requestStack
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Event::class;
    }

    public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): QueryBuilder
    {
        $qb = parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters);

        // Eager load relations to avoid N+1 queries
        $qb->leftJoin('entity.eventType', 'et')->addSelect('et')
           ->leftJoin('entity.client', 'c')->addSelect('c');

        // Hide past events by default unless "showPast" filter is enabled
        $request = $this->requestStack->getCurrentRequest();
        $showPast = $request?->query->all('filters')['showPast'] ?? null;

        if ($showPast !== '1') {
            $qb->andWhere('entity.startAt >= :today')
               ->setParameter('today', new \DateTimeImmutable('today'));
        }

        return $qb;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Événement')
            ->setEntityLabelInPlural('Événements')
            ->setPageTitle('index', 'Calendrier - Liste des événements')
            ->setPageTitle('new', 'Nouvel événement')
            ->setPageTitle('edit', 'Modifier l\'événement')
            ->setDefaultSort(['startAt' => 'ASC'])
            ->setPaginatorPageSize(20)
            ->setDateTimeFormat('dd/MM/yyyy HH:mm');
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->update(Crud::PAGE_INDEX, Action::NEW, function (Action $action) {
                return $action->setLabel('Nouvel événement')->setIcon('fa fa-plus');
            });
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(ShowPastEventsFilter::new('showPast', 'Afficher les événements passés'))
            ->add(EntityFilter::new('eventType')->setLabel('Type'))
            ->add(DateTimeFilter::new('startAt')->setLabel('Date'));
    }

    public function configureFields(string $pageName): iterable
    {

        yield TextField::new('title', 'Titre')
            ->setRequired(true)
            ->setColumns(12);

        yield AssociationField::new('eventType', 'Type')
            ->setQueryBuilder(fn ($qb) => $qb->orderBy('entity.position', 'ASC')->addOrderBy('entity.name', 'ASC'))
            ->setColumns(6)
            ->formatValue(function ($value, $entity) {
                if (!$value) {
                    return '<span class="badge" style="background-color: #8E8E93; color: white;">Non défini</span>';
                }
                $color = $entity->getEventType()->getColor();
                return sprintf(
                    '<span class="badge" style="background-color: %s; color: white;">%s</span>',
                    $color,
                    htmlspecialchars($value)
                );
            })
            ->renderAsHtml();

        yield BooleanField::new('allDay', 'Journée entière')
            ->setColumns(6)
            ->hideOnIndex();

        yield DateTimeField::new('startAt', 'Début')
            ->setRequired(true)
            ->setColumns(6);

        yield DateTimeField::new('endAt', 'Fin')
            ->setColumns(6)
            ->hideOnIndex();

        yield TextField::new('location', 'Lieu')
            ->setColumns(6)
            ->hideOnIndex();

        yield AssociationField::new('client', 'Client lié')
            ->setColumns(6)
            ->hideOnIndex();

        yield TextareaField::new('description', 'Description')
            ->setColumns(12)
            ->hideOnIndex();

        yield ColorField::new('color', 'Couleur personnalisée')
            ->setColumns(6)
            ->hideOnIndex()
            ->setHelp('Laisser vide pour utiliser la couleur du type');
    }
}
