<?php

namespace App\Form;

use App\Entity\Client;
use App\Entity\ClientSite;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
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
            // Planning import GSC
            ->add('importDay', ChoiceType::class, [
                'label' => 'Jour d\'import GSC',
                'required' => false,
                'placeholder' => 'Non planifie',
                'choices' => [
                    'Lundi' => 1,
                    'Mardi' => 2,
                    'Mercredi' => 3,
                    'Jeudi' => 4,
                    'Vendredi' => 5,
                ],
                'attr' => ['class' => 'form-control'],
            ])
            ->add('importSlot', ChoiceType::class, [
                'label' => 'Creneau d\'import',
                'required' => false,
                'placeholder' => '-',
                'choices' => [
                    'Matin' => 'morning',
                    'Apres-midi' => 'afternoon',
                ],
                'attr' => ['class' => 'form-control'],
            ])
            // Planning compte rendu
            ->add('reportWeekOfMonth', ChoiceType::class, [
                'label' => 'Semaine du rapport',
                'required' => false,
                'placeholder' => 'Non planifie',
                'choices' => [
                    '1ere semaine' => 1,
                    '2eme semaine' => 2,
                    '3eme semaine' => 3,
                    '4eme semaine' => 4,
                ],
                'attr' => ['class' => 'form-control'],
            ])
            ->add('reportDayOfWeek', ChoiceType::class, [
                'label' => 'Jour du rapport',
                'required' => false,
                'placeholder' => '-',
                'choices' => [
                    'Lundi' => 1,
                    'Mardi' => 2,
                    'Mercredi' => 3,
                    'Jeudi' => 4,
                    'Vendredi' => 5,
                ],
                'attr' => ['class' => 'form-control'],
            ])
            ->add('reportSlot', ChoiceType::class, [
                'label' => 'Creneau du rapport',
                'required' => false,
                'placeholder' => '-',
                'choices' => [
                    'Matin' => 'morning',
                    'Apres-midi' => 'afternoon',
                ],
                'attr' => ['class' => 'form-control'],
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
