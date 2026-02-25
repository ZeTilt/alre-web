<?php

namespace App\EventListener;

use App\Entity\City;
use App\Service\IndexNowService;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;
use Psr\Log\LoggerInterface;

#[AsEntityListener(event: Events::postPersist, entity: City::class)]
#[AsEntityListener(event: Events::postUpdate, entity: City::class)]
class CityIndexNowListener
{
    private const BASE_URL = 'https://www.alre-web.bzh';

    private const SERVICES = [
        'developpeur-web',
        'creation-site-internet',
        'agence-web',
    ];

    public function __construct(
        private IndexNowService $indexNowService,
        private LoggerInterface $logger,
    ) {}

    public function postPersist(City $city): void
    {
        $this->submitCityUrls($city);
    }

    public function postUpdate(City $city): void
    {
        $this->submitCityUrls($city);
    }

    private function submitCityUrls(City $city): void
    {
        if (!$city->isActive()) {
            return;
        }

        if (!$this->indexNowService->isAvailable()) {
            return;
        }

        $slug = $city->getSlug();
        $urls = [];

        foreach (self::SERVICES as $service) {
            $urls[] = self::BASE_URL . '/' . $service . '-' . $slug;
        }

        // La page référencement local n'existe que si le contenu SEO est rempli
        if ($city->getDescriptionReferencement() !== null || $city->getDescriptionReferencementLong() !== null) {
            $urls[] = self::BASE_URL . '/referencement-local-' . $slug;
        }

        $this->logger->info('IndexNow: submitting city pages', [
            'city' => $city->getName(),
            'urls' => $urls,
        ]);

        $this->indexNowService->submitUrls($urls);
    }
}
