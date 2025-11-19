<?php

namespace App\Controller\Admin;

use App\Entity\Testimonial;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;

class TestimonialCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Testimonial::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            TextField::new('clientName', 'Nom du client')
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
            TextField::new('clientCompany', 'Entreprise/Association')->setHelp('Optionnel'),
            TextareaField::new('content', 'Témoignage')
                ->setHelp('Le contenu du témoignage'),
            IntegerField::new('rating', 'Note')
                ->setHelp('De 1 à 5 étoiles'),
            TextField::new('projectType', 'Type de projet')->setHelp('Ex: Site vitrine, E-commerce...'),
            ImageField::new('photo', 'Photo du client')
                ->setBasePath('uploads/testimonials')
                ->setUploadDir('public/uploads/testimonials')
                ->setUploadedFileNamePattern('[randomhash].[extension]')
                ->setHelp('Photo optionnelle')
                ->hideOnIndex(),
            BooleanField::new('isPublished', 'Publié'),
            DateTimeField::new('createdAt', 'Date de création')->hideOnForm(),
        ];
    }
}
