<?php

namespace App\Service;

use App\Entity\City;
use App\Repository\CityRepository;
use Symfony\Component\Yaml\Yaml;

class LocalPageService
{
    private array $servicesConfig;

    public function __construct(
        private CityRepository $cityRepository,
        private string $projectDir,
    ) {
        $this->loadServicesConfig();
    }

    private function loadServicesConfig(): void
    {
        $configPath = $this->projectDir . '/config/local_pages.yaml';

        if (file_exists($configPath)) {
            $config = Yaml::parseFile($configPath);
            $this->servicesConfig = $config['services'] ?? [];
        } else {
            // Configuration par défaut
            $this->servicesConfig = [
                'developpeur-web' => [
                    'title' => 'Développeur Web',
                    'description' => 'Création de sites internet sur mesure',
                    'icon' => 'fa-code',
                ],
                'creation-site-internet' => [
                    'title' => 'Création de Site Internet',
                    'description' => 'Sites vitrines et e-commerce professionnels',
                    'icon' => 'fa-globe',
                ],
                'agence-web' => [
                    'title' => 'Agence Web',
                    'description' => 'Accompagnement complet pour votre présence en ligne',
                    'icon' => 'fa-building',
                ],
            ];
        }
    }

    /**
     * Retourne toutes les villes actives depuis la base de données.
     *
     * @return City[]
     */
    public function getCities(): array
    {
        return $this->cityRepository->findAllActive();
    }

    /**
     * Retourne une ville par son slug.
     */
    public function getCity(string $slug): ?City
    {
        return $this->cityRepository->findBySlug($slug);
    }

    /**
     * Retourne une ville sous forme de tableau (pour compatibilité templates).
     */
    public function getCityAsArray(string $slug): ?array
    {
        $city = $this->getCity($slug);

        if (!$city) {
            return null;
        }

        return [
            'name' => $city->getName(),
            'region' => $city->getRegion(),
            'description' => $city->getDescription(),
            'nearby' => $city->getNearby(),
            'keywords' => $city->getKeywords(),
        ];
    }

    /**
     * Retourne tous les services configurés.
     */
    public function getServices(): array
    {
        return $this->servicesConfig;
    }

    /**
     * Retourne un service par son slug.
     */
    public function getService(string $slug): ?array
    {
        return $this->servicesConfig[$slug] ?? null;
    }

    /**
     * Génère toutes les combinaisons service-ville pour le sitemap.
     *
     * @return array<array{service: string, city: string, url: string, cityEntity: City}>
     */
    public function getAllPages(): array
    {
        $pages = [];
        $cities = $this->getCities();

        foreach ($this->servicesConfig as $serviceSlug => $service) {
            foreach ($cities as $city) {
                $pages[] = [
                    'service' => $serviceSlug,
                    'serviceTitle' => $service['title'],
                    'city' => $city->getSlug(),
                    'cityName' => $city->getName(),
                    'url' => $serviceSlug . '-' . $city->getSlug(),
                    'cityEntity' => $city,
                ];
            }
        }

        return $pages;
    }

    /**
     * Parse un slug combiné (ex: developpeur-web-vannes) en service et ville.
     *
     * @return array{service: string|null, city: string|null}
     */
    public function parseSlug(string $slug): array
    {
        foreach ($this->servicesConfig as $serviceSlug => $service) {
            if (str_starts_with($slug, $serviceSlug . '-')) {
                $citySlug = substr($slug, strlen($serviceSlug) + 1);
                $city = $this->getCity($citySlug);

                if ($city) {
                    return [
                        'service' => $serviceSlug,
                        'city' => $citySlug,
                    ];
                }
            }
        }

        return [
            'service' => null,
            'city' => null,
        ];
    }

    /**
     * Vérifie si un slug est valide.
     */
    public function isValidSlug(string $slug): bool
    {
        $parsed = $this->parseSlug($slug);
        return $parsed['service'] !== null && $parsed['city'] !== null;
    }

    /**
     * Retourne le nombre de pages générées.
     */
    public function getPageCount(): int
    {
        $citiesCount = count($this->getCities());
        $servicesCount = count($this->servicesConfig);

        return $citiesCount * $servicesCount;
    }
}
