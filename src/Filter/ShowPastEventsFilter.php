<?php

namespace App\Filter;

use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Filter\FilterInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FilterDataDto;
use EasyCorp\Bundle\EasyAdminBundle\Filter\FilterTrait;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;

class ShowPastEventsFilter implements FilterInterface
{
    use FilterTrait;

    public static function new(string $propertyName, ?string $label = null): self
    {
        return (new self())
            ->setFilterFqcn(__CLASS__)
            ->setProperty($propertyName)
            ->setLabel($label ?? 'Afficher les événements passés')
            ->setFormType(CheckboxType::class)
            ->setFormTypeOptions([
                'required' => false,
            ]);
    }

    public function apply(QueryBuilder $queryBuilder, FilterDataDto $filterDataDto, ?FieldDto $fieldDto, EntityDto $entityDto): void
    {
        // The filtering logic is handled in createIndexQueryBuilder
        // This filter just needs to exist to show the checkbox
    }
}
