<?php

namespace App\Form;

use App\Entity\Client;
use App\Entity\ClientSite;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class ClientSiteType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('client', EntityType::class, [
                'class' => Client::class,
                'label' => 'Client',
                'choice_label' => 'displayName',
                'placeholder' => 'Selectionner un client',
                'attr' => ['class' => 'form-control'],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Veuillez selectionner un client.']),
                ],
            ])
            ->add('name', TextType::class, [
                'label' => 'Nom du site',
                'attr' => [
                    'placeholder' => 'Ex: Site vitrine de Mon Client',
                    'class' => 'form-control',
                ],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Veuillez renseigner un nom.']),
                    new Assert\Length(['max' => 255]),
                ],
            ])
            ->add('url', UrlType::class, [
                'label' => 'URL du site',
                'attr' => [
                    'placeholder' => 'https://www.example.com',
                    'class' => 'form-control',
                ],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Veuillez renseigner l\'URL.']),
                    new Assert\Url(['message' => 'Veuillez entrer une URL valide.']),
                    new Assert\Length(['max' => 500]),
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ClientSite::class,
        ]);
    }
}
