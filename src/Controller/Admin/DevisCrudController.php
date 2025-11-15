<?php

namespace App\Controller\Admin;

use App\Entity\Devis;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use Doctrine\ORM\EntityManagerInterface;
use App\Service\PdfGeneratorService;
use App\Service\NumberingService;
use Symfony\Component\HttpFoundation\Response;

class DevisCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly NumberingService $numberingService
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Devis::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Devis')
            ->setEntityLabelInPlural('Devis')
            ->setPageTitle('index', 'Liste des devis')
            ->setPageTitle('new', 'Créer un devis')
            ->setPageTitle('edit', 'Modifier le devis')
            ->setPageTitle('detail', 'Détails du devis')
            ->setDefaultSort(['dateCreation' => 'DESC'])
            ->setPaginatorPageSize(20);
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->onlyOnIndex(),
            TextField::new('number', 'Numéro')
                ->setHelp('Auto-généré si vide, mais vous pouvez le modifier')
                ->hideOnIndex(),
            TextField::new('title', 'Titre'),
            AssociationField::new('client', 'Client'),
            ChoiceField::new('status', 'Statut')
                ->setChoices(Devis::getStatusChoices())
                ->renderAsBadges([
                    Devis::STATUS_BROUILLON => 'secondary',
                    Devis::STATUS_A_ENVOYER => 'warning',
                    Devis::STATUS_ENVOYE => 'info',
                    Devis::STATUS_RELANCE => 'warning',
                    Devis::STATUS_ACCEPTE => 'success',
                    Devis::STATUS_REFUSE => 'danger',
                    Devis::STATUS_EXPIRE => 'dark',
                    Devis::STATUS_ANNULE => 'secondary',
                ])
                ->formatValue(function ($value, $entity) {
                    if ($this->getContext()->getCrud()->getCurrentPage() === 'index') {
                        return $this->renderStatusWithActions($entity);
                    }
                    return $value;
                }),
            DateField::new('dateCreation', 'Date création'),
            DateField::new('dateValidite', 'Validité'),
            DateField::new('dateEnvoi', 'Date envoi')->onlyOnDetail(),
            CollectionField::new('items', 'Lignes du devis')
                ->setEntryType(\App\Form\DevisItemType::class)
                ->onlyOnForms()
                ->setHelp('Ajoutez les lignes de votre devis'),
            NumberField::new('vatRate', 'TVA (%)')
                ->setNumDecimals(2)
                ->setHelp('0 pour auto-entrepreneur (TVA non applicable), 20 pour TVA normale')
                ->onlyOnForms(),
            MoneyField::new('totalHt', 'Total HT')->setCurrency('EUR')->setStoredAsCents(false)->hideOnForm(),
            MoneyField::new('totalTtc', 'Total TTC')->setCurrency('EUR')->setStoredAsCents(false)->hideOnForm(),
            MoneyField::new('acompte', 'Acompte (€)')
                ->setCurrency('EUR')
                ->setStoredAsCents(false)
                ->onlyOnForms(),
            NumberField::new('acomptePercentage', 'Acompte (%)')
                ->setNumDecimals(2)
                ->onlyOnForms(),
            TextareaField::new('description', 'Description')->onlyOnForms(),
            TextareaField::new('conditions', 'Conditions')->onlyOnForms(),
            TextareaField::new('notes', 'Notes')->onlyOnForms(),
            AssociationField::new('createdBy', 'Créé par')->onlyOnDetail(),
        ];
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(ChoiceFilter::new('status')->setChoices(Devis::getStatusChoices()))
            ->add('client')
            ->add(DateTimeFilter::new('dateCreation'))
            ->add(DateTimeFilter::new('dateValidite'));
    }

    public function configureActions(Actions $actions): Actions
    {
        $generateInvoice = Action::new('generateInvoice', 'Générer facture')
            ->linkToCrudAction('generateInvoice')
            ->setIcon('fas fa-file-invoice')
            ->displayIf(function ($entity) {
                return $entity->canBeConverted();
            });

        $generatePdf = Action::new('generatePdf', 'Générer PDF')
            ->linkToCrudAction('generatePdf')
            ->setIcon('fas fa-file-pdf')
            ->displayIf(function ($entity) {
                return $entity->getStatus() !== Devis::STATUS_BROUILLON;
            });

        // Quick status change actions
        $markAsReady = Action::new('markAsReady', 'À envoyer')
            ->linkToCrudAction('changeStatus')
            ->setIcon('fas fa-paper-plane')
            ->addCssClass('btn btn-sm btn-warning')
            ->displayIf(function ($entity) {
                return $entity->getStatus() === Devis::STATUS_BROUILLON;
            });

        $markAsSent = Action::new('markAsSent', 'Envoyé')
            ->linkToCrudAction('changeStatus')
            ->setIcon('fas fa-check')
            ->addCssClass('btn btn-sm btn-info')
            ->displayIf(function ($entity) {
                return $entity->getStatus() === Devis::STATUS_A_ENVOYER;
            });

        $markAsAccepted = Action::new('markAsAccepted', 'Accepté')
            ->linkToCrudAction('changeStatus')
            ->setIcon('fas fa-thumbs-up')
            ->addCssClass('btn btn-sm btn-success')
            ->displayIf(function ($entity) {
                return in_array($entity->getStatus(), [Devis::STATUS_ENVOYE, Devis::STATUS_RELANCE]);
            });

        $markAsRejected = Action::new('markAsRejected', 'Refusé')
            ->linkToCrudAction('changeStatus')
            ->setIcon('fas fa-thumbs-down')
            ->addCssClass('btn btn-sm btn-danger')
            ->displayIf(function ($entity) {
                return in_array($entity->getStatus(), [Devis::STATUS_ENVOYE, Devis::STATUS_RELANCE]);
            });

        return $actions
            ->add(Crud::PAGE_INDEX, $generateInvoice)
            ->add(Crud::PAGE_INDEX, $generatePdf)
            ->add(Crud::PAGE_INDEX, $markAsReady)
            ->add(Crud::PAGE_INDEX, $markAsSent)
            ->add(Crud::PAGE_INDEX, $markAsAccepted)
            ->add(Crud::PAGE_INDEX, $markAsRejected)
            ->add(Crud::PAGE_DETAIL, $generateInvoice)
            ->add(Crud::PAGE_DETAIL, $generatePdf);
    }

    private function renderStatusWithActions($entity): string
    {
        $currentStatus = $entity->getStatus();
        $statusLabels = array_flip(Devis::getStatusChoices());
        $currentLabel = $statusLabels[$currentStatus] ?? $currentStatus;
        
        $badgeClass = [
            Devis::STATUS_BROUILLON => 'secondary',
            Devis::STATUS_A_ENVOYER => 'warning',
            Devis::STATUS_ENVOYE => 'info',
            Devis::STATUS_RELANCE => 'warning',
            Devis::STATUS_ACCEPTE => 'success',
            Devis::STATUS_REFUSE => 'danger',
            Devis::STATUS_EXPIRE => 'dark',
            Devis::STATUS_ANNULE => 'secondary',
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
            Devis::STATUS_BROUILLON => [Devis::STATUS_A_ENVOYER],
            Devis::STATUS_A_ENVOYER => [Devis::STATUS_ENVOYE, Devis::STATUS_ANNULE],
            Devis::STATUS_ENVOYE => [Devis::STATUS_RELANCE, Devis::STATUS_ACCEPTE, Devis::STATUS_REFUSE],
            Devis::STATUS_RELANCE => [Devis::STATUS_ACCEPTE, Devis::STATUS_REFUSE, Devis::STATUS_EXPIRE],
            Devis::STATUS_ACCEPTE => [Devis::STATUS_ANNULE],
            Devis::STATUS_REFUSE => [],
            Devis::STATUS_EXPIRE => [],
            Devis::STATUS_ANNULE => [],
        ];
        
        return $transitions[$currentStatus] ?? [];
    }

    private function getActionNameForStatus(string $status): string
    {
        $actionMap = [
            Devis::STATUS_A_ENVOYER => 'markAsReady',
            Devis::STATUS_ENVOYE => 'markAsSent',
            Devis::STATUS_RELANCE => 'markAsRelance',
            Devis::STATUS_ACCEPTE => 'markAsAccepted',
            Devis::STATUS_REFUSE => 'markAsRejected',
            Devis::STATUS_EXPIRE => 'markAsExpired',
            Devis::STATUS_ANNULE => 'markAsCancelled',
        ];
        
        return $actionMap[$status] ?? 'changeStatus';
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof Devis) {
            // Set the current user as the creator
            $entityInstance->setCreatedBy($this->getUser());
            
            // Generate sequential number if not set
            if (!$entityInstance->getNumber()) {
                $number = $this->numberingService->generateDevisNumber();
                $entityInstance->setNumber($number);
            }
            
            // Set creation timestamp if not already set
            if (!$entityInstance->getCreatedAt()) {
                $entityInstance->setCreatedAt(new \DateTimeImmutable());
            }
            
            // Set default status if not set
            if (!$entityInstance->getStatus()) {
                $entityInstance->setStatus(Devis::STATUS_BROUILLON);
            }
            
            // Set validity date if not set (30 days from creation)
            if (!$entityInstance->getDateValidite()) {
                $entityInstance->setDateValidite(new \DateTimeImmutable('+30 days'));
            }
            
            // Set position for items in natural order if not set
            $position = 1;
            foreach ($entityInstance->getItems() as $item) {
                if ($item->getPosition() === null) {
                    $item->setPosition($position);
                }
                $position++;
            }
            
            // Recalculate totals based on items
            $entityInstance->calculateTotals();
        }

        parent::persistEntity($entityManager, $entityInstance);
    }

    public function changeStatus(EntityManagerInterface $entityManager)
    {
        $devis = $this->getContext()->getEntity()->getInstance();
        $actionName = $this->getContext()->getRequest()->get('action');
        
        // Debug info
        if (!$actionName) {
            $this->addFlash('error', 'Action non spécifiée.');
            return $this->redirectToRoute('admin', [
                'crudAction' => 'index',
                'crudControllerFqcn' => self::class
            ]);
        }
        
        $statusMap = [
            'markAsReady' => Devis::STATUS_A_ENVOYER,
            'markAsSent' => Devis::STATUS_ENVOYE,
            'markAsRelance' => Devis::STATUS_RELANCE,
            'markAsAccepted' => Devis::STATUS_ACCEPTE,
            'markAsRejected' => Devis::STATUS_REFUSE,
            'markAsExpired' => Devis::STATUS_EXPIRE,
            'markAsCancelled' => Devis::STATUS_ANNULE,
        ];
        
        if (isset($statusMap[$actionName])) {
            $newStatus = $statusMap[$actionName];
            $devis->setStatus($newStatus);
            $entityManager->flush();
            
            $statusLabels = [
                Devis::STATUS_A_ENVOYER => 'à envoyer',
                Devis::STATUS_ENVOYE => 'envoyé',
                Devis::STATUS_RELANCE => 'relancé',
                Devis::STATUS_ACCEPTE => 'accepté',
                Devis::STATUS_REFUSE => 'refusé',
                Devis::STATUS_EXPIRE => 'expiré',
                Devis::STATUS_ANNULE => 'annulé',
            ];
            
            $this->addFlash('success', 'Devis marqué comme ' . $statusLabels[$newStatus] . '.');
        } else {
            $this->addFlash('error', 'Action non reconnue: ' . $actionName);
        }
        
        return $this->redirectToRoute('admin', [
            'crudAction' => 'index',
            'crudControllerFqcn' => self::class
        ]);
    }

    public function generateInvoice(EntityManagerInterface $entityManager)
    {
        $devis = $this->getContext()->getEntity()->getInstance();
        
        if (!$devis->canBeConverted()) {
            $this->addFlash('error', 'Ce devis ne peut pas être converti en facture.');
            return $this->redirectToRoute('admin', ['crudAction' => 'detail', 'crudControllerFqcn' => self::class, 'entityId' => $devis->getId()]);
        }

        // Create new invoice from quote
        $facture = new \App\Entity\Facture();
        $facture->setDevis($devis);
        $facture->setClient($devis->getClient());
        $facture->setCreatedBy($this->getUser());
        $facture->setTitle($devis->getTitle());
        $facture->setDescription($devis->getDescription());
        $facture->setConditions($devis->getConditions());
        $facture->setNotes($devis->getNotes());
        $facture->setTotalHt($devis->getTotalHt());
        $facture->setTotalTtc($devis->getTotalTtc());
        $facture->setVatRate($devis->getVatRate());
        
        // Generate invoice number based on devis number
        $facture->setNumber($this->numberingService->generateFactureNumber($devis));
        
        // Copy items
        foreach ($devis->getItems() as $devisItem) {
            $factureItem = new \App\Entity\FactureItem();
            $factureItem->setFacture($facture);
            $factureItem->setDescription($devisItem->getDescription());
            $factureItem->setQuantity($devisItem->getQuantity());
            $factureItem->setUnit($devisItem->getUnit());
            $factureItem->setUnitPrice($devisItem->getUnitPrice());
            $factureItem->setDiscount($devisItem->getDiscount());
            $factureItem->setVatRate($devisItem->getVatRate());
            $factureItem->setTotal($devisItem->getTotal());
            $factureItem->setPosition($devisItem->getPosition());
            
            $facture->addItem($factureItem);
        }
        
        $entityManager->persist($facture);
        $entityManager->flush();
        
        $this->addFlash('success', 'Facture générée avec succès.');
        
        return $this->redirectToRoute('admin', [
            'crudAction' => 'detail',
            'crudControllerFqcn' => 'App\\Controller\\Admin\\FactureCrudController',
            'entityId' => $facture->getId()
        ]);
    }

    public function generatePdf(PdfGeneratorService $pdfGenerator)
    {
        $devis = $this->getContext()->getEntity()->getInstance();
        
        try {
            $filepath = $pdfGenerator->generateDevisPdf($devis);
            
            // Return PDF as download
            return $this->file($filepath, basename($filepath));
            
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de la génération du PDF: ' . $e->getMessage());
            
            return $this->redirectToRoute('admin', [
                'crudAction' => 'detail', 
                'crudControllerFqcn' => self::class, 
                'entityId' => $devis->getId()
            ]);
        }
    }
}