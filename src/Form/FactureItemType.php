<?php

namespace App\Form;

use App\Entity\FactureItem;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class FactureItemType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => true,
                'attr' => [
                    'rows' => 3,
                    'placeholder' => 'Décrivez la prestation ou le produit...'
                ]
            ])
            ->add('quantity', NumberType::class, [
                'label' => 'Quantité',
                'required' => true,
                'scale' => 2,
                'attr' => [
                    'min' => 0,
                    'step' => 0.01
                ]
            ])
            ->add('unit', TextType::class, [
                'label' => 'Unité',
                'required' => false,
                'attr' => [
                    'placeholder' => 'h, jour, pièce, etc.'
                ]
            ])
            ->add('unitPrice', MoneyType::class, [
                'label' => 'Prix unitaire',
                'required' => true,
                'currency' => 'EUR',
                'scale' => 2,
                'divisor' => 1,
                'attr' => [
                    'min' => 0,
                    'step' => 0.01
                ]
            ])
            ->add('discount', NumberType::class, [
                'label' => 'Remise (%)',
                'required' => false,
                'scale' => 2,
                'attr' => [
                    'min' => 0,
                    'max' => 100,
                    'step' => 0.01,
                    'placeholder' => '0'
                ]
            ])
            ->add('position', NumberType::class, [
                'label' => 'Position',
                'required' => false,
                'attr' => [
                    'min' => 0
                ]
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => FactureItem::class,
        ]);
    }
}