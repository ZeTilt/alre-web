<?php

namespace App\Controller\Admin;

use App\Entity\Company;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TelephoneField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\UrlField;
use Doctrine\ORM\EntityManagerInterface;

class CompanyCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Company::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Informations Entreprise')
            ->setEntityLabelInPlural('Informations Entreprise')
            ->setPageTitle('index', 'Informations de l\'entreprise')
            ->setPageTitle('new', 'Ajouter les informations de l\'entreprise')
            ->setPageTitle('edit', 'Modifier les informations de l\'entreprise')
            ->setPageTitle('detail', 'Détails de l\'entreprise')
            ->setDefaultSort(['id' => 'ASC'])
            ->setPaginatorPageSize(1);
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            TextField::new('name', 'Nom de l\'entreprise')
                ->setRequired(true)
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
            TextField::new('ownerName', 'Nom du dirigeant')
                ->setRequired(true),
            TextField::new('title', 'Titre/Fonction')
                ->setRequired(true),
            ImageField::new('homePortraitPhoto', 'Photo portrait (Accueil)')
                ->setBasePath('/uploads/profile')
                ->setUploadDir('public/uploads/profile')
                ->setUploadedFileNamePattern('[slug]-[timestamp].[extension]')
                ->setRequired(false)
                ->setHelp('Photo au format portrait pour la page d\'accueil'),
            ImageField::new('aboutWidePhoto', 'Photo plan large (À propos)')
                ->setBasePath('/uploads/profile')
                ->setUploadDir('public/uploads/profile')
                ->setUploadedFileNamePattern('[slug]-[timestamp].[extension]')
                ->setRequired(false)
                ->setHelp('Photo en plan large pour la page "À propos"'),
            TextareaField::new('address', 'Adresse')
                ->setRequired(true)
                ->setNumOfRows(2),
            TextField::new('postalCode', 'Code postal')
                ->setRequired(true),
            TextField::new('city', 'Ville')
                ->setRequired(true),
            TelephoneField::new('phone', 'Téléphone')
                ->setRequired(true),
            EmailField::new('email', 'Email')
                ->setRequired(true),
            TextField::new('siret', 'SIRET')
                ->setRequired(true),
            UrlField::new('website', 'Site web')
                ->setRequired(false),
            TextField::new('legalStatus', 'Statut juridique')
                ->setRequired(false),
            TextareaField::new('legalMentions', 'Mentions légales')
                ->setRequired(false)
                ->setHelp('Mentions légales à afficher sur les documents')
                ->setNumOfRows(3),
            TextareaField::new('devisConditions', 'Conditions des devis')
                ->setRequired(false)
                ->setHelp('Conditions générales par défaut pour les devis (validité, acceptation, etc.)')
                ->setNumOfRows(4),
            TextareaField::new('factureConditions', 'Conditions des factures')
                ->setRequired(false)
                ->setHelp('Conditions de paiement par défaut pour les factures (délais, modes de paiement, pénalités, etc.)')
                ->setNumOfRows(4),

            // Paramètres auto-entrepreneur pour dashboard
            MoneyField::new('plafondCaAnnuel', 'Plafond CA annuel')
                ->setRequired(false)
                ->setCurrency('EUR')
                ->setHelp('Plafond CA auto-entrepreneur (ex: 77 700 € pour prestations de services)'),
            NumberField::new('tauxCotisationsUrssaf', 'Taux cotisations URSSAF (%)')
                ->setRequired(false)
                ->setNumDecimals(2)
                ->setHelp('Taux de cotisations sociales (ex: 21.2% pour prestations de services)'),
            MoneyField::new('objectifCaMensuel', 'Objectif CA mensuel')
                ->setRequired(false)
                ->setCurrency('EUR')
                ->setHelp('Objectif de chiffre d\'affaires mensuel'),
            MoneyField::new('objectifCaAnnuel', 'Objectif CA annuel')
                ->setRequired(false)
                ->setCurrency('EUR')
                ->setHelp('Objectif de chiffre d\'affaires annuel'),
            IntegerField::new('anneeFiscaleEnCours', 'Année fiscale en cours')
                ->setRequired(false)
                ->setHelp('Année fiscale pour le calcul du CA (défaut: année en cours)'),
        ];
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof Company) {
            // Ensure only one company record exists
            $existingCompany = $entityManager->getRepository(Company::class)->findOneBy([]);
            if ($existingCompany) {
                $this->addFlash('error', 'Une entreprise existe déjà. Vous ne pouvez en avoir qu\'une seule.');
                return;
            }
        }

        parent::persistEntity($entityManager, $entityInstance);
    }
}