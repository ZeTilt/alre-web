<?php

namespace App\Controller\Admin;

use App\Entity\Project;
use App\Service\ImageOptimizerService;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\UrlField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;

class ProjectCrudController extends AbstractCrudController
{
    private ImageOptimizerService $imageOptimizer;
    private string $projectDir;

    public function __construct(
        ImageOptimizerService $imageOptimizer,
        string $projectDir
    ) {
        $this->imageOptimizer = $imageOptimizer;
        $this->projectDir = $projectDir;
    }

    public static function getEntityFqcn(): string
    {
        return Project::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->hideOnForm(),
            TextField::new('title', 'Titre du projet'),
            TextField::new('slug', 'Slug (URL)')->setHelp('Généré automatiquement si laissé vide'),
            TextField::new('clientName', 'Nom du client')->setHelp('Optionnel si confidentiel'),
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
            ArrayField::new('partners', 'Partenaires')
                ->setHelp('Format JSON : [{"name":"Raison sociale","url":"https://example.com"}]')
                ->hideOnIndex(),
            TextEditorField::new('context', 'Contexte & Besoin')->hideOnIndex(),
            TextEditorField::new('solutions', 'Solutions apportées')->hideOnIndex(),
            TextEditorField::new('results', 'Résultats obtenus')->hideOnIndex(),
            ImageField::new('imageFilename', 'Image du projet')
                ->setBasePath('uploads/projects')
                ->setUploadDir('public/uploads/projects')
                ->setUploadedFileNamePattern('[randomhash].[extension]')
                ->setHelp('Image de présentation du projet'),
            UrlField::new('projectUrl', 'URL du projet')->hideOnIndex(),
            DateField::new('completionDate', 'Date de réalisation'),
            BooleanField::new('featured', 'Projet mis en avant'),
            BooleanField::new('isPublished', 'Publié'),
        ];
    }

    /**
     * Appelé après la création d'une nouvelle entité
     */
    public function persistEntity($entityManager, $entityInstance): void
    {
        parent::persistEntity($entityManager, $entityInstance);

        if ($entityInstance instanceof Project) {
            $this->optimizeProjectImage($entityInstance);
        }
    }

    /**
     * Appelé après la modification d'une entité existante
     */
    public function updateEntity($entityManager, $entityInstance): void
    {
        parent::updateEntity($entityManager, $entityInstance);

        if ($entityInstance instanceof Project) {
            $this->optimizeProjectImage($entityInstance);
        }
    }

    /**
     * Optimise l'image du projet et génère toutes les variantes
     */
    private function optimizeProjectImage(Project $project): void
    {
        $imageFilename = $project->getImageFilename();

        // Si pas d'image, rien à faire
        if (!$imageFilename) {
            return;
        }

        $imagePath = $this->projectDir . '/public/uploads/projects/' . $imageFilename;

        // Vérifier que le fichier existe
        if (!file_exists($imagePath)) {
            return;
        }

        try {
            // Optimiser l'image et générer toutes les variantes
            // sizes: thumbnail (400x300), medium (800x600), large (1600x1200)
            $results = $this->imageOptimizer->optimize($imagePath, [
                'quality' => 85,
                'formats' => ['webp'],
                'sizes' => ['thumbnail', 'medium', 'large']
            ]);

            // Les variantes sont automatiquement créées avec les noms :
            // - original.jpg (compressé)
            // - original.webp
            // - original-thumbnail.jpg + .webp
            // - original-medium.jpg + .webp
            // - original-large.jpg + .webp

        } catch (\Exception $e) {
            // En cas d'erreur, on log mais on ne bloque pas la sauvegarde
            error_log("Erreur lors de l'optimisation de l'image du projet {$project->getId()}: " . $e->getMessage());
        }
    }
}
