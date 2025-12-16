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
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use App\Service\PdfGeneratorService;
use App\Service\NumberingService;
use App\Service\CompanyService;
use App\Service\Workflow\WorkflowService;
use App\Service\Workflow\Config\DevisWorkflowConfig;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;

class DevisCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly NumberingService $numberingService,
        private readonly CompanyService $companyService,
        private readonly WorkflowService $workflowService,
        private readonly DevisWorkflowConfig $workflowConfig
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Devis::class;
    }

    public function createEntity(string $entityFqcn): Devis
    {
        $devis = new Devis();
        $devis->setNumber($this->numberingService->generateDevisNumber());
        return $devis;
    }

    public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): QueryBuilder
    {
        $qb = parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters);

        // Eager load relations to avoid N+1 queries
        $qb->leftJoin('entity.client', 'c')->addSelect('c')
           ->leftJoin('entity.items', 'i')->addSelect('i')
           ->leftJoin('entity.createdBy', 'u')->addSelect('u');

        return $qb;
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
            ChoiceField::new('status', 'Statut')
                ->setChoices(Devis::getStatusChoices())
                ->renderAsBadges([
                    Devis::STATUS_BROUILLON => 'secondary',
                    Devis::STATUS_A_ENVOYER => 'warning',
                    Devis::STATUS_ENVOYE => 'info',
                    Devis::STATUS_A_RELANCER => 'danger',
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
            ->addCssClass('btn btn-success')
            ->displayIf(function ($entity) {
                // Afficher pour tous les devis acceptés qui n'ont pas encore de facture
                return $entity->getStatus() === Devis::STATUS_ACCEPTE && $entity->getFacture() === null;
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

        $duplicate = Action::new('duplicate', 'Dupliquer')
            ->linkToCrudAction('duplicate')
            ->setIcon('fas fa-copy')
            ->addCssClass('btn btn-secondary')
            ->displayIf(function ($entity) {
                return in_array($entity->getStatus(), [Devis::STATUS_REFUSE, Devis::STATUS_ANNULE, Devis::STATUS_EXPIRE]);
            });

        return $actions
            ->add(Crud::PAGE_INDEX, $generateInvoice)
            ->add(Crud::PAGE_INDEX, $generatePdf)
            ->add(Crud::PAGE_INDEX, $markAsReady)
            ->add(Crud::PAGE_INDEX, $markAsSent)
            ->add(Crud::PAGE_INDEX, $markAsAccepted)
            ->add(Crud::PAGE_INDEX, $markAsRejected)
            ->add(Crud::PAGE_INDEX, $duplicate)
            ->add(Crud::PAGE_DETAIL, $generateInvoice)
            ->add(Crud::PAGE_DETAIL, $generatePdf)
            ->add(Crud::PAGE_DETAIL, $duplicate);
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

            // Set default conditions from company if not set
            if (!$entityInstance->getConditions()) {
                $company = $this->companyService->getCompanyOrDefault();
                if ($company->getDevisConditions()) {
                    $entityInstance->setConditions($company->getDevisConditions());
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

            // Sync acompte/percentage values
            $entityInstance->syncAcompteValues();
        }

        parent::persistEntity($entityManager, $entityInstance);
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof Devis) {
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

            // Sync acompte/percentage values
            $entityInstance->syncAcompteValues();

            // Update timestamp
            $entityInstance->setUpdatedAt(new \DateTimeImmutable());
        }

        parent::updateEntity($entityManager, $entityInstance);
    }

    public function changeStatus(EntityManagerInterface $entityManager): \Symfony\Component\HttpFoundation\RedirectResponse
    {
        $devis = $this->getContext()->getEntity()->getInstance();
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
            $devis->setStatus($newStatus);

            // Set new validity date when marked as relance (30 days from now)
            if ($newStatus === Devis::STATUS_RELANCE) {
                $devis->setDateValidite(new \DateTimeImmutable('+30 days'));
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

    public function generateInvoice(EntityManagerInterface $entityManager): \Symfony\Component\HttpFoundation\RedirectResponse
    {
        $devis = $this->getContext()->getEntity()->getInstance();
        
        if (!$devis->canBeConverted()) {
            $this->addFlash('danger', 'Ce devis ne peut pas être converti en facture.');
            return $this->redirectToRoute('admin', ['crudAction' => 'detail', 'crudControllerFqcn' => self::class, 'entityId' => $devis->getId()]);
        }

        // Create new invoice from quote
        $facture = new \App\Entity\Facture();
        $facture->setDevis($devis);
        $facture->setClient($devis->getClient());
        $facture->setCreatedBy($this->getUser());
        $facture->setTitle($devis->getTitle());
        $facture->setDescription($devis->getDescription());
        $facture->setAdditionalInfo($devis->getAdditionalInfo());
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

    public function generatePdf(PdfGeneratorService $pdfGenerator): \Symfony\Component\HttpFoundation\BinaryFileResponse|\Symfony\Component\HttpFoundation\RedirectResponse
    {
        $devis = $this->getContext()->getEntity()->getInstance();

        try {
            // Use DOMPDF for PDF generation (pure PHP, no Node.js dependency)
            $filepath = $pdfGenerator->generateDevisPdfWithDompdf($devis);

            // Return PDF as download
            return $this->file($filepath, basename($filepath));

        } catch (\Exception $e) {
            $this->addFlash('danger', 'Erreur lors de la génération du PDF: ' . $e->getMessage());

            return $this->redirectToRoute('admin', [
                'crudAction' => 'detail',
                'crudControllerFqcn' => self::class,
                'entityId' => $devis->getId()
            ]);
        }
    }

    public function duplicate(EntityManagerInterface $entityManager): \Symfony\Component\HttpFoundation\RedirectResponse
    {
        /** @var Devis $original */
        $original = $this->getContext()->getEntity()->getInstance();

        // Create new devis
        $newDevis = new Devis();
        $newDevis->setNumber($this->numberingService->generateDevisNumber());
        $newDevis->setTitle($original->getTitle() . ' (copie)');
        $newDevis->setDescription($original->getDescription());
        $newDevis->setAdditionalInfo($original->getAdditionalInfo());
        $newDevis->setClient($original->getClient());
        $newDevis->setStatus(Devis::STATUS_BROUILLON);
        $newDevis->setVatRate($original->getVatRate());
        $newDevis->setConditions($original->getConditions());
        $newDevis->setNotes($original->getNotes());
        $newDevis->setAcompte($original->getAcompte());
        $newDevis->setAcomptePercentage($original->getAcomptePercentage());
        $newDevis->setCreatedBy($this->getUser());
        $newDevis->setDateCreation(new \DateTimeImmutable());
        $newDevis->setDateValidite(new \DateTimeImmutable('+30 days'));

        // Copy items
        foreach ($original->getItems() as $item) {
            $newItem = new \App\Entity\DevisItem();
            $newItem->setDevis($newDevis);
            $newItem->setDescription($item->getDescription());
            $newItem->setUnit($item->getUnit());
            $newItem->setPosition($item->getPosition());
            $newItem->setDiscount($item->getDiscount());
            $newItem->setVatRate($item->getVatRate());
            $newItem->setQuantity($item->getQuantity());
            $newItem->setUnitPrice($item->getUnitPrice()); // Triggers calculateTotal

            $newDevis->addItem($newItem);
        }

        // Calculate totals
        $newDevis->calculateTotals();

        $entityManager->persist($newDevis);
        $entityManager->flush();

        $this->addFlash('success', 'Devis dupliqué avec succès.');

        return $this->redirectToRoute('admin', [
            'crudAction' => 'edit',
            'crudControllerFqcn' => self::class,
            'entityId' => $newDevis->getId()
        ]);
    }
}