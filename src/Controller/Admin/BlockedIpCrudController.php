<?php

namespace App\Controller\Admin;

use App\Entity\BlockedIp;
use App\Service\IpSecurityService;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Response;

class BlockedIpCrudController extends AbstractCrudController
{
    public function __construct(
        private IpSecurityService $ipSecurityService,
        private AdminUrlGenerator $adminUrlGenerator,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return BlockedIp::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('IP bloquée')
            ->setEntityLabelInPlural('IPs bloquées')
            ->setPageTitle('index', 'Gestion des IP bloquées')
            ->setPageTitle('new', 'Bloquer une IP')
            ->setPageTitle('edit', 'Modifier le blocage')
            ->setDefaultSort(['createdAt' => 'DESC'])
            ->setSearchFields(['ipAddress', 'description'])
            ->showEntityActionsInlined();
    }

    public function configureActions(Actions $actions): Actions
    {
        $unblockAction = Action::new('unblock', 'Débloquer', 'fa fa-unlock')
            ->linkToCrudAction('unblock')
            ->setCssClass('btn btn-success btn-sm')
            ->displayIf(fn (BlockedIp $ip) => $ip->isActive());

        $viewLogsAction = Action::new('viewLogs', 'Voir les logs', 'fa fa-list')
            ->linkToUrl(function (BlockedIp $ip) {
                return $this->adminUrlGenerator
                    ->setController(SecurityLogCrudController::class)
                    ->setAction(Action::INDEX)
                    ->set('filters[ipAddress][comparison]', '=')
                    ->set('filters[ipAddress][value]', $ip->getIpAddress())
                    ->generateUrl();
            });

        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_INDEX, $unblockAction)
            ->add(Crud::PAGE_INDEX, $viewLogsAction)
            ->add(Crud::PAGE_DETAIL, $unblockAction)
            ->add(Crud::PAGE_DETAIL, $viewLogsAction)
            ->reorder(Crud::PAGE_INDEX, [Action::DETAIL, 'unblock', 'viewLogs', Action::EDIT, Action::DELETE]);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(BooleanFilter::new('isActive', 'Actif'))
            ->add(BooleanFilter::new('isAutomatic', 'Automatique'))
            ->add(ChoiceFilter::new('reason', 'Raison')->setChoices([
                'Seuil automatique' => BlockedIp::REASON_AUTO_THRESHOLD,
                'Manuel' => BlockedIp::REASON_MANUAL,
                'Pattern d\'attaque' => BlockedIp::REASON_ATTACK_PATTERN,
            ]));
    }

    public function configureFields(string $pageName): iterable
    {

        yield TextField::new('ipAddress', 'Adresse IP')
            ->setRequired(true)
            ->setHelp('Format IPv4 ou IPv6');

        yield ChoiceField::new('reason', 'Raison')
            ->setChoices([
                'Blocage manuel' => BlockedIp::REASON_MANUAL,
                'Seuil automatique' => BlockedIp::REASON_AUTO_THRESHOLD,
                'Pattern d\'attaque' => BlockedIp::REASON_ATTACK_PATTERN,
            ])
            ->renderAsBadges([
                BlockedIp::REASON_AUTO_THRESHOLD => 'warning',
                BlockedIp::REASON_MANUAL => 'info',
                BlockedIp::REASON_ATTACK_PATTERN => 'danger',
            ]);

        yield TextareaField::new('description', 'Notes')
            ->hideOnIndex();

        yield BooleanField::new('isAutomatic', 'Auto')
            ->renderAsSwitch(false)
            ->hideOnForm();

        yield BooleanField::new('isActive', 'Actif')
            ->renderAsSwitch(true);

        if ($pageName === Crud::PAGE_NEW) {
            yield ChoiceField::new('duration', 'Durée du blocage')
                ->setChoices([
                    '1 heure' => BlockedIp::DURATION_1_HOUR,
                    '24 heures' => BlockedIp::DURATION_24_HOURS,
                    '7 jours' => BlockedIp::DURATION_7_DAYS,
                    '30 jours' => BlockedIp::DURATION_30_DAYS,
                    'Permanent' => 0,
                ])
                ->setFormTypeOption('mapped', false)
                ->setRequired(true);
        }

        yield DateTimeField::new('expiresAt', 'Expire le')
            ->setFormat('dd/MM/yyyy HH:mm')
            ->hideOnForm()
            ->formatValue(fn ($value) => $value === null ? 'Permanent' : $value->format('d/m/Y H:i'));

        yield IntegerField::new('hitCount', 'Requêtes bloquées')
            ->hideOnForm();

        yield DateTimeField::new('lastHitAt', 'Dernier hit')
            ->setFormat('dd/MM/yyyy HH:mm')
            ->hideOnForm()
            ->hideOnIndex();

        yield ArrayField::new('triggerData', 'URLs déclencheuses')
            ->hideOnIndex()
            ->hideOnForm();

        yield DateTimeField::new('createdAt', 'Créé le')
            ->setFormat('dd/MM/yyyy HH:mm')
            ->hideOnForm();
    }

    public function unblock(): Response
    {
        /** @var BlockedIp $blockedIp */
        $blockedIp = $this->getContext()->getEntity()->getInstance();

        $this->ipSecurityService->unblockIp($blockedIp->getIpAddress());

        $this->addFlash('success', sprintf('IP %s débloquée', $blockedIp->getIpAddress()));

        return $this->redirect(
            $this->adminUrlGenerator
                ->setController(self::class)
                ->setAction(Action::INDEX)
                ->generateUrl()
        );
    }

    public function persistEntity($entityManager, $entityInstance): void
    {
        // Handle duration selection on create
        $request = $this->getContext()->getRequest();
        $duration = $request->request->all()['BlockedIp']['duration'] ?? null;

        if ($duration !== null && $duration !== '' && (int) $duration > 0) {
            $entityInstance->setExpiresAt(new \DateTimeImmutable("+{$duration} seconds"));
        } else {
            $entityInstance->setExpiresAt(null); // Permanent
        }

        parent::persistEntity($entityManager, $entityInstance);
        $this->ipSecurityService->invalidateCache();
    }

    public function updateEntity($entityManager, $entityInstance): void
    {
        parent::updateEntity($entityManager, $entityInstance);
        $this->ipSecurityService->invalidateCache();
    }

    public function deleteEntity($entityManager, $entityInstance): void
    {
        parent::deleteEntity($entityManager, $entityInstance);
        $this->ipSecurityService->invalidateCache();
    }
}
