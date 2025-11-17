<?php

namespace App\Controller\Admin;

use App\Entity\Project;
use App\Form\ProjectPartnerType;
use App\Form\ProjectImageType;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\UrlField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class ProjectCrudController extends AbstractCrudController
{
    public function __construct(
        private string $projectDir
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Project::class;
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if (!$entityInstance instanceof Project) return;

        $this->processImageUploads($entityInstance);
        parent::persistEntity($entityManager, $entityInstance);
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if (!$entityInstance instanceof Project) return;

        $this->processImageUploads($entityInstance);
        parent::updateEntity($entityManager, $entityInstance);
    }

    private function processImageUploads(Project $project): void
    {
        $uploadDir = $this->projectDir . '/public/uploads/projects';

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $request = $this->container->get('request_stack')->getCurrentRequest();
        if (!$request) {
            return;
        }

        // Récupérer tous les fichiers uploadés
        $allFiles = $request->files->all();

        // Chercher les fichiers dans la structure du formulaire
        if (isset($allFiles['Project']['images'])) {
            $imageFiles = $allFiles['Project']['images'];

            $imageIndex = 0;
            foreach ($project->getImages() as $image) {
                if (isset($imageFiles[$imageIndex]['imageFile']) && $imageFiles[$imageIndex]['imageFile'] instanceof UploadedFile) {
                    $uploadedFile = $imageFiles[$imageIndex]['imageFile'];

                    // Supprimer l'ancien fichier si il existe
                    if ($image->getImageFilename()) {
                        $oldFile = $uploadDir . '/' . $image->getImageFilename();
                        if (file_exists($oldFile)) {
                            unlink($oldFile);
                        }
                    }

                    // Générer un nom de fichier unique
                    $filename = uniqid() . '.' . $uploadedFile->guessExtension();

                    // Déplacer le fichier
                    $uploadedFile->move($uploadDir, $filename);

                    // Mettre à jour l'entité
                    $image->setImageFilename($filename);
                }
                $imageIndex++;
            }
        }
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->hideOnForm(),
            TextField::new('title', 'Titre du projet'),
            TextField::new('slug', 'Slug (URL)')
                ->setHelp('Généré automatiquement si laissé vide')
                ->setRequired(false),
            AssociationField::new('client', 'Client')
                ->setHelp('Le client pour lequel le projet a été réalisé')
                ->setRequired(false),
            ChoiceField::new('category', 'Catégorie')
                ->setChoices([
                    'Site Vitrine' => 'vitrine',
                    'E-commerce' => 'ecommerce',
                    'Sur Mesure' => 'sur-mesure',
                    'Application Métier' => 'application',
                    'Refonte' => 'refonte',
                ]),
            TextareaField::new('shortDescription', 'Description courte')
                ->setHelp('Description affichée sur la page portfolio'),
            TextEditorField::new('fullDescription', 'Description complète')->hideOnIndex(),
            ArrayField::new('technologies', 'Technologies utilisées')
                ->setHelp('Ex: Symfony, React, MySQL...'),
            CollectionField::new('images', 'Images')
                ->setEntryType(ProjectImageType::class)
                ->setFormTypeOption('by_reference', false)
                ->setHelp('Ajoutez des images pour illustrer le projet')
                ->hideOnIndex()
                ->onlyOnForms(),
            CollectionField::new('projectPartners', 'Partenaires')
                ->setEntryType(ProjectPartnerType::class)
                ->setFormTypeOption('by_reference', false)
                ->setHelp('Ajoutez les partenaires qui ont collaboré sur ce projet')
                ->hideOnIndex()
                ->onlyOnForms(),
            TextEditorField::new('context', 'Contexte & Besoin')->hideOnIndex(),
            TextEditorField::new('solutions', 'Solutions apportées')->hideOnIndex(),
            TextEditorField::new('results', 'Résultats obtenus')->hideOnIndex(),
            UrlField::new('projectUrl', 'URL du projet')->hideOnIndex(),
            IntegerField::new('completionYear', 'Année de réalisation')
                ->setHelp('Ex: 2024')
                ->setRequired(false),
            BooleanField::new('featured', 'Projet mis en avant'),
            BooleanField::new('isPublished', 'Publié'),
        ];
    }
}
