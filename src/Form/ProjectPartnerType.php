<?php

namespace App\Form;

use App\Entity\Partner;
use App\Entity\ProjectPartner;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ProjectPartnerType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('partner', EntityType::class, [
                'class' => Partner::class,
                'query_builder' => function (EntityRepository $er) {
                    return $er->createQueryBuilder('p')
                        ->where('p.isActive = :active')
                        ->setParameter('active', true)
                        ->orderBy('p.name', 'ASC');
                },
                'choice_label' => 'name',
                'label' => 'Partenaire',
                'placeholder' => 'Sélectionnez un partenaire',
                'required' => true,
            ])
            ->add('selectedDomains', ChoiceType::class, [
                'label' => 'Domaines concernés',
                'choices' => [],
                'multiple' => true,
                'expanded' => true,
                'required' => false,
                'help' => 'Sélectionnez les domaines d\'expertise concernés pour ce projet',
            ]);

        // Dynamically populate the domains based on selected partner
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) {
            $projectPartner = $event->getData();
            $form = $event->getForm();

            if ($projectPartner && $projectPartner->getPartner()) {
                $partner = $projectPartner->getPartner();
                $domains = $partner->getDomains();

                $choices = array_combine($domains, $domains);

                $form->add('selectedDomains', ChoiceType::class, [
                    'label' => 'Domaines concernés',
                    'choices' => $choices,
                    'multiple' => true,
                    'expanded' => true,
                    'required' => false,
                    'help' => 'Sélectionnez les domaines d\'expertise concernés pour ce projet',
                ]);
            }
        });

        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) {
            $data = $event->getData();
            $form = $event->getForm();

            if (isset($data['partner']) && $data['partner']) {
                // We need to fetch the partner to get its domains
                // This is a bit tricky because we don't have access to the EntityManager here
                // For now, we'll handle this in the controller or use a different approach
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ProjectPartner::class,
        ]);
    }
}
