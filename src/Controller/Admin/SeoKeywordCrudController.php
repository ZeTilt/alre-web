<?php

namespace App\Controller\Admin;

use App\Entity\SeoKeyword;
use App\Repository\SeoKeywordRepository;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Response;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\UrlField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;

class SeoKeywordCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return SeoKeyword::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Mot-clé SEO')
            ->setEntityLabelInPlural('Mots-clés SEO')
            ->setPageTitle('index', 'Gestion des mots-clés SEO')
            ->setPageTitle('new', 'Ajouter un mot-clé')
            ->setPageTitle('edit', 'Modifier le mot-clé')
            ->setDefaultSort(['keyword' => 'ASC'])
            ->setSearchFields(['keyword', 'targetUrl'])
            ->showEntityActionsInlined();
    }

    public function configureActions(Actions $actions): Actions
    {
        $setHigh = Action::new('setRelevanceHigh', 'Haute', 'fa fa-arrow-up')
            ->linkToCrudAction('setRelevanceHigh')
            ->setCssClass('btn btn-sm btn-success')
            ->displayIf(fn (SeoKeyword $k) => $k->getRelevanceLevel() !== SeoKeyword::RELEVANCE_HIGH);

        $setMedium = Action::new('setRelevanceMedium', 'Moyenne', 'fa fa-minus')
            ->linkToCrudAction('setRelevanceMedium')
            ->setCssClass('btn btn-sm btn-warning')
            ->displayIf(fn (SeoKeyword $k) => $k->getRelevanceLevel() !== SeoKeyword::RELEVANCE_MEDIUM);

        $setLow = Action::new('setRelevanceLow', 'Basse', 'fa fa-arrow-down')
            ->linkToCrudAction('setRelevanceLow')
            ->setCssClass('btn btn-sm btn-secondary')
            ->displayIf(fn (SeoKeyword $k) => $k->getRelevanceLevel() !== SeoKeyword::RELEVANCE_LOW);

        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_INDEX, $setHigh)
            ->add(Crud::PAGE_INDEX, $setMedium)
            ->add(Crud::PAGE_INDEX, $setLow)
            ->reorder(Crud::PAGE_INDEX, ['setRelevanceHigh', 'setRelevanceMedium', 'setRelevanceLow', Action::DETAIL, Action::EDIT, Action::DELETE]);
    }

    public function setRelevanceHigh(AdminContext $context, EntityManagerInterface $em, AdminUrlGenerator $urlGenerator): Response
    {
        return $this->setRelevance($context, $em, $urlGenerator, SeoKeyword::RELEVANCE_HIGH);
    }

    public function setRelevanceMedium(AdminContext $context, EntityManagerInterface $em, AdminUrlGenerator $urlGenerator): Response
    {
        return $this->setRelevance($context, $em, $urlGenerator, SeoKeyword::RELEVANCE_MEDIUM);
    }

    public function setRelevanceLow(AdminContext $context, EntityManagerInterface $em, AdminUrlGenerator $urlGenerator): Response
    {
        return $this->setRelevance($context, $em, $urlGenerator, SeoKeyword::RELEVANCE_LOW);
    }

    private function setRelevance(AdminContext $context, EntityManagerInterface $em, AdminUrlGenerator $urlGenerator, string $level): Response
    {
        /** @var SeoKeyword $keyword */
        $keyword = $context->getEntity()->getInstance();
        $keyword->setRelevanceLevel($level);
        $em->flush();

        $this->addFlash('success', sprintf('Pertinence de "%s" mise à jour.', $keyword->getKeyword()));

        return $this->redirect($urlGenerator->setAction(Action::INDEX)->generateUrl());
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(BooleanFilter::new('isActive', 'Actif'))
            ->add(ChoiceFilter::new('relevanceLevel', 'Pertinence')->setChoices(SeoKeyword::getRelevanceLevelChoices()))
            ->add(ChoiceFilter::new('source', 'Source')->setChoices(SeoKeyword::getSourceChoices()));
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id', 'ID')
            ->hideOnForm();

        yield TextField::new('keyword', 'Mot-clé')
            ->setRequired(true)
            ->setHelp('Le mot-clé à suivre dans Google Search Console');

        yield UrlField::new('targetUrl', 'URL cible')
            ->setRequired(false)
            ->setHelp('URL spécifique à suivre pour ce mot-clé (optionnel)')
            ->hideOnIndex();

        yield BooleanField::new('isActive', 'Actif')
            ->renderAsSwitch(true)
            ->setHelp('Seuls les mots-clés actifs sont synchronisés');

        yield ChoiceField::new('relevanceLevel', 'Pertinence')
            ->setChoices(SeoKeyword::getRelevanceLevelChoices())
            ->renderAsBadges([
                SeoKeyword::RELEVANCE_HIGH => 'success',
                SeoKeyword::RELEVANCE_MEDIUM => 'warning',
                SeoKeyword::RELEVANCE_LOW => 'secondary',
            ])
            ->setHelp('Niveau de pertinence pour votre activité');

        yield TextField::new('sourceLabel', 'Source')
            ->hideOnForm()
            ->formatValue(function ($value, $entity) {
                $badge = $entity->isManual() ? 'primary' : 'info';
                return sprintf('<span class="badge bg-%s">%s</span>', $badge, $value);
            });

        // Afficher la dernière position sur la page index
        if ($pageName === Crud::PAGE_INDEX || $pageName === Crud::PAGE_DETAIL) {
            yield NumberField::new('latestPosition.position', 'Position')
                ->setNumDecimals(1)
                ->formatValue(function ($value, $entity) {
                    $latest = $entity->getLatestPosition();
                    if (!$latest) {
                        return '-';
                    }
                    $pos = $latest->getPosition();
                    if ($pos <= 10) {
                        return sprintf('<span style="color: #10b981; font-weight: bold;">%.1f</span>', $pos);
                    } elseif ($pos <= 20) {
                        return sprintf('<span style="color: #f59e0b;">%.1f</span>', $pos);
                    } else {
                        return sprintf('<span style="color: #ef4444;">%.1f</span>', $pos);
                    }
                })
                ->onlyOnIndex();

            yield NumberField::new('latestPosition.clicks', 'Clics')
                ->formatValue(function ($value, $entity) {
                    $latest = $entity->getLatestPosition();
                    return $latest ? $latest->getClicks() : '-';
                })
                ->onlyOnIndex();

            yield NumberField::new('latestPosition.impressions', 'Impressions')
                ->formatValue(function ($value, $entity) {
                    $latest = $entity->getLatestPosition();
                    return $latest ? number_format($latest->getImpressions(), 0, ',', ' ') : '-';
                })
                ->onlyOnIndex();
        }

        yield DateTimeField::new('lastSyncAt', 'Dernière sync')
            ->setFormat('dd/MM/yyyy HH:mm')
            ->hideOnForm();

        yield DateTimeField::new('createdAt', 'Créé le')
            ->setFormat('dd/MM/yyyy')
            ->hideOnForm()
            ->hideOnIndex();
    }
}
