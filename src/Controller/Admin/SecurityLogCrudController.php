<?php

namespace App\Controller\Admin;

use App\Entity\SecurityLog;
use App\Service\IpSecurityService;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Response;

class SecurityLogCrudController extends AbstractCrudController
{
    public function __construct(
        private IpSecurityService $ipSecurityService,
        private AdminUrlGenerator $adminUrlGenerator,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return SecurityLog::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Log de sécurité')
            ->setEntityLabelInPlural('Logs de sécurité')
            ->setPageTitle('index', 'Journal des erreurs 4xx')
            ->setPageTitle('detail', 'Détail du log')
            ->setDefaultSort(['createdAt' => 'DESC'])
            ->setSearchFields(['ipAddress', 'requestUrl', 'userAgent'])
            ->showEntityActionsInlined()
            ->setHelp('index', 'Logs des erreurs HTTP 4xx. Les données sont purgées après 7 jours (RGPD).');
    }

    public function configureActions(Actions $actions): Actions
    {
        $blockIpAction = Action::new('blockIp', 'Bloquer IP', 'fa fa-ban')
            ->linkToCrudAction('blockIp')
            ->setCssClass('btn btn-danger btn-sm')
            ->displayIf(fn (SecurityLog $log) => !$this->ipSecurityService->isIpBlocked($log->getIpAddress()));

        $viewLogsForIp = Action::new('viewLogsForIp', 'Logs de cette IP', 'fa fa-search')
            ->linkToUrl(function (SecurityLog $log) {
                return $this->adminUrlGenerator
                    ->setController(self::class)
                    ->setAction(Action::INDEX)
                    ->set('filters[ipAddress][comparison]', '=')
                    ->set('filters[ipAddress][value]', $log->getIpAddress())
                    ->generateUrl();
            });

        return $actions
            // Remove create, edit, delete actions (read-only)
            ->disable(Action::NEW, Action::EDIT, Action::DELETE)
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_INDEX, $blockIpAction)
            ->add(Crud::PAGE_DETAIL, $blockIpAction)
            ->add(Crud::PAGE_DETAIL, $viewLogsForIp)
            ->reorder(Crud::PAGE_INDEX, [Action::DETAIL, 'blockIp']);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(TextFilter::new('ipAddress', 'Adresse IP'))
            ->add(TextFilter::new('requestUrl', 'URL'))
            ->add(ChoiceFilter::new('statusCode', 'Code HTTP')->setChoices([
                '400 Bad Request' => 400,
                '401 Unauthorized' => 401,
                '403 Forbidden' => 403,
                '404 Not Found' => 404,
                '405 Method Not Allowed' => 405,
                '429 Too Many Requests' => 429,
            ]))
            ->add(DateTimeFilter::new('createdAt', 'Date'));
    }

    public function configureFields(string $pageName): iterable
    {

        yield TextField::new('ipAddress', 'Adresse IP')
            ->formatValue(function ($value) {
                $isBlocked = $this->ipSecurityService->isIpBlocked($value);
                $badge = $isBlocked ? ' <span class="badge bg-danger">Bloquée</span>' : '';
                return $value . $badge;
            });

        yield TextField::new('requestUrl', 'URL')
            ->setMaxLength(80);

        yield TextField::new('requestMethod', 'Méthode')
            ->hideOnIndex();

        yield IntegerField::new('statusCode', 'Code HTTP')
            ->formatValue(fn ($value) => $this->formatStatusCode($value));

        yield TextField::new('userAgent', 'User Agent')
            ->setMaxLength(50)
            ->hideOnIndex();

        yield TextareaField::new('userAgent', 'User Agent')
            ->onlyOnDetail();

        yield TextField::new('referer', 'Referer')
            ->hideOnIndex();

        yield ArrayField::new('extraData', 'Données supplémentaires')
            ->onlyOnDetail();

        yield DateTimeField::new('createdAt', 'Date')
            ->setFormat('dd/MM/yyyy HH:mm:ss');
    }

    private function formatStatusCode(int $code): string
    {
        $labels = [
            400 => '400 Bad Request',
            401 => '401 Unauthorized',
            403 => '403 Forbidden',
            404 => '404 Not Found',
            405 => '405 Not Allowed',
            429 => '429 Too Many',
        ];

        return $labels[$code] ?? (string) $code;
    }

    public function blockIp(): Response
    {
        /** @var SecurityLog $log */
        $log = $this->getContext()->getEntity()->getInstance();

        $this->ipSecurityService->blockIp(
            $log->getIpAddress(),
            'manual',
            sprintf('Bloqué depuis le log #%d - URL: %s', $log->getId(), $log->getRequestUrl())
        );

        $this->addFlash('success', sprintf('IP %s bloquée avec succès', $log->getIpAddress()));

        return $this->redirect(
            $this->adminUrlGenerator
                ->setController(BlockedIpCrudController::class)
                ->setAction(Action::INDEX)
                ->generateUrl()
        );
    }
}
