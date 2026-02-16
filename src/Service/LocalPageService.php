<?php

namespace App\Service;

use App\Entity\City;
use App\Entity\DepartmentPage;
use App\Repository\CityRepository;
use App\Repository\DepartmentPageRepository;
use Symfony\Component\Yaml\Yaml;

class LocalPageService
{
    private array $servicesConfig;

    public function __construct(
        private CityRepository $cityRepository,
        private DepartmentPageRepository $departmentPageRepository,
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
     * Retourne un département par son slug.
     */
    public function getDepartment(string $slug): ?DepartmentPage
    {
        return $this->departmentPageRepository->findBySlug($slug);
    }

    /**
     * Retourne tous les départements actifs.
     *
     * @return DepartmentPage[]
     */
    public function getDepartmentPages(): array
    {
        return $this->departmentPageRepository->findAllActive();
    }

    /**
     * Retourne les départements actifs indexés par nom.
     *
     * @return array<string, DepartmentPage>
     */
    public function getDepartmentPagesIndexedByName(): array
    {
        return $this->departmentPageRepository->findAllActiveIndexedByName();
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
     * Génère toutes les combinaisons service-ville et service-département pour le sitemap.
     *
     * @return array<array{service: string, city: string|null, url: string, type: string, cityEntity: ?City, departmentEntity: ?DepartmentPage}>
     */
    public function getAllPages(): array
    {
        $pages = [];
        $cities = $this->getCities();
        $departments = $this->getDepartmentPages();

        foreach ($this->servicesConfig as $serviceSlug => $service) {
            // Department pages
            foreach ($departments as $dept) {
                $pages[] = [
                    'type' => 'department',
                    'service' => $serviceSlug,
                    'serviceTitle' => $service['title'],
                    'city' => null,
                    'cityName' => null,
                    'url' => $serviceSlug . '-' . $dept->getSlug(),
                    'cityEntity' => null,
                    'departmentEntity' => $dept,
                ];
            }

            // City pages
            foreach ($cities as $city) {
                $pages[] = [
                    'type' => 'city',
                    'service' => $serviceSlug,
                    'serviceTitle' => $service['title'],
                    'city' => $city->getSlug(),
                    'cityName' => $city->getName(),
                    'url' => $serviceSlug . '-' . $city->getSlug(),
                    'cityEntity' => $city,
                    'departmentEntity' => null,
                ];
            }
        }

        return $pages;
    }

    /**
     * Parse un slug combiné (ex: developpeur-web-vannes) en service et ville/département.
     *
     * @return array{service: string|null, city: string|null, department: string|null}
     */
    public function parseSlug(string $slug): array
    {
        foreach ($this->servicesConfig as $serviceSlug => $service) {
            if (str_starts_with($slug, $serviceSlug . '-')) {
                $remainder = substr($slug, strlen($serviceSlug) + 1);

                // Try city first
                $city = $this->getCity($remainder);
                if ($city) {
                    return [
                        'service' => $serviceSlug,
                        'city' => $remainder,
                        'department' => null,
                    ];
                }

                // Try department
                $dept = $this->getDepartment($remainder);
                if ($dept) {
                    return [
                        'service' => $serviceSlug,
                        'city' => null,
                        'department' => $remainder,
                    ];
                }
            }
        }

        return [
            'service' => null,
            'city' => null,
            'department' => null,
        ];
    }

    /**
     * Retourne les villes actives groupées par département.
     * Morbihan en premier (base), puis alphabétique.
     *
     * @return array<string, City[]>
     */
    public function getCitiesByRegion(): array
    {
        $cities = $this->getCities();
        $grouped = [];

        foreach ($cities as $city) {
            // Normaliser : "Morbihan, Bretagne" → "Morbihan"
            $region = trim(explode(',', $city->getRegion())[0]);
            $grouped[$region][] = $city;
        }

        $order = ['Morbihan', 'Finistère', 'Côtes-d\'Armor', 'Ille-et-Vilaine'];
        $sorted = [];
        foreach ($order as $region) {
            if (isset($grouped[$region])) {
                $sorted[$region] = $grouped[$region];
            }
        }
        foreach ($grouped as $region => $regionCities) {
            if (!isset($sorted[$region])) {
                $sorted[$region] = $regionCities;
            }
        }

        return $sorted;
    }

    /**
     * Vérifie si un slug est valide.
     */
    public function isValidSlug(string $slug): bool
    {
        $parsed = $this->parseSlug($slug);
        return $parsed['service'] !== null && ($parsed['city'] !== null || $parsed['department'] !== null);
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
