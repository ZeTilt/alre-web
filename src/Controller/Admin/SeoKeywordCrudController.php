<?php

namespace App\Controller\Admin;

use App\Entity\SeoKeyword;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Orm\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\UrlField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

class SeoKeywordCrudController extends AbstractCrudController
{
    public function __construct(
        private CsrfTokenManagerInterface $csrfTokenManager,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return SeoKeyword::class;
    }

    private function generateCsrfToken(int $id): string
    {
        return $this->csrfTokenManager->getToken('seo-score-' . $id)->getValue();
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
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL);
    }

    public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): QueryBuilder
    {
        // Mapper les champs de tri custom vers les sous-requêtes scalaires
        $sortFieldMap = [
            'latestPosition.position' => 'sort_position',
            'latestPosition.clicks' => 'sort_clicks',
            'latestPosition.impressions' => 'sort_impressions',
        ];

        $sort = $searchDto->getSort();
        $customSort = null;

        if (!empty($sort)) {
            $sortField = array_key_first($sort);
            if (isset($sortFieldMap[$sortField])) {
                $customSort = [$sortFieldMap[$sortField], $sort[$sortField]];
                // Recréer SearchDto sans le tri custom pour éviter une erreur dans le QB par défaut
                $searchDto = new SearchDto(
                    $searchDto->getRequest(),
                    $searchDto->getSearchableProperties(),
                    $searchDto->getQuery(),
                    ['keyword' => 'ASC'],
                    [],
                    $searchDto->getAppliedFilters(),
                );
            }
        }

        $qb = $this->container->get(EntityRepository::class)
            ->createQueryBuilder($searchDto, $entityDto, $fields, $filters);

        // Sous-requêtes scalaires pour le tri : on récupère les données de la date la plus récente
        $qb->addSelect('(SELECT lp1.position FROM App\Entity\SeoPosition lp1 WHERE lp1.keyword = entity.id AND lp1.date = (SELECT MAX(lp1b.date) FROM App\Entity\SeoPosition lp1b WHERE lp1b.keyword = entity.id)) AS HIDDEN sort_position');
        $qb->addSelect('(SELECT lp2.clicks FROM App\Entity\SeoPosition lp2 WHERE lp2.keyword = entity.id AND lp2.date = (SELECT MAX(lp2b.date) FROM App\Entity\SeoPosition lp2b WHERE lp2b.keyword = entity.id)) AS HIDDEN sort_clicks');
        $qb->addSelect('(SELECT lp3.impressions FROM App\Entity\SeoPosition lp3 WHERE lp3.keyword = entity.id AND lp3.date = (SELECT MAX(lp3b.date) FROM App\Entity\SeoPosition lp3b WHERE lp3b.keyword = entity.id)) AS HIDDEN sort_impressions');

        if ($customSort) {
            $qb->resetDQLPart('orderBy');
            $qb->orderBy($customSort[0], $customSort[1]);
        }

        return $qb;
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(BooleanFilter::new('isActive', 'Actif'))
            ->add(ChoiceFilter::new('relevanceScore', 'Score')->setChoices([
                '5 ★' => 5, '4 ★' => 4, '3 ★' => 3, '2 ★' => 2, '1 ★' => 1, 'Non scoré' => 0,
            ]))
            ->add(ChoiceFilter::new('source', 'Source')->setChoices(SeoKeyword::getSourceChoices()));
    }

    public function configureFields(string $pageName): iterable
    {

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

        yield IntegerField::new('relevanceScore', 'Score')
            ->setHelp('Score de pertinence (0-5 étoiles)')
            ->formatValue(function ($value, $entity) {
                $score = $entity->getRelevanceScore();
                $id = $entity->getId();
                $stars = '<span class="seo-star-group" data-keyword-id="' . $id . '" data-score="' . $score . '" data-url="/saeiblauhjc/seo-keyword/' . $id . '/set-score" data-token="' . $this->generateCsrfToken($id) . '" style="white-space: nowrap;">';
                for ($i = 1; $i <= 5; $i++) {
                    $filled = $i <= $score ? 'fas' : 'far';
                    $color = $i <= $score ? '#f59e0b' : '#d1d5db';
                    $stars .= '<i class="' . $filled . ' fa-star seo-star" data-value="' . $i . '" style="color: ' . $color . '; cursor: pointer; font-size: 0.85rem; padding: 0 1px;"></i>';
                }
                if ($score === 0) {
                    $stars .= ' <span style="color: #9ca3af; font-size: 0.65rem;">?</span>';
                }
                $stars .= '</span>';
                return $stars;
            })
            ->onlyOnIndex();

        yield IntegerField::new('relevanceScore', 'Score (0-5)')
            ->setHelp('0 = non scoré, 1 = bruit, 5 = prioritaire')
            ->onlyOnForms();

        yield TextField::new('sourceLabel', 'Source')
            ->hideOnForm()
            ->hideOnIndex()
            ->formatValue(function ($value, $entity) {
                $badge = $entity->isManual() ? 'primary' : 'info';
                return sprintf('<span class="badge bg-%s">%s</span>', $badge, $value);
            });

        // Afficher la dernière position sur la page index
        if ($pageName === Crud::PAGE_INDEX || $pageName === Crud::PAGE_DETAIL) {
            yield NumberField::new('latestPosition.position', 'Position')
                ->setNumDecimals(1)
                ->setSortable(true)
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
                ->setSortable(true)
                ->formatValue(function ($value, $entity) {
                    $latest = $entity->getLatestPosition();
                    return $latest ? $latest->getClicks() : '-';
                })
                ->onlyOnIndex();

            yield NumberField::new('latestPosition.impressions', 'Impressions')
                ->setSortable(true)
                ->formatValue(function ($value, $entity) {
                    $latest = $entity->getLatestPosition();
                    return $latest ? number_format($latest->getImpressions(), 0, ',', ' ') : '-';
                })
                ->onlyOnIndex();
        }

        yield DateTimeField::new('lastSeenInGsc', 'Dernière impression')
            ->setFormat('dd/MM/yyyy')
            ->hideOnForm();

        yield DateTimeField::new('createdAt', 'Créé le')
            ->setFormat('dd/MM/yyyy')
            ->hideOnForm()
            ->hideOnIndex();
    }
}
