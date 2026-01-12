<?php

namespace App\Service;

use Symfony\Component\Yaml\Yaml;

class LocalPageService
{
    private array $config;
    private string $configPath;

    public function __construct(string $projectDir)
    {
        $this->configPath = $projectDir . '/config/local_pages.yaml';
        $this->loadConfig();
    }

    private function loadConfig(): void
    {
        if (!file_exists($this->configPath)) {
            $this->config = ['cities' => [], 'services' => []];
            return;
        }

        $this->config = Yaml::parseFile($this->configPath);
    }

    /**
     * Retourne toutes les villes configurées.
     */
    public function getCities(): array
    {
        return $this->config['cities'] ?? [];
    }

    /**
     * Retourne une ville par son slug.
     */
    public function getCity(string $slug): ?array
    {
        return $this->config['cities'][$slug] ?? null;
    }

    /**
     * Retourne tous les services configurés.
     */
    public function getServices(): array
    {
        return $this->config['services'] ?? [];
    }

    /**
     * Retourne un service par son slug.
     */
    public function getService(string $slug): ?array
    {
        return $this->config['services'][$slug] ?? null;
    }

    /**
     * Génère toutes les combinaisons service-ville pour le sitemap.
     *
     * @return array<array{service: string, city: string, url: string}>
     */
    public function getAllPages(): array
    {
        $pages = [];

        foreach ($this->config['services'] as $serviceSlug => $service) {
            foreach ($this->config['cities'] as $citySlug => $city) {
                $pages[] = [
                    'service' => $serviceSlug,
                    'serviceTitle' => $service['title'],
                    'city' => $citySlug,
                    'cityName' => $city['name'],
                    'url' => $serviceSlug . '-' . $citySlug,
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
        foreach ($this->config['services'] as $serviceSlug => $service) {
            if (str_starts_with($slug, $serviceSlug . '-')) {
                $citySlug = substr($slug, strlen($serviceSlug) + 1);
                if (isset($this->config['cities'][$citySlug])) {
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
}
