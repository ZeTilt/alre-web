<?php

namespace App\Form;

use App\Entity\ProjectImage;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

class ProjectImageType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var ProjectImage|null $image */
        $image = $builder->getData();
        $helpText = 'Formats acceptés : JPG, PNG, WebP (max 5Mo)';

        if ($image && $image->getImageFilename()) {
            $helpText .= ' | Image actuelle : ' . $image->getImageFilename();
        }

        $builder
            ->add('imageFile', FileType::class, [
                'label' => 'Image',
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new File([
                        'maxSize' => '5M',
                        'mimeTypes' => [
                            'image/jpeg',
                            'image/jpg',
                            'image/png',
                            'image/webp',
                        ],
                        'mimeTypesMessage' => 'Formats acceptés : JPG, PNG, WebP',
                    ])
                ],
                'help' => $helpText,
            ])
            ->add('altText', TextType::class, [
                'label' => 'Texte alternatif',
                'required' => false,
                'help' => 'Description de l\'image pour l\'accessibilité et le SEO. Si vide, la légende sera utilisée.',
            ])
            ->add('caption', TextareaType::class, [
                'label' => 'Légende',
                'required' => false,
                'attr' => ['rows' => 2],
            ])
            ->add('isFeatured', CheckboxType::class, [
                'label' => 'Image principale',
                'required' => false,
                'help' => 'Affichée sur la carte du portfolio',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ProjectImage::class,
        ]);
    }
}
