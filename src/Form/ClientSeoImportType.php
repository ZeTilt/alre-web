<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class ClientSeoImportType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('file', FileType::class, [
                'label' => 'Fichier CSV ou ZIP (export Google Search Console)',
                'attr' => [
                    'accept' => '.csv,.zip',
                    'class' => 'form-control',
                ],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Veuillez selectionner un fichier.']),
                    new Assert\File([
                        'maxSize' => '10M',
                        'mimeTypes' => [
                            'text/csv',
                            'text/plain',
                            'application/csv',
                            'application/zip',
                            'application/x-zip-compressed',
                        ],
                        'mimeTypesMessage' => 'Format accepte : CSV ou ZIP uniquement.',
                    ]),
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([]);
    }
}
