<?php

namespace App\Form;

use App\Entity\ProspectContact;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ProspectContactType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstName', TextType::class, [
                'label' => 'Prénom',
                'required' => true,
                'attr' => [
                    'placeholder' => 'Prénom du contact'
                ]
            ])
            ->add('lastName', TextType::class, [
                'label' => 'Nom',
                'required' => true,
                'attr' => [
                    'placeholder' => 'Nom du contact'
                ]
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'required' => false,
                'attr' => [
                    'placeholder' => 'email@exemple.com'
                ]
            ])
            ->add('phone', TelType::class, [
                'label' => 'Téléphone',
                'required' => false,
                'attr' => [
                    'placeholder' => '06 12 34 56 78'
                ]
            ])
            ->add('role', TextType::class, [
                'label' => 'Fonction',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Ex: Directeur, Responsable comm...'
                ]
            ])
            ->add('linkedinUrl', UrlType::class, [
                'label' => 'Profil LinkedIn',
                'required' => false,
                'attr' => [
                    'placeholder' => 'https://linkedin.com/in/...'
                ]
            ])
            ->add('isPrimary', CheckboxType::class, [
                'label' => 'Contact principal',
                'required' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ProspectContact::class,
        ]);
    }
}
