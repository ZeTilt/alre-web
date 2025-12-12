<?php

namespace App\Twig;

use App\Service\ImageResizerService;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class ResponsiveImageExtension extends AbstractExtension
{
    public function __construct(
        private ImageResizerService $imageResizer,
        private string $projectDir
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('responsive_srcset', [$this, 'getResponsiveSrcset']),
            new TwigFunction('responsive_image', [$this, 'getResponsiveImage'], ['is_safe' => ['html']]),
        ];
    }

    // Mapping des anciens suffixes (1x, 2x) vers les largeurs
    private const LEGACY_WIDTH_MAP = [
        '1x' => 260,
        '2x' => 520,
    ];

    /**
     * Génère l'attribut srcset pour une image responsive
     * Utilise des descripteurs de largeur (w) pour permettre au navigateur
     * de choisir la bonne image selon la taille d'affichage et la densité
     * @param string $relativePath Chemin relatif depuis uploads/ (ex: profile/photo.jpg)
     * @return string srcset attribute value ou empty string
     */
    public function getResponsiveSrcset(string $relativePath): string
    {
        $versions = $this->imageResizer->getResponsiveVersions($relativePath);

        if (empty($versions)) {
            return '';
        }

        $srcset = [];
        foreach ($versions as $suffix => $path) {
            // Les nouveaux suffixes sont déjà au format "200w", "260w", etc.
            if (str_ends_with($suffix, 'w')) {
                $srcset[] = '/' . $path . ' ' . $suffix;
            } else {
                // Anciens suffixes 1x, 2x -> convertir en largeurs
                $width = self::LEGACY_WIDTH_MAP[$suffix] ?? null;
                if ($width) {
                    $srcset[] = '/' . $path . ' ' . $width . 'w';
                }
            }
        }

        return implode(', ', $srcset);
    }

    /**
     * Génère une balise img complète avec srcset
     */
    public function getResponsiveImage(
        string $relativePath,
        string $alt = '',
        string $class = '',
        string $loading = 'lazy'
    ): string {
        $versions = $this->imageResizer->getResponsiveVersions($relativePath);

        // Utiliser la version 1x comme src par défaut, sinon l'original
        $src = '/' . ($versions['1x'] ?? $relativePath);
        $srcset = $this->getResponsiveSrcset($relativePath);

        $attrs = [
            'src' => htmlspecialchars($src),
            'alt' => htmlspecialchars($alt),
        ];

        if ($srcset) {
            $attrs['srcset'] = htmlspecialchars($srcset);
        }

        if ($class) {
            $attrs['class'] = htmlspecialchars($class);
        }

        if ($loading) {
            $attrs['loading'] = htmlspecialchars($loading);
        }

        $attrString = implode(' ', array_map(
            fn($k, $v) => $k . '="' . $v . '"',
            array_keys($attrs),
            array_values($attrs)
        ));

        return '<img ' . $attrString . '>';
    }
}
