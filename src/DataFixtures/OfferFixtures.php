<?php

namespace App\DataFixtures;

use App\Entity\Offer;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;

class OfferFixtures extends Fixture implements FixtureGroupInterface
{
    public static function getGroups(): array
    {
        return ['offers'];
    }

    public function load(ObjectManager $manager): void
    {
        $offers = [
            [
                'name' => 'Page Unique',
                'slug' => 'page_unique',
                'category' => 'creation',
                'price' => '697.00',
                'promoPrice' => '493.00',
                'promoEndDate' => new \DateTime('2026-03-31 23:59:59'),
                'promoLabel' => 'Offre Lancement',
                'isRecurring' => false,
                'priceSuffix' => null,
                'shortDescription' => 'Landing page optimisée',
                'isFeatured' => false,
                'sortOrder' => 10,
            ],
            [
                'name' => 'Site Vitrine',
                'slug' => 'site_vitrine',
                'category' => 'creation',
                'price' => '1597.00',
                'promoPrice' => '1193.00',
                'promoEndDate' => new \DateTime('2026-03-31 23:59:59'),
                'promoLabel' => 'Offre Lancement',
                'isRecurring' => false,
                'priceSuffix' => null,
                'shortDescription' => 'Site professionnel complet',
                'isFeatured' => true,
                'sortOrder' => 20,
            ],
            [
                'name' => 'Site E-commerce',
                'slug' => 'ecommerce',
                'category' => 'creation',
                'price' => '3297.00',
                'promoPrice' => '2497.00',
                'promoEndDate' => new \DateTime('2026-03-31 23:59:59'),
                'promoLabel' => 'Offre Lancement',
                'isRecurring' => false,
                'priceSuffix' => null,
                'shortDescription' => 'Boutique en ligne complète',
                'isFeatured' => false,
                'sortOrder' => 30,
            ],
            [
                'name' => 'Hébergement + Maintenance',
                'slug' => 'hebergement_maintenance',
                'category' => 'hebergement',
                'price' => '77.00',
                'promoPrice' => '53.00',
                'promoEndDate' => new \DateTime('2026-03-31 23:59:59'),
                'promoLabel' => 'Offre Lancement',
                'isRecurring' => true,
                'priceSuffix' => null,
                'shortDescription' => 'Hébergement et maintenance tout inclus',
                'isFeatured' => false,
                'sortOrder' => 40,
            ],
            [
                'name' => 'Hébergement Seul',
                'slug' => 'hebergement',
                'category' => 'hebergement',
                'price' => '23.00',
                'promoPrice' => '13.00',
                'promoEndDate' => new \DateTime('2026-03-31 23:59:59'),
                'promoLabel' => 'Offre Lancement',
                'isRecurring' => true,
                'priceSuffix' => null,
                'shortDescription' => 'Hébergement performant et sécurisé',
                'isFeatured' => false,
                'sortOrder' => 50,
            ],
            [
                'name' => 'Maintenance Seule',
                'slug' => 'maintenance',
                'category' => 'hebergement',
                'price' => '63.00',
                'promoPrice' => '47.00',
                'promoEndDate' => new \DateTime('2026-03-31 23:59:59'),
                'promoLabel' => 'Offre Lancement',
                'isRecurring' => true,
                'priceSuffix' => null,
                'shortDescription' => 'Mises à jour et support technique',
                'isFeatured' => false,
                'sortOrder' => 60,
            ],
            [
                'name' => 'Pack SEO Essentiel',
                'slug' => 'seo_essentiel',
                'category' => 'seo',
                'price' => '397.00',
                'promoPrice' => '247.00',
                'promoEndDate' => new \DateTime('2026-03-31 23:59:59'),
                'promoLabel' => 'Offre Lancement',
                'isRecurring' => false,
                'priceSuffix' => null,
                'shortDescription' => 'Audit et optimisation SEO ponctuel',
                'isFeatured' => false,
                'sortOrder' => 70,
            ],
            [
                'name' => 'Pack SEO Visibilité',
                'slug' => 'seo_visibilite',
                'category' => 'seo',
                'price' => '197.00',
                'promoPrice' => '143.00',
                'promoEndDate' => new \DateTime('2026-03-31 23:59:59'),
                'promoLabel' => 'Offre Lancement',
                'isRecurring' => true,
                'priceSuffix' => null,
                'shortDescription' => 'Suivi SEO mensuel',
                'isFeatured' => false,
                'sortOrder' => 80,
            ],
            [
                'name' => 'Pack SEO Performance',
                'slug' => 'seo_performance',
                'category' => 'seo',
                'price' => '397.00',
                'promoPrice' => '227.00',
                'promoEndDate' => new \DateTime('2026-03-31 23:59:59'),
                'promoLabel' => 'Offre Lancement',
                'isRecurring' => true,
                'priceSuffix' => null,
                'shortDescription' => 'SEO complet avec suivi mensuel',
                'isFeatured' => true,
                'sortOrder' => 90,
            ],
            [
                'name' => 'Optimisation SEO',
                'slug' => 'optimisation_seo',
                'category' => 'ponctuel',
                'price' => '347.00',
                'promoPrice' => null,
                'promoEndDate' => null,
                'promoLabel' => null,
                'isRecurring' => false,
                'priceSuffix' => null,
                'shortDescription' => 'Optimisation SEO ponctuelle',
                'isFeatured' => false,
                'sortOrder' => 100,
            ],
            [
                'name' => 'Optimisation de contenu',
                'slug' => 'optimisation_contenu',
                'category' => 'ponctuel',
                'price' => '33.00',
                'promoPrice' => null,
                'promoEndDate' => null,
                'promoLabel' => null,
                'isRecurring' => false,
                'priceSuffix' => '/page',
                'shortDescription' => 'Rédaction et optimisation de contenu',
                'isFeatured' => false,
                'sortOrder' => 110,
            ],
            [
                'name' => 'Site multilingue',
                'slug' => 'site_multilingue',
                'category' => 'ponctuel',
                'price' => '93.00',
                'promoPrice' => null,
                'promoEndDate' => null,
                'promoLabel' => null,
                'isRecurring' => false,
                'priceSuffix' => '/langue/page',
                'shortDescription' => 'Traduction et adaptation du site',
                'isFeatured' => false,
                'sortOrder' => 120,
            ],
            [
                'name' => 'Refonte de site',
                'slug' => 'refonte',
                'category' => 'ponctuel',
                'price' => '593.00',
                'promoPrice' => null,
                'promoEndDate' => null,
                'promoLabel' => null,
                'isRecurring' => false,
                'priceSuffix' => null,
                'shortDescription' => 'Modernisation de site existant',
                'isFeatured' => false,
                'sortOrder' => 130,
            ],
        ];

        foreach ($offers as $data) {
            $offer = new Offer();
            $offer->setName($data['name']);
            $offer->setSlug($data['slug']);
            $offer->setCategory($data['category']);
            $offer->setPrice($data['price']);
            $offer->setPromoPrice($data['promoPrice']);
            $offer->setPromoEndDate($data['promoEndDate']);
            $offer->setPromoLabel($data['promoLabel']);
            $offer->setIsRecurring($data['isRecurring']);
            $offer->setPriceSuffix($data['priceSuffix']);
            $offer->setShortDescription($data['shortDescription']);
            $offer->setIsFeatured($data['isFeatured']);
            $offer->setSortOrder($data['sortOrder']);

            $manager->persist($offer);
        }

        $manager->flush();
    }
}
