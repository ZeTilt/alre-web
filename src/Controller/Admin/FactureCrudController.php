<?php

namespace App\Controller\Admin;

use App\Entity\Facture;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use App\Service\PdfGeneratorService;
use App\Service\NumberingService;

class FactureCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly NumberingService $numberingService
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Facture::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Facture')
            ->setEntityLabelInPlural('Factures')
            ->setPageTitle('index', 'Liste des factures')
            ->setPageTitle('new', 'Créer une facture')
            ->setPageTitle('edit', 'Modifier la facture')
            ->setPageTitle('detail', 'Détails de la facture')
            ->setDefaultSort(['dateFacture' => 'DESC'])
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
            AssociationField::new('devis', 'Devis')->onlyOnDetail(),
            ChoiceField::new('status', 'Statut')
                ->setChoices(Facture::getStatusChoices())
                ->renderAsBadges([
                    Facture::STATUS_BROUILLON => 'secondary',
                    Facture::STATUS_A_ENVOYER => 'warning',
                    Facture::STATUS_ENVOYE => 'info',
                    Facture::STATUS_RELANCE => 'warning',
                    Facture::STATUS_PAYE => 'success',
                    Facture::STATUS_EN_RETARD => 'danger',
                    Facture::STATUS_ANNULE => 'secondary',
                ])
                ->formatValue(function ($value, $entity) {
                    if ($this->getContext()->getCrud()->getCurrentPage() === 'index') {
                        return $this->renderStatusWithActions($entity);
                    }
                    return $value;
                }),
            DateField::new('dateFacture', 'Date facture'),
            DateField::new('dateEcheance', 'Échéance'),
            DateField::new('datePaiement', 'Date paiement')->onlyOnDetail(),
            ChoiceField::new('modePaiement', 'Mode paiement')
                ->setChoices(Facture::getModePaiementChoices())
                ->onlyOnForms(),
            CollectionField::new('items', 'Lignes de la facture')
                ->setEntryType(\App\Form\FactureItemType::class)
                ->onlyOnForms()
                ->setHelp('Ajoutez les lignes de votre facture'),
            MoneyField::new('totalHt', 'Total HT')->setCurrency('EUR')->setStoredAsCents(false)->hideOnForm(),
            MoneyField::new('totalTtc', 'Total TTC')->setCurrency('EUR')->setStoredAsCents(false)->hideOnForm(),
            TextareaField::new('description', 'Description')->onlyOnForms(),
            TextareaField::new('conditions', 'Conditions')->onlyOnForms(),
            TextareaField::new('notes', 'Notes')->onlyOnForms(),
            AssociationField::new('createdBy', 'Créé par')->onlyOnDetail(),
        ];
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(ChoiceFilter::new('status')->setChoices(Facture::getStatusChoices()))
            ->add('client')
            ->add(DateTimeFilter::new('dateFacture'))
            ->add(DateTimeFilter::new('dateEcheance'));
    }

    public function configureActions(Actions $actions): Actions
    {
        $generatePdf = Action::new('generatePdf', 'Générer PDF')
            ->linkToCrudAction('generatePdf')
            ->setIcon('fas fa-file-pdf')
            ->displayIf(function ($entity) {
                return $entity->getStatus() !== Facture::STATUS_BROUILLON;
            });

        // Quick status change actions
        $markAsReady = Action::new('markAsReady', 'À envoyer')
            ->linkToCrudAction('changeStatus')
            ->setIcon('fas fa-paper-plane')
            ->addCssClass('btn btn-sm btn-warning')
            ->displayIf(function ($entity) {
                return $entity->getStatus() === Facture::STATUS_BROUILLON;
            });

        $markAsSent = Action::new('markAsSent', 'Envoyé')
            ->linkToCrudAction('changeStatus')
            ->setIcon('fas fa-check')
            ->addCssClass('btn btn-sm btn-info')
            ->displayIf(function ($entity) {
                return $entity->getStatus() === Facture::STATUS_A_ENVOYER;
            });

        $markAsPaid = Action::new('markAsPaid', 'Payé')
            ->linkToCrudAction('changeStatus')
            ->setIcon('fas fa-euro-sign')
            ->addCssClass('btn btn-sm btn-success')
            ->displayIf(function ($entity) {
                return in_array($entity->getStatus(), [Facture::STATUS_ENVOYE, Facture::STATUS_RELANCE]);
            });

        $markAsOverdue = Action::new('markAsOverdue', 'En retard')
            ->linkToCrudAction('changeStatus')
            ->setIcon('fas fa-exclamation-triangle')
            ->addCssClass('btn btn-sm btn-danger')
            ->displayIf(function ($entity) {
                return in_array($entity->getStatus(), [Facture::STATUS_ENVOYE, Facture::STATUS_RELANCE]);
            });

        return $actions
            ->add(Crud::PAGE_INDEX, $generatePdf)
            ->add(Crud::PAGE_INDEX, $markAsReady)
            ->add(Crud::PAGE_INDEX, $markAsSent)
            ->add(Crud::PAGE_INDEX, $markAsPaid)
            ->add(Crud::PAGE_INDEX, $markAsOverdue)
            ->add(Crud::PAGE_DETAIL, $generatePdf);
    }

    private function renderStatusWithActions($entity): string
    {
        $currentStatus = $entity->getStatus();
        $statusLabels = array_flip(Facture::getStatusChoices());
        $currentLabel = $statusLabels[$currentStatus] ?? $currentStatus;
        
        $badgeClass = [
            Facture::STATUS_BROUILLON => 'secondary',
            Facture::STATUS_A_ENVOYER => 'warning',
            Facture::STATUS_ENVOYE => 'info',
            Facture::STATUS_RELANCE => 'warning',
            Facture::STATUS_PAYE => 'success',
            Facture::STATUS_EN_RETARD => 'danger',
            Facture::STATUS_ANNULE => 'secondary',
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
            Facture::STATUS_BROUILLON => [Facture::STATUS_A_ENVOYER],
            Facture::STATUS_A_ENVOYER => [Facture::STATUS_ENVOYE, Facture::STATUS_ANNULE],
            Facture::STATUS_ENVOYE => [Facture::STATUS_RELANCE, Facture::STATUS_PAYE, Facture::STATUS_EN_RETARD],
            Facture::STATUS_RELANCE => [Facture::STATUS_PAYE, Facture::STATUS_EN_RETARD],
            Facture::STATUS_PAYE => [],
            Facture::STATUS_EN_RETARD => [Facture::STATUS_PAYE],
            Facture::STATUS_ANNULE => [],
        ];
        
        return $transitions[$currentStatus] ?? [];
    }

    private function getActionNameForStatus(string $status): string
    {
        $actionMap = [
            Facture::STATUS_A_ENVOYER => 'markAsReady',
            Facture::STATUS_ENVOYE => 'markAsSent',
            Facture::STATUS_RELANCE => 'markAsRelance',
            Facture::STATUS_PAYE => 'markAsPaid',
            Facture::STATUS_EN_RETARD => 'markAsOverdue',
            Facture::STATUS_ANNULE => 'markAsCancelled',
        ];
        
        return $actionMap[$status] ?? 'changeStatus';
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof Facture) {
            // Set the current user as the creator
            $entityInstance->setCreatedBy($this->getUser());

            // Generate sequential number if not set
            if (!$entityInstance->getNumber()) {
                $number = $this->numberingService->generateFactureNumber();
                $entityInstance->setNumber($number);
            }

            // Set creation timestamp if not already set
            if (!$entityInstance->getCreatedAt()) {
                $entityInstance->setCreatedAt(new \DateTimeImmutable());
            }
            
            // Set default status if not set
            if (!$entityInstance->getStatus()) {
                $entityInstance->setStatus(Facture::STATUS_BROUILLON);
            }
            
            // Set invoice date if not set
            if (!$entityInstance->getDateFacture()) {
                $entityInstance->setDateFacture(new \DateTimeImmutable());
            }
            
            // Set due date if not set (30 days from invoice date)
            if (!$entityInstance->getDateEcheance()) {
                $entityInstance->setDateEcheance(new \DateTimeImmutable('+30 days'));
            }
            
            // Set position for items in natural order if not set
            $position = 1;
            foreach ($entityInstance->getItems() as $item) {
                if ($item->getPosition() === null) {
                    $item->setPosition($position);
                }
                $position++;
                // Set default VAT rate if not set
                if (!$item->getVatRate()) {
                    $item->setVatRate('20.00');
                }
            }
            
            // Recalculate totals based on items
            $entityInstance->calculateTotals();
        }

        parent::persistEntity($entityManager, $entityInstance);
    }

    public function changeStatus(EntityManagerInterface $entityManager)
    {
        $facture = $this->getContext()->getEntity()->getInstance();
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
            'markAsReady' => Facture::STATUS_A_ENVOYER,
            'markAsSent' => Facture::STATUS_ENVOYE,
            'markAsRelance' => Facture::STATUS_RELANCE,
            'markAsPaid' => Facture::STATUS_PAYE,
            'markAsOverdue' => Facture::STATUS_EN_RETARD,
            'markAsCancelled' => Facture::STATUS_ANNULE,
        ];
        
        if (isset($statusMap[$actionName])) {
            $newStatus = $statusMap[$actionName];
            $facture->setStatus($newStatus);
            
            // Set payment date when marked as paid
            if ($newStatus === Facture::STATUS_PAYE) {
                $facture->setDatePaiement(new \DateTimeImmutable());
            }
            
            $entityManager->flush();
            
            $statusLabels = [
                Facture::STATUS_A_ENVOYER => 'à envoyer',
                Facture::STATUS_ENVOYE => 'envoyée',
                Facture::STATUS_RELANCE => 'relancée',
                Facture::STATUS_PAYE => 'payée',
                Facture::STATUS_EN_RETARD => 'en retard',
                Facture::STATUS_ANNULE => 'annulée',
            ];
            
            $this->addFlash('success', 'Facture marquée comme ' . $statusLabels[$newStatus] . '.');
        } else {
            $this->addFlash('error', 'Action non reconnue: ' . $actionName);
        }
        
        return $this->redirectToRoute('admin', [
            'crudAction' => 'index',
            'crudControllerFqcn' => self::class
        ]);
    }

    public function generatePdf(PdfGeneratorService $pdfGenerator)
    {
        $facture = $this->getContext()->getEntity()->getInstance();
        
        try {
            $filepath = $pdfGenerator->generateFacturePdf($facture);
            
            // Return PDF as download
            return $this->file($filepath, basename($filepath));
            
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de la génération du PDF: ' . $e->getMessage());
            
            return $this->redirectToRoute('admin', [
                'crudAction' => 'detail', 
                'crudControllerFqcn' => self::class, 
                'entityId' => $facture->getId()
            ]);
        }
    }
}