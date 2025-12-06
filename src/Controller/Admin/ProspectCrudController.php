<?php

namespace App\Controller\Admin;

use App\Entity\Prospect;
use App\Entity\ProspectFollowUp;
use App\Form\ProspectContactType;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\UrlField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;

class ProspectCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Prospect::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Prospect')
            ->setEntityLabelInPlural('Prospects')
            ->setPageTitle('index', 'Liste des prospects')
            ->setPageTitle('new', 'Nouveau prospect')
            ->setPageTitle('edit', 'Modifier le prospect')
            ->setPageTitle('detail', 'Fiche prospect')
            ->setDefaultSort(['lastContactAt' => 'DESC'])
            ->setPaginatorPageSize(20);
    }

    public function configureFields(string $pageName): iterable
    {
        $statusBadges = [
            Prospect::STATUS_IDENTIFIED => 'secondary',
            Prospect::STATUS_CONTACTED => 'info',
            Prospect::STATUS_IN_DISCUSSION => 'warning',
            Prospect::STATUS_QUOTE_SENT => 'primary',
            Prospect::STATUS_WON => 'success',
            Prospect::STATUS_LOST => 'danger',
        ];

        return [
            TextField::new('companyName', 'Entreprise')
                ->formatValue(function ($value, $entity) {
                    if ($this->getContext()->getCrud()->getCurrentPage() === Crud::PAGE_INDEX) {
                        $url = $this->generateUrl('admin', [
                            'crudAction' => 'detail',
                            'crudControllerFqcn' => self::class,
                            'entityId' => $entity->getId()
                        ]);
                        return sprintf('<a href="%s">%s</a>', $url, htmlspecialchars($value));
                    }
                    return $value;
                })
                ->renderAsHtml(),
            UrlField::new('website', 'Site web')
                ->hideOnIndex(),
            TextField::new('activity', 'Activité'),
            TextField::new('city', 'Ville')
                ->hideOnIndex(),
            TextField::new('postalCode', 'Code postal')
                ->hideOnIndex(),
            TextField::new('country', 'Pays')
                ->setFormTypeOption('data', 'France')
                ->hideOnIndex(),
            ChoiceField::new('source', 'Source')
                ->setChoices(Prospect::getSourceChoices())
                ->renderAsBadges([
                    Prospect::SOURCE_LINKEDIN => 'info',
                    Prospect::SOURCE_FACEBOOK => 'primary',
                    Prospect::SOURCE_REFERRAL => 'success',
                    Prospect::SOURCE_COLD_EMAIL => 'warning',
                    Prospect::SOURCE_WEBSITE => 'secondary',
                    Prospect::SOURCE_EVENT => 'dark',
                    Prospect::SOURCE_OTHER => 'light',
                ]),
            TextField::new('sourceDetail', 'Détail source')
                ->setHelp('Ex: "Groupe Facebook XYZ", "Recommandé par Jean Dupont"')
                ->hideOnIndex(),
            ChoiceField::new('status', 'Statut')
                ->setChoices(Prospect::getStatusChoices())
                ->renderAsBadges($statusBadges)
                ->formatValue(function ($value, $entity) {
                    if ($this->getContext()->getCrud()->getCurrentPage() === 'index') {
                        return $this->renderStatusWithActions($entity);
                    }
                    return $value;
                }),
            MoneyField::new('estimatedValue', 'Valeur estimée')
                ->setCurrency('EUR')
                ->setStoredAsCents(false),
            CollectionField::new('contacts', 'Contacts')
                ->setEntryType(ProspectContactType::class)
                ->onlyOnForms()
                ->allowAdd()
                ->allowDelete()
                ->setHelp('Ajoutez les contacts de cette entreprise'),
            TextareaField::new('notes', 'Notes')
                ->hideOnIndex(),
            DateTimeField::new('lastContactAt', 'Dernier contact')
                ->hideOnForm(),
            DateTimeField::new('createdAt', 'Créé le')
                ->hideOnForm()
                ->hideOnIndex(),
            AssociationField::new('convertedClient', 'Client converti')
                ->hideOnForm()
                ->onlyOnDetail(),
            AssociationField::new('linkedDevis', 'Devis lié')
                ->hideOnForm()
                ->onlyOnDetail(),
        ];
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(ChoiceFilter::new('status')->setChoices(Prospect::getStatusChoices()))
            ->add(ChoiceFilter::new('source')->setChoices(Prospect::getSourceChoices()))
            ->add(DateTimeFilter::new('lastContactAt'))
            ->add(DateTimeFilter::new('createdAt'));
    }

    public function configureActions(Actions $actions): Actions
    {
        $sendEmail = Action::new('sendEmail', 'Envoyer email')
            ->linkToCrudAction('sendEmail')
            ->setIcon('fas fa-envelope')
            ->addCssClass('btn btn-info');

        $addFollowUp = Action::new('addFollowUp', 'Ajouter relance')
            ->linkToCrudAction('addFollowUp')
            ->setIcon('fas fa-bell')
            ->addCssClass('btn btn-warning');

        $createQuote = Action::new('createQuote', 'Créer devis')
            ->linkToCrudAction('createQuote')
            ->setIcon('fas fa-file-invoice-dollar')
            ->addCssClass('btn btn-primary')
            ->displayIf(fn($entity) => in_array($entity->getStatus(), [
                Prospect::STATUS_IN_DISCUSSION,
                Prospect::STATUS_QUOTE_SENT
            ]));

        $convertToClient = Action::new('convertToClient', 'Convertir en client')
            ->linkToCrudAction('convertToClient')
            ->setIcon('fas fa-user-check')
            ->addCssClass('btn btn-success')
            ->displayIf(fn($entity) => $entity->getStatus() === Prospect::STATUS_WON && !$entity->getConvertedClient());

        return $actions
            ->add(Crud::PAGE_INDEX, $sendEmail)
            ->add(Crud::PAGE_INDEX, $addFollowUp)
            ->add(Crud::PAGE_DETAIL, $sendEmail)
            ->add(Crud::PAGE_DETAIL, $addFollowUp)
            ->add(Crud::PAGE_DETAIL, $createQuote)
            ->add(Crud::PAGE_DETAIL, $convertToClient);
    }

    private function renderStatusWithActions($entity): string
    {
        $currentStatus = $entity->getStatus();
        $statusLabels = array_flip(Prospect::getStatusChoices());
        $currentLabel = $statusLabels[$currentStatus] ?? $currentStatus;

        $badgeClass = [
            Prospect::STATUS_IDENTIFIED => 'secondary',
            Prospect::STATUS_CONTACTED => 'info',
            Prospect::STATUS_IN_DISCUSSION => 'warning',
            Prospect::STATUS_QUOTE_SENT => 'primary',
            Prospect::STATUS_WON => 'success',
            Prospect::STATUS_LOST => 'danger',
        ];

        $possibleStatuses = $this->getPossibleStatusTransitions($currentStatus);

        if (empty($possibleStatuses)) {
            return sprintf('<span class="badge badge-%s">%s</span>', $badgeClass[$currentStatus], $currentLabel);
        }

        $dropdown = '<div class="btn-group">';
        $dropdown .= sprintf('<span class="badge badge-%s dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false" style="cursor: pointer;">%s <i class="fas fa-chevron-down fa-xs"></i></span>', $badgeClass[$currentStatus], $currentLabel);
        $dropdown .= '<ul class="dropdown-menu">';

        foreach ($possibleStatuses as $status) {
            $label = $statusLabels[$status] ?? $status;
            $actionName = $this->getActionNameForStatus($status);
            $url = $this->generateUrl('admin', [
                'crudAction' => 'changeStatus',
                'crudControllerFqcn' => self::class,
                'entityId' => $entity->getId(),
                'action' => $actionName
            ]);
            $dropdown .= sprintf('<li><a class="dropdown-item" href="%s">%s</a></li>', $url, $label);
        }

        $dropdown .= '</ul></div>';

        return $dropdown;
    }

    private function getPossibleStatusTransitions(string $currentStatus): array
    {
        $transitions = [
            Prospect::STATUS_IDENTIFIED => [Prospect::STATUS_CONTACTED, Prospect::STATUS_LOST],
            Prospect::STATUS_CONTACTED => [Prospect::STATUS_IN_DISCUSSION, Prospect::STATUS_LOST],
            Prospect::STATUS_IN_DISCUSSION => [Prospect::STATUS_QUOTE_SENT, Prospect::STATUS_WON, Prospect::STATUS_LOST],
            Prospect::STATUS_QUOTE_SENT => [Prospect::STATUS_WON, Prospect::STATUS_LOST, Prospect::STATUS_IN_DISCUSSION],
            Prospect::STATUS_WON => [],
            Prospect::STATUS_LOST => [Prospect::STATUS_IDENTIFIED], // Allow to reactivate
        ];

        return $transitions[$currentStatus] ?? [];
    }

    private function getActionNameForStatus(string $status): string
    {
        $actionMap = [
            Prospect::STATUS_IDENTIFIED => 'markAsIdentified',
            Prospect::STATUS_CONTACTED => 'markAsContacted',
            Prospect::STATUS_IN_DISCUSSION => 'markAsInDiscussion',
            Prospect::STATUS_QUOTE_SENT => 'markAsQuoteSent',
            Prospect::STATUS_WON => 'markAsWon',
            Prospect::STATUS_LOST => 'markAsLost',
        ];

        return $actionMap[$status] ?? 'changeStatus';
    }

    public function changeStatus(EntityManagerInterface $entityManager): \Symfony\Component\HttpFoundation\RedirectResponse
    {
        $prospect = $this->getContext()->getEntity()->getInstance();
        $actionName = $this->getContext()->getRequest()->get('action');

        if (!$actionName) {
            $this->addFlash('error', 'Action non spécifiée.');
            return $this->redirectToRoute('admin', [
                'crudAction' => 'index',
                'crudControllerFqcn' => self::class
            ]);
        }

        $statusMap = [
            'markAsIdentified' => Prospect::STATUS_IDENTIFIED,
            'markAsContacted' => Prospect::STATUS_CONTACTED,
            'markAsInDiscussion' => Prospect::STATUS_IN_DISCUSSION,
            'markAsQuoteSent' => Prospect::STATUS_QUOTE_SENT,
            'markAsWon' => Prospect::STATUS_WON,
            'markAsLost' => Prospect::STATUS_LOST,
        ];

        if (isset($statusMap[$actionName])) {
            $newStatus = $statusMap[$actionName];
            $oldStatus = $prospect->getStatus();
            $prospect->setStatus($newStatus);

            // Update lastContactAt when status changes (implies a contact happened)
            if ($oldStatus !== $newStatus) {
                $prospect->setLastContactAt(new \DateTimeImmutable());
            }

            $entityManager->flush();

            $statusLabels = [
                Prospect::STATUS_IDENTIFIED => 'identifié',
                Prospect::STATUS_CONTACTED => 'contacté',
                Prospect::STATUS_IN_DISCUSSION => 'en discussion',
                Prospect::STATUS_QUOTE_SENT => 'devis envoyé',
                Prospect::STATUS_WON => 'gagné',
                Prospect::STATUS_LOST => 'perdu',
            ];

            $this->addFlash('success', 'Prospect marqué comme ' . $statusLabels[$newStatus] . '.');
        } else {
            $this->addFlash('error', 'Action non reconnue: ' . $actionName);
        }

        return $this->redirectToRoute('admin', [
            'crudAction' => 'index',
            'crudControllerFqcn' => self::class
        ]);
    }

    public function sendEmail(): \Symfony\Component\HttpFoundation\RedirectResponse
    {
        $prospect = $this->getContext()->getEntity()->getInstance();

        return $this->redirectToRoute('admin_prospection_send_email', [
            'id' => $prospect->getId()
        ]);
    }

    public function addFollowUp(EntityManagerInterface $entityManager): \Symfony\Component\HttpFoundation\RedirectResponse
    {
        $prospect = $this->getContext()->getEntity()->getInstance();

        // Create a new follow-up with default values
        $followUp = new ProspectFollowUp();
        $followUp->setProspect($prospect);
        $followUp->setSubject('Relance - ' . $prospect->getCompanyName());
        $followUp->setDueAt(new \DateTime('+3 days'));
        $followUp->setType(\App\Entity\ProspectInteraction::TYPE_EMAIL);

        // Set primary contact if exists
        $primaryContact = $prospect->getPrimaryContact();
        if ($primaryContact) {
            $followUp->setContact($primaryContact);
        }

        $entityManager->persist($followUp);
        $entityManager->flush();

        $this->addFlash('success', 'Relance créée. Vous pouvez la modifier.');

        return $this->redirectToRoute('admin', [
            'crudAction' => 'edit',
            'crudControllerFqcn' => ProspectFollowUpCrudController::class,
            'entityId' => $followUp->getId()
        ]);
    }

    public function createQuote(): \Symfony\Component\HttpFoundation\RedirectResponse
    {
        $prospect = $this->getContext()->getEntity()->getInstance();

        // Redirect to devis creation with pre-filled data
        return $this->redirectToRoute('admin', [
            'crudAction' => 'new',
            'crudControllerFqcn' => 'App\\Controller\\Admin\\DevisCrudController',
            'prospectId' => $prospect->getId()
        ]);
    }

    public function convertToClient(EntityManagerInterface $entityManager): \Symfony\Component\HttpFoundation\RedirectResponse
    {
        $prospect = $this->getContext()->getEntity()->getInstance();

        if ($prospect->getConvertedClient()) {
            $this->addFlash('warning', 'Ce prospect a déjà été converti en client.');
            return $this->redirectToRoute('admin', [
                'crudAction' => 'detail',
                'crudControllerFqcn' => self::class,
                'entityId' => $prospect->getId()
            ]);
        }

        // Create new client from prospect
        $client = new \App\Entity\Client();
        $client->setCompanyName($prospect->getCompanyName());
        $client->setCity($prospect->getCity());
        $client->setPostalCode($prospect->getPostalCode());
        $client->setCountry($prospect->getCountry() ?? 'France');

        // Get primary contact info
        $primaryContact = $prospect->getPrimaryContact();
        if ($primaryContact) {
            $client->setContactName($primaryContact->getFirstName() . ' ' . $primaryContact->getLastName());
            $client->setEmail($primaryContact->getEmail());
            $client->setPhone($primaryContact->getPhone());
        }

        // Link prospect to client
        $prospect->setConvertedClient($client);

        $entityManager->persist($client);
        $entityManager->flush();

        $this->addFlash('success', 'Client créé avec succès à partir du prospect.');

        return $this->redirectToRoute('admin', [
            'crudAction' => 'detail',
            'crudControllerFqcn' => 'App\\Controller\\Admin\\ClientCrudController',
            'entityId' => $client->getId()
        ]);
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof Prospect) {
            // Set default status if not set
            if (!$entityInstance->getStatus()) {
                $entityInstance->setStatus(Prospect::STATUS_IDENTIFIED);
            }
        }

        parent::persistEntity($entityManager, $entityInstance);
    }
}
