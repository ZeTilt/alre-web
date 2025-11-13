<?php

namespace App\Form;

use App\Entity\ContactMessage;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class ContactType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstName', TextType::class, [
                'label' => 'Prénom',
                'attr' => [
                    'placeholder' => 'Votre prénom',
                    'class' => 'form-control'
                ],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Veuillez renseigner votre prénom']),
                    new Assert\Length(['min' => 2, 'max' => 255])
                ]
            ])
            ->add('lastName', TextType::class, [
                'label' => 'Nom',
                'attr' => [
                    'placeholder' => 'Votre nom',
                    'class' => 'form-control'
                ],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Veuillez renseigner votre nom']),
                    new Assert\Length(['min' => 2, 'max' => 255])
                ]
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'attr' => [
                    'placeholder' => 'votre.email@exemple.fr',
                    'class' => 'form-control'
                ],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Veuillez renseigner votre email']),
                    new Assert\Email(['message' => 'Veuillez entrer une adresse email valide'])
                ]
            ])
            ->add('phone', TelType::class, [
                'label' => 'Téléphone',
                'required' => false,
                'attr' => [
                    'placeholder' => '06 12 34 56 78',
                    'class' => 'form-control'
                ],
                'constraints' => [
                    new Assert\Regex([
                        'pattern' => '/^(?:(?:\+|00)33|0)\s*[1-9](?:[\s.-]*\d{2}){4}$/',
                        'message' => 'Veuillez entrer un numéro de téléphone français valide'
                    ])
                ]
            ])
            ->add('company', TextType::class, [
                'label' => 'Entreprise',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Nom de votre entreprise',
                    'class' => 'form-control'
                ],
                'constraints' => [
                    new Assert\Length(['max' => 255])
                ]
            ])
            ->add('projectType', ChoiceType::class, [
                'label' => 'Type de projet',
                'placeholder' => 'Sélectionnez le type de projet',
                'attr' => ['class' => 'form-control'],
                'choices' => [
                    'Site Vitrine' => 'vitrine',
                    'E-commerce' => 'ecommerce',
                    'Application sur mesure' => 'sur-mesure',
                    'Refonte de site existant' => 'refonte',
                    'Maintenance / Support' => 'maintenance',
                    'Autre / Je ne sais pas' => 'autre'
                ],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Veuillez sélectionner un type de projet'])
                ]
            ])
            ->add('budget', ChoiceType::class, [
                'label' => 'Budget estimé',
                'required' => false,
                'placeholder' => 'Sélectionnez votre budget',
                'attr' => ['class' => 'form-control'],
                'choices' => [
                    'Moins de 1 000 €' => 'moins-1000',
                    '1 000 € - 2 000 €' => '1000-2000',
                    '2 000 € - 5 000 €' => '2000-5000',
                    '5 000 € - 10 000 €' => '5000-10000',
                    'Plus de 10 000 €' => 'plus-10000',
                    'Je ne sais pas encore' => 'non-defini'
                ]
            ])
            ->add('message', TextareaType::class, [
                'label' => 'Votre message',
                'attr' => [
                    'placeholder' => 'Décrivez votre projet et vos besoins...',
                    'class' => 'form-control',
                    'rows' => 6
                ],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Veuillez décrire votre projet']),
                    new Assert\Length(['min' => 10, 'max' => 5000, 'minMessage' => 'Votre message est trop court'])
                ]
            ])
            ->add('rgpdConsent', CheckboxType::class, [
                'label' => 'J\'accepte que mes données soient utilisées pour me recontacter concernant ma demande',
                'required' => true,
                'attr' => ['class' => 'form-check-input'],
                'label_attr' => ['class' => 'form-check-label'],
                'constraints' => [
                    new Assert\IsTrue(['message' => 'Vous devez accepter la politique de confidentialité'])
                ]
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ContactMessage::class,
        ]);
    }
}
