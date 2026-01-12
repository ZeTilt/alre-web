<?php

namespace App\Twig;

use App\Entity\Company;
use App\Repository\CompanyRepository;
use App\Repository\GoogleReviewRepository;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;
use Twig\TwigFunction;

class SchemaOrgExtension extends AbstractExtension implements GlobalsInterface
{
    public function __construct(
        private GoogleReviewRepository $googleReviewRepository,
        private CompanyRepository $companyRepository,
    ) {}

    public function getGlobals(): array
    {
        $stats = $this->googleReviewRepository->getStats();

        return [
            'schema_aggregate_rating' => $stats['approved'] > 0 ? [
                'ratingValue' => number_format($stats['averageRating'], 1, '.', ''),
                'reviewCount' => $stats['approved'],
            ] : null,
        ];
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('schema_local_business', [$this, 'generateLocalBusiness'], ['is_safe' => ['html']]),
            new TwigFunction('schema_service', [$this, 'generateService'], ['is_safe' => ['html']]),
            new TwigFunction('schema_webpage', [$this, 'generateWebPage'], ['is_safe' => ['html']]),
        ];
    }

    /**
     * Génère le JSON-LD LocalBusiness avec AggregateRating si disponible.
     */
    public function generateLocalBusiness(?Company $company, ?string $logoUrl = null): string
    {
        if (!$company) {
            return '';
        }

        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'ProfessionalService',
            'name' => $company->getName(),
            'description' => 'Création de sites web professionnels pour artisans, commerçants et PME en Bretagne',
            'url' => $company->getWebsite(),
            'telephone' => $company->getPhone(),
            'email' => $company->getEmail(),
            'address' => [
                '@type' => 'PostalAddress',
                'streetAddress' => $company->getAddress(),
                'postalCode' => $company->getPostalCode(),
                'addressLocality' => $company->getCity(),
                'addressCountry' => 'FR',
            ],
            'geo' => [
                '@type' => 'GeoCoordinates',
                'latitude' => 47.6683,  // Auray
                'longitude' => -2.9833,
            ],
            'areaServed' => [
                '@type' => 'GeoCircle',
                'geoMidpoint' => [
                    '@type' => 'GeoCoordinates',
                    'latitude' => 47.6683,
                    'longitude' => -2.9833,
                ],
                'geoRadius' => '50000', // 50km autour d'Auray
            ],
            'priceRange' => '€€',
            'openingHoursSpecification' => [
                '@type' => 'OpeningHoursSpecification',
                'dayOfWeek' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'],
                'opens' => '09:00',
                'closes' => '18:00',
            ],
            'sameAs' => [
                'https://www.linkedin.com/in/fabrice-dhuicque/',
            ],
        ];

        // Ajouter le logo si disponible
        if ($logoUrl) {
            $schema['logo'] = $logoUrl;
            $schema['image'] = $logoUrl;
        }

        // Ajouter l'AggregateRating si des avis existent
        $stats = $this->googleReviewRepository->getStats();
        if ($stats['approved'] > 0 && $stats['averageRating'] > 0) {
            $schema['aggregateRating'] = [
                '@type' => 'AggregateRating',
                'ratingValue' => number_format($stats['averageRating'], 1, '.', ''),
                'bestRating' => '5',
                'worstRating' => '1',
                'reviewCount' => $stats['approved'],
            ];
        }

        return $this->renderJsonLd($schema);
    }

    /**
     * Génère le JSON-LD Service pour une offre spécifique.
     */
    public function generateService(string $name, string $description, ?string $price = null, ?string $url = null): string
    {
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Service',
            'name' => $name,
            'description' => $description,
            'provider' => [
                '@type' => 'ProfessionalService',
                'name' => 'Alré Web',
            ],
            'areaServed' => [
                '@type' => 'Place',
                'name' => 'Bretagne, France',
            ],
        ];

        if ($price) {
            $schema['offers'] = [
                '@type' => 'Offer',
                'price' => $price,
                'priceCurrency' => 'EUR',
            ];
        }

        if ($url) {
            $schema['url'] = $url;
        }

        return $this->renderJsonLd($schema);
    }

    /**
     * Génère le JSON-LD WebPage.
     */
    public function generateWebPage(string $name, string $description, string $url): string
    {
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'WebPage',
            'name' => $name,
            'description' => $description,
            'url' => $url,
            'inLanguage' => 'fr-FR',
        ];

        return $this->renderJsonLd($schema);
    }

    /**
     * Rend le JSON-LD dans une balise script.
     */
    private function renderJsonLd(array $schema): string
    {
        $json = json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        return sprintf('<script type="application/ld+json">%s</script>', $json);
    }
}
