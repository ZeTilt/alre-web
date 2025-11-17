<?php

namespace App\Form;

use App\Entity\Partner;
use App\Entity\ProjectPartner;
use App\Repository\PartnerRepository;
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
    public function __construct(
        private PartnerRepository $partnerRepository
    ) {
    }

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
                'choice_attr' => function (Partner $partner) {
                    return ['data-domains' => json_encode($partner->getDomains())];
                },
                'label' => 'Partenaire',
                'placeholder' => 'Sélectionnez un partenaire',
                'required' => true,
                'attr' => ['class' => 'partner-select'],
            ])
            ->add('selectedDomains', ChoiceType::class, [
                'label' => 'Domaines concernés',
                'choices' => [],
                'multiple' => true,
                'expanded' => true,
                'required' => false,
                'help' => 'Tous les domaines sont sélectionnés par défaut. Désélectionnez ceux qui ne sont pas concernés.',
                'row_attr' => ['class' => 'domains-field-wrapper'],
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
                $partner = $this->partnerRepository->find($data['partner']);

                if ($partner) {
                    $domains = $partner->getDomains();
                    $choices = array_combine($domains, $domains);

                    // Si aucun domaine n'est sélectionné dans les données soumises,
                    // on présélectionne tous les domaines
                    if (!isset($data['selectedDomains']) || empty($data['selectedDomains'])) {
                        $data['selectedDomains'] = $domains;
                        $event->setData($data);
                    }

                    $form->add('selectedDomains', ChoiceType::class, [
                        'label' => 'Domaines concernés',
                        'choices' => $choices,
                        'multiple' => true,
                        'expanded' => true,
                        'required' => false,
                        'help' => 'Désélectionnez les domaines non concernés par ce projet',
                    ]);
                }
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
