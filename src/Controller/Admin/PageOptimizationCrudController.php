<?php

namespace App\Controller\Admin;

use App\Entity\PageOptimization;
use App\Repository\SeoKeywordRepository;
use App\Service\MainPageKeywordMatcher;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

class PageOptimizationCrudController extends AbstractCrudController
{
    public function __construct(
        private MainPageKeywordMatcher $mainPageKeywordMatcher,
        private CsrfTokenManagerInterface $csrfTokenManager,
        private SeoKeywordRepository $seoKeywordRepository,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return PageOptimization::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Page principale')
            ->setEntityLabelInPlural('Pages principales (SEO)')
            ->setPageTitle('index', 'Gestion des pages principales - SEO')
            ->setPageTitle('new', 'Ajouter une page')
            ->setPageTitle('edit', 'Modifier la page')
            ->setDefaultSort(['url' => 'ASC'])
            ->setSearchFields(['url', 'label'])
            ->showEntityActionsInlined();
    }

    public function configureActions(Actions $actions): Actions
    {
        // Sync pages from keywords on every index page load
        $this->mainPageKeywordMatcher->syncPages();

        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(BooleanFilter::new('isActive', 'Actif'));
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('label', 'Nom')
            ->setRequired(true);

        yield TextField::new('url', 'URL (chemin relatif)')
            ->setRequired(true)
            ->setHelp('Ex: /tarifs, /portfolio/mon-projet');

        yield BooleanField::new('isActive', 'Actif')
            ->renderAsSwitch(true);

        yield TextField::new('lastOptimizedLabel', 'Dernière optimisation')
            ->formatValue(function ($value, PageOptimization $page) {
                $date = $page->getLastOptimizedAt();
                if ($date === null) {
                    return '<span style="color:#9ca3af">jamais</span>';
                }
                return $date->format('d/m/Y');
            })
            ->setVirtual(true)
            ->renderAsHtml()
            ->onlyOnIndex();

        yield DateTimeField::new('lastOptimizedAt', 'Dernière optimisation')
            ->setFormat('dd/MM/yyyy')
            ->hideOnIndex();

        // Bouton "Optimisé" + lien vers la page (sur index seulement)
        if ($pageName === Crud::PAGE_INDEX) {
            $csrfManager = $this->csrfTokenManager;
            yield TextField::new('optimizeAction', 'Actions')
                ->formatValue(function ($value, PageOptimization $page) use ($csrfManager) {
                    if (!$page->isActive()) {
                        return '-';
                    }
                    $id = $page->getId();
                    $token = $csrfManager->getToken('page-optimize-' . $id)->getValue();
                    $base = 'https://alre-web.bzh';

                    return sprintf(
                        '<a href="%s%s" target="_blank" style="font-size:0.75rem; margin-right:0.5rem;">%s%s</a><br>'
                        . '<button type="button" class="btn btn-sm btn-outline-info mt-1 page-mark-optimized" '
                        . 'data-page-id="%d" data-token="%s" style="font-size:0.75rem">'
                        . '<i class="fas fa-check"></i> Optimisé</button>'
                        . '<span class="page-optimized-result ms-2" data-page-id="%d" style="font-size:0.75rem"></span>',
                        $base,
                        $page->getUrl(),
                        $base,
                        $page->getUrl(),
                        $id,
                        $token,
                        $id
                    );
                })
                ->setVirtual(true)
                ->renderAsHtml();
        }

        yield DateTimeField::new('createdAt', 'Créé le')
            ->setFormat('dd/MM/yyyy')
            ->setHelp('Antidater pour que la page apparaisse immédiatement dans le dashboard (filtre 30j)');

        if ($pageName === Crud::PAGE_DETAIL) {
            $repo = $this->seoKeywordRepository;
            yield TextField::new('keywordsList', 'Keywords associés')
                ->formatValue(function ($value, PageOptimization $page) use ($repo) {
                    $fullUrl = 'https://alre-web.bzh' . $page->getUrl();
                    $keywords = $repo->findByTargetUrl($fullUrl);

                    if (empty($keywords)) {
                        return '<em style="color:#9ca3af">Aucun keyword associé</em>';
                    }

                    $html = '<table style="width:100%; border-collapse:collapse; font-size:0.85rem">';
                    $html .= '<thead><tr style="border-bottom:2px solid #dee2e6; text-align:left">'
                        . '<th style="padding:6px 8px">Keyword</th>'
                        . '<th style="padding:6px 8px">Score</th>'
                        . '<th style="padding:6px 8px; text-align:right">Position</th>'
                        . '<th style="padding:6px 8px; text-align:right">Clics</th>'
                        . '<th style="padding:6px 8px; text-align:right">Impressions</th>'
                        . '</tr></thead><tbody>';

                    foreach ($keywords as $kw) {
                        $score = $kw->getRelevanceScore();
                        $stars = str_repeat('★', $score) . str_repeat('☆', 5 - $score);

                        $latest = $kw->getLatestPosition();
                        $pos = $latest ? number_format($latest->getPosition(), 1) : '-';
                        $clicks = $latest ? $latest->getClicks() : '-';
                        $impressions = $latest ? $latest->getImpressions() : '-';

                        $html .= '<tr style="border-bottom:1px solid #eee">'
                            . '<td style="padding:4px 8px">' . htmlspecialchars($kw->getKeyword()) . '</td>'
                            . '<td style="padding:4px 8px; color:#d4a017">' . $stars . '</td>'
                            . '<td style="padding:4px 8px; text-align:right">' . $pos . '</td>'
                            . '<td style="padding:4px 8px; text-align:right">' . $clicks . '</td>'
                            . '<td style="padding:4px 8px; text-align:right">' . $impressions . '</td>'
                            . '</tr>';
                    }

                    $html .= '</tbody></table>';
                    $html .= '<div style="margin-top:6px; color:#6c757d; font-size:0.8rem">'
                        . count($keywords) . ' keyword(s) actif(s)</div>';

                    return $html;
                })
                ->setVirtual(true)
                ->renderAsHtml();
        }
    }
}
