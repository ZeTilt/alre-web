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
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use App\Service\PdfGeneratorService;
use App\Service\NumberingService;
use App\Service\CompanyService;
use App\Service\Workflow\WorkflowService;
use App\Service\Workflow\Config\FactureWorkflowConfig;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;

class FactureCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly NumberingService $numberingService,
        private readonly CompanyService $companyService,
        private readonly WorkflowService $workflowService,
        private readonly FactureWorkflowConfig $workflowConfig
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Facture::class;
    }

    public function createEntity(string $entityFqcn): Facture
    {
        $facture = new Facture();
        $facture->setNumber($this->numberingService->generateFactureNumber());
        return $facture;
    }

    public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): QueryBuilder
    {
        $qb = parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters);

        // Eager load relations to avoid N+1 queries
        $qb->leftJoin('entity.client', 'c')->addSelect('c')
           ->leftJoin('entity.items', 'i')->addSelect('i')
           ->leftJoin('entity.devis', 'd')->addSelect('d')
           ->leftJoin('entity.createdBy', 'u')->addSelect('u');

        return $qb;
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
            TextField::new('number', 'Numéro')
                ->setHelp('Auto-généré si vide, mais vous pouvez le modifier')
                ->hideOnIndex(),
            TextField::new('title', 'Titre')
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
            TextareaField::new('additionalInfo', 'Information complémentaire')
                ->onlyOnForms()
                ->setHelp('Texte libre affiché entre l\'objet et les prestations (sans label)'),
            AssociationField::new('client', 'Client'),
            AssociationField::new('devis', 'Devis')->onlyOnDetail(),
            ChoiceField::new('status', 'Statut')
                ->setChoices(Facture::getStatusChoices())
                ->renderAsBadges([
                    Facture::STATUS_BROUILLON => 'secondary',
                    Facture::STATUS_A_ENVOYER => 'warning',
                    Facture::STATUS_ENVOYE => 'info',
                    Facture::STATUS_A_RELANCER => 'danger',
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
            DateField::new('datePaiement', 'Date paiement')
                ->setHelp('Date effective du paiement (utilisée pour le calcul du CA)')
                ->hideOnIndex()
                ->setFormTypeOption('required', false),
            ChoiceField::new('modePaiement', 'Mode paiement')
                ->setChoices(Facture::getModePaiementChoices())
                ->onlyOnForms(),
            CollectionField::new('items', 'Lignes de la facture')
                ->setEntryType(\App\Form\FactureItemType::class)
                ->onlyOnForms()
                ->setHelp('Ajoutez les lignes de votre facture'),
            NumberField::new('vatRate', 'TVA (%)')
                ->setNumDecimals(2)
                ->setHelp('0 pour auto-entrepreneur (TVA non applicable), 20 pour TVA normale')
                ->onlyOnForms(),
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
            ->add(DateTimeFilter::new('dateEcheance'))
            ->add(DateTimeFilter::new('datePaiement'));
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
        return $this->workflowService->renderStatusDropdown(
            $entity,
            $this->workflowConfig,
            self::class,
            fn($e) => $e->getId()
        );
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

            // Set default conditions from company if not set
            if (!$entityInstance->getConditions()) {
                $company = $this->companyService->getCompanyOrDefault();
                if ($company->getFactureConditions()) {
                    $entityInstance->setConditions($company->getFactureConditions());
                }
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

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof Facture) {
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

            // Update timestamp
            $entityInstance->setUpdatedAt(new \DateTimeImmutable());
        }

        parent::updateEntity($entityManager, $entityInstance);
    }

    public function changeStatus(EntityManagerInterface $entityManager): \Symfony\Component\HttpFoundation\RedirectResponse
    {
        $facture = $this->getContext()->getEntity()->getInstance();
        $actionName = $this->getContext()->getRequest()->get('action');

        if (!$actionName) {
            $this->addFlash('danger', 'Action non spécifiée.');
            return $this->redirectToRoute('admin', [
                'crudAction' => 'index',
                'crudControllerFqcn' => self::class
            ]);
        }

        $newStatus = $this->workflowService->getStatusForAction($this->workflowConfig, $actionName);

        if ($newStatus !== null) {
            $facture->setStatus($newStatus);

            // Set payment date when marked as paid
            if ($newStatus === Facture::STATUS_PAYE) {
                $facture->setDatePaiement(new \DateTimeImmutable());
            }

            // Set new deadline when marked as relance (30 days from now)
            if ($newStatus === Facture::STATUS_RELANCE) {
                $facture->setDateEcheance(new \DateTimeImmutable('+30 days'));
            }

            $entityManager->flush();

            $this->addFlash('success', $this->workflowService->getStatusChangeMessage($this->workflowConfig, $newStatus));
        } else {
            $this->addFlash('danger', 'Action non reconnue: ' . $actionName);
        }

        return $this->redirectToRoute('admin', [
            'crudAction' => 'index',
            'crudControllerFqcn' => self::class
        ]);
    }

    public function deleteEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof Facture) {
            // If this invoice is linked to a quote, remove the link before deletion
            if ($entityInstance->getDevis()) {
                $devis = $entityInstance->getDevis();
                $devis->setFacture(null);
                $entityInstance->setDevis(null);
                $entityManager->flush();
            }
        }

        parent::deleteEntity($entityManager, $entityInstance);
    }

    public function generatePdf(PdfGeneratorService $pdfGenerator): \Symfony\Component\HttpFoundation\BinaryFileResponse|\Symfony\Component\HttpFoundation\RedirectResponse
    {
        $facture = $this->getContext()->getEntity()->getInstance();

        try {
            // Use DOMPDF for PDF generation (pure PHP, no Node.js dependency)
            $filepath = $pdfGenerator->generateFacturePdfWithDompdf($facture);

            // Return PDF as download
            return $this->file($filepath, basename($filepath));

        } catch (\Exception $e) {
            $this->addFlash('danger', 'Erreur lors de la génération du PDF: ' . $e->getMessage());

            return $this->redirectToRoute('admin', [
                'crudAction' => 'detail',
                'crudControllerFqcn' => self::class,
                'entityId' => $facture->getId()
            ]);
        }
    }
}