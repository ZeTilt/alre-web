<?php

namespace App\Controller\Admin;

use App\Entity\GoogleReview;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Response;

class GoogleReviewCrudController extends AbstractCrudController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private AdminUrlGenerator $adminUrlGenerator,
    ) {}

    public static function getEntityFqcn(): string
    {
        return GoogleReview::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Avis Google')
            ->setEntityLabelInPlural('Avis Google')
            ->setPageTitle('index', 'Modération des avis Google')
            ->setPageTitle('detail', fn (GoogleReview $review) => sprintf('Avis de %s', $review->getAuthorName()))
            ->setDefaultSort(['reviewDate' => 'DESC'])
            ->setSearchFields(['authorName', 'comment'])
            ->showEntityActionsInlined()
            ->setPaginatorPageSize(20);
    }

    public function configureActions(Actions $actions): Actions
    {
        $approveAction = Action::new('approve', 'Approuver', 'fa fa-check')
            ->linkToCrudAction('approveReview')
            ->setCssClass('btn btn-success btn-sm')
            ->displayIf(fn (GoogleReview $review) => !$review->isApproved());

        $rejectAction = Action::new('reject', 'Rejeter', 'fa fa-times')
            ->linkToCrudAction('rejectReview')
            ->setCssClass('btn btn-danger btn-sm')
            ->displayIf(fn (GoogleReview $review) => !$review->isRejected());

        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_INDEX, $approveAction)
            ->add(Crud::PAGE_INDEX, $rejectAction)
            ->add(Crud::PAGE_DETAIL, $approveAction)
            ->add(Crud::PAGE_DETAIL, $rejectAction)
            ->disable(Action::NEW, Action::DELETE)
            ->reorder(Crud::PAGE_INDEX, [Action::DETAIL, 'approve', 'reject', Action::EDIT]);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(ChoiceFilter::new('status', 'Statut')
                ->setChoices([
                    'En attente' => 'pending',
                    'Approuvé' => 'approved',
                    'Rejeté' => 'rejected',
                ])
            );
    }

    public function createIndexQueryBuilder(
        SearchDto $searchDto,
        EntityDto $entityDto,
        FieldCollection $fields,
        FilterCollection $filters
    ): QueryBuilder {
        $qb = parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters);

        // Handle custom status filter
        $request = $this->container->get('request_stack')->getCurrentRequest();
        $filterData = $request?->query->all('filters') ?? [];

        if (isset($filterData['status']) && !empty($filterData['status']['value'])) {
            $status = $filterData['status']['value'];
            $alias = $qb->getRootAliases()[0];

            match ($status) {
                'pending' => $qb->andWhere("$alias.isApproved = false AND $alias.rejectedAt IS NULL"),
                'approved' => $qb->andWhere("$alias.isApproved = true"),
                'rejected' => $qb->andWhere("$alias.rejectedAt IS NOT NULL"),
                default => null,
            };
        }

        return $qb;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id', 'ID')
            ->hideOnForm()
            ->hideOnIndex();

        yield TextField::new('authorName', 'Auteur')
            ->formatValue(function ($value, GoogleReview $entity) use ($pageName) {
                if ($pageName === Crud::PAGE_INDEX) {
                    $url = $this->adminUrlGenerator
                        ->setController(self::class)
                        ->setAction(Action::DETAIL)
                        ->setEntityId($entity->getId())
                        ->generateUrl();
                    return sprintf('<a href="%s"><strong>%s</strong></a>', $url, htmlspecialchars($value));
                }
                return $value;
            })
            ->renderAsHtml()
            ->setFormTypeOption('disabled', true);

        yield IntegerField::new('rating', 'Note')
            ->formatValue(function ($value, GoogleReview $entity) {
                $rating = $entity->getRating();
                $stars = '';
                for ($i = 1; $i <= 5; $i++) {
                    if ($i <= $rating) {
                        $stars .= '<i class="fas fa-star" style="color: #f59e0b;"></i>';
                    } else {
                        $stars .= '<i class="far fa-star" style="color: #d1d5db;"></i>';
                    }
                }
                return $stars;
            })
            ->renderAsHtml()
            ->onlyOnIndex();

        if ($pageName === Crud::PAGE_INDEX) {
            yield TextField::new('commentExcerpt', 'Commentaire')
                ->formatValue(fn ($value, GoogleReview $entity) => $entity->getCommentExcerpt(80))
                ->setFormTypeOption('mapped', false);
        } else {
            yield TextareaField::new('comment', 'Commentaire')
                ->setFormTypeOption('disabled', true)
                ->hideOnIndex();
        }

        yield TextField::new('status', 'Statut')
            ->formatValue(function ($value, GoogleReview $entity) {
                if ($entity->isApproved()) {
                    return '<span class="badge" style="background: #d1fae5; color: #065f46; padding: 0.25rem 0.75rem; border-radius: 9999px;"><i class="fas fa-check"></i> Approuvé</span>';
                } elseif ($entity->isRejected()) {
                    return '<span class="badge" style="background: #fee2e2; color: #991b1b; padding: 0.25rem 0.75rem; border-radius: 9999px;"><i class="fas fa-times"></i> Rejeté</span>';
                } else {
                    return '<span class="badge" style="background: #fef3c7; color: #92400e; padding: 0.25rem 0.75rem; border-radius: 9999px;"><i class="fas fa-clock"></i> En attente</span>';
                }
            })
            ->renderAsHtml()
            ->setFormTypeOption('mapped', false)
            ->onlyOnIndex();

        yield DateTimeField::new('reviewDate', 'Date avis')
            ->setFormat('dd/MM/yyyy')
            ->setFormTypeOption('disabled', true);

        yield DateTimeField::new('rejectedAt', 'Rejeté le')
            ->setFormat('dd/MM/yyyy HH:mm')
            ->hideOnIndex()
            ->hideOnForm();

        yield DateTimeField::new('createdAt', 'Importé le')
            ->setFormat('dd/MM/yyyy HH:mm')
            ->hideOnIndex()
            ->hideOnForm();
    }

    public function approveReview(): Response
    {
        $review = $this->getContext()?->getEntity()?->getInstance();

        if ($review instanceof GoogleReview) {
            $review->setIsApproved(true);
            $this->entityManager->flush();
            $this->addFlash('success', sprintf('L\'avis de "%s" a été approuvé.', $review->getAuthorName()));
        }

        return $this->redirect(
            $this->adminUrlGenerator
                ->setController(self::class)
                ->setAction(Action::INDEX)
                ->generateUrl()
        );
    }

    public function rejectReview(): Response
    {
        $review = $this->getContext()?->getEntity()?->getInstance();

        if ($review instanceof GoogleReview) {
            $review->reject();
            $this->entityManager->flush();
            $this->addFlash('warning', sprintf('L\'avis de "%s" a été rejeté.', $review->getAuthorName()));
        }

        return $this->redirect(
            $this->adminUrlGenerator
                ->setController(self::class)
                ->setAction(Action::INDEX)
                ->generateUrl()
        );
    }
}
