<?php

namespace App\Service;

use Symfony\Component\Filesystem\Filesystem;

class ImageVariantGenerator
{
    private ImageOptimizerService $imageOptimizer;
    private Filesystem $filesystem;
    private string $publicDir;

    public function __construct(
        ImageOptimizerService $imageOptimizer,
        string $publicDir
    ) {
        $this->imageOptimizer = $imageOptimizer;
        $this->filesystem = new Filesystem();
        $this->publicDir = $publicDir;
    }

    /**
     * Génère toutes les variantes du logo
     *
     * @param string $logoPath Chemin vers le logo source
     * @return array Tableau avec tous les chemins générés
     */
    public function generateLogoVariants(string $logoPath): array
    {
        if (!file_exists($logoPath)) {
            throw new \InvalidArgumentException("Logo file not found: $logoPath");
        }

        $variants = [];
        $pathInfo = pathinfo($logoPath);
        $baseDir = $pathInfo['dirname'];
        $baseName = $pathInfo['filename'];
        $extension = $pathInfo['extension'];

        // 1. Logo Navbar (150x50)
        $navbarPath = $this->resize($logoPath, 150, 50, $baseDir . '/logo-navbar.' . $extension);
        $variants['navbar'] = $navbarPath;
        $variants['navbar_webp'] = $this->imageOptimizer->generateWebP($navbarPath);

        // 2. Logo Navbar @2x (300x100)
        $navbar2xPath = $this->resize($logoPath, 300, 100, $baseDir . '/logo-navbar@2x.' . $extension);
        $variants['navbar_2x'] = $navbar2xPath;
        $variants['navbar_2x_webp'] = $this->imageOptimizer->generateWebP($navbar2xPath);

        // 3. Logo Footer (120x40)
        $footerPath = $this->resize($logoPath, 120, 40, $baseDir . '/logo-footer.' . $extension);
        $variants['footer'] = $footerPath;
        $variants['footer_webp'] = $this->imageOptimizer->generateWebP($footerPath);

        // 4. Logo Footer @2x (240x80)
        $footer2xPath = $this->resize($logoPath, 240, 80, $baseDir . '/logo-footer@2x.' . $extension);
        $variants['footer_2x'] = $footer2xPath;
        $variants['footer_2x_webp'] = $this->imageOptimizer->generateWebP($footer2xPath);

        // 5. Logo Open Graph (1200x630)
        $ogPath = $this->resize($logoPath, 1200, 630, $baseDir . '/logo-og.' . $extension);
        $variants['og'] = $ogPath;
        $variants['og_webp'] = $this->imageOptimizer->generateWebP($ogPath);

        // 6. Version WebP du logo original (SANS toucher au fichier source!)
        $logoWebP = $this->imageOptimizer->generateWebP($logoPath);
        $variants['original'] = $logoPath;
        $variants['original_webp'] = $logoWebP;

        return array_filter($variants); // Remove null values
    }

    /**
     * Génère tous les favicons à partir d'une image source
     *
     * @param string $sourcePath Chemin vers l'image source (idéalement le logo carré)
     * @return array Tableau avec tous les chemins générés
     */
    public function generateFavicons(string $sourcePath): array
    {
        if (!file_exists($sourcePath)) {
            throw new \InvalidArgumentException("Source file not found: $sourcePath");
        }

        $variants = [];
        $imagesDir = $this->publicDir . '/images';

        if (!is_dir($imagesDir)) {
            $this->filesystem->mkdir($imagesDir);
        }

        // 1. Favicon 16x16
        $favicon16 = $this->resizeSquare($sourcePath, 16, $imagesDir . '/favicon-16x16.png');
        $variants['favicon_16'] = $favicon16;

        // 2. Favicon 32x32
        $favicon32 = $this->resizeSquare($sourcePath, 32, $imagesDir . '/favicon-32x32.png');
        $variants['favicon_32'] = $favicon32;

        // 3. Favicon 48x48
        $favicon48 = $this->resizeSquare($sourcePath, 48, $imagesDir . '/favicon-48x48.png');
        $variants['favicon_48'] = $favicon48;

        // 4. Apple Touch Icon (180x180)
        $appleTouchIcon = $this->resizeSquare($sourcePath, 180, $imagesDir . '/apple-touch-icon.png');
        $variants['apple_touch_icon'] = $appleTouchIcon;

        // 5. Android Chrome 192x192
        $androidChrome192 = $this->resizeSquare($sourcePath, 192, $imagesDir . '/android-chrome-192x192.png');
        $variants['android_chrome_192'] = $androidChrome192;

        // 6. Android Chrome 512x512
        $androidChrome512 = $this->resizeSquare($sourcePath, 512, $imagesDir . '/android-chrome-512x512.png');
        $variants['android_chrome_512'] = $androidChrome512;

        // 7. Generate favicon.ico (multi-resolution)
        $this->generateMultiResolutionIco($favicon16, $favicon32, $favicon48, $imagesDir . '/favicon.ico');
        $variants['favicon_ico'] = $imagesDir . '/favicon.ico';

        // 8. Try to generate SVG favicon if ImageMagick is available
        $svgFavicon = $this->generateSvgFavicon($sourcePath, $imagesDir . '/favicon.svg');
        if ($svgFavicon) {
            $variants['favicon_svg'] = $svgFavicon;
        }

        return array_filter($variants);
    }

    /**
     * Génère un manifest.json pour PWA
     *
     * @param string $siteName Nom du site
     * @param array $iconPaths Chemins vers les icônes android-chrome
     * @return string Chemin vers manifest.json
     */
    public function generateManifest(string $siteName, array $iconPaths): string
    {
        $manifestPath = $this->publicDir . '/manifest.json';

        $icons = [];
        if (isset($iconPaths['android_chrome_192'])) {
            $icons[] = [
                'src' => '/images/android-chrome-192x192.png',
                'sizes' => '192x192',
                'type' => 'image/png'
            ];
        }
        if (isset($iconPaths['android_chrome_512'])) {
            $icons[] = [
                'src' => '/images/android-chrome-512x512.png',
                'sizes' => '512x512',
                'type' => 'image/png'
            ];
        }

        $manifest = [
            'name' => $siteName,
            'short_name' => $siteName,
            'icons' => $icons,
            'theme_color' => '#3A4556',
            'background_color' => '#ffffff',
            'display' => 'standalone',
            'start_url' => '/',
            'orientation' => 'portrait-primary'
        ];

        file_put_contents($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $manifestPath;
    }

    /**
     * Redimensionne une image (helper avec maintien du ratio)
     * Ne fait JAMAIS d'upscale - si l'image source est plus petite, on garde la taille originale
     */
    private function resize(string $sourcePath, int $maxWidth, int $maxHeight, string $outputPath): string
    {
        $imageInfo = getimagesize($sourcePath);
        if (!$imageInfo) {
            throw new \RuntimeException("Cannot read image: $sourcePath");
        }

        $mimeType = $imageInfo['mime'];
        $sourceImage = null;

        switch ($mimeType) {
            case 'image/jpeg':
                $sourceImage = imagecreatefromjpeg($sourcePath);
                break;
            case 'image/png':
                $sourceImage = imagecreatefrompng($sourcePath);
                break;
            case 'image/webp':
                $sourceImage = imagecreatefromwebp($sourcePath);
                break;
        }

        if (!$sourceImage) {
            throw new \RuntimeException("Cannot create image resource");
        }

        $originalWidth = imagesx($sourceImage);
        $originalHeight = imagesy($sourceImage);

        // Calculate new dimensions maintaining aspect ratio
        // IMPORTANT: Ne JAMAIS upscaler (ratio > 1 = agrandissement interdit)
        $ratio = min($maxWidth / $originalWidth, $maxHeight / $originalHeight, 1.0);
        $newWidth = (int) ($originalWidth * $ratio);
        $newHeight = (int) ($originalHeight * $ratio);

        $resizedImage = imagecreatetruecolor($newWidth, $newHeight);

        // Preserve transparency for PNG
        if ($mimeType === 'image/png') {
            imagealphablending($resizedImage, false);
            imagesavealpha($resizedImage, true);
            $transparent = imagecolorallocatealpha($resizedImage, 255, 255, 255, 127);
            imagefilledrectangle($resizedImage, 0, 0, $newWidth, $newHeight, $transparent);
        }

        imagecopyresampled(
            $resizedImage, $sourceImage,
            0, 0, 0, 0,
            $newWidth, $newHeight,
            $originalWidth, $originalHeight
        );

        // Determine output format from output path
        $outputExtension = strtolower(pathinfo($outputPath, PATHINFO_EXTENSION));

        // Save resized image avec compression optimale
        switch ($outputExtension) {
            case 'jpg':
            case 'jpeg':
                imagejpeg($resizedImage, $outputPath, 85);
                break;
            case 'png':
                // Niveau 9 = compression maximale (0 = aucune, 9 = max)
                imagepng($resizedImage, $outputPath, 9);
                break;
            case 'webp':
                imagewebp($resizedImage, $outputPath, 85);
                break;
        }

        imagedestroy($sourceImage);
        imagedestroy($resizedImage);

        // Pas de double compression - c'est déjà fait à la sauvegarde

        return $outputPath;
    }

    /**
     * Redimensionne une image en carré (crop au centre si nécessaire)
     * Ne fait JAMAIS d'upscale - si la taille demandée est plus grande que la source, on garde la taille source
     */
    private function resizeSquare(string $sourcePath, int $size, string $outputPath): string
    {
        $imageInfo = getimagesize($sourcePath);
        if (!$imageInfo) {
            throw new \RuntimeException("Cannot read image: $sourcePath");
        }

        $mimeType = $imageInfo['mime'];
        $sourceImage = null;

        switch ($mimeType) {
            case 'image/jpeg':
                $sourceImage = imagecreatefromjpeg($sourcePath);
                break;
            case 'image/png':
                $sourceImage = imagecreatefrompng($sourcePath);
                break;
            case 'image/webp':
                $sourceImage = imagecreatefromwebp($sourcePath);
                break;
        }

        if (!$sourceImage) {
            throw new \RuntimeException("Cannot create image resource");
        }

        $originalWidth = imagesx($sourceImage);
        $originalHeight = imagesy($sourceImage);

        // Calculate crop dimensions (center crop to square)
        $cropSize = min($originalWidth, $originalHeight);
        $cropX = (int) (($originalWidth - $cropSize) / 2);
        $cropY = (int) (($originalHeight - $cropSize) / 2);

        // IMPORTANT: Ne JAMAIS upscaler - si la source est plus petite, on garde la taille source
        $finalSize = min($size, $cropSize);

        // Create square image
        $squareImage = imagecreatetruecolor($finalSize, $finalSize);

        // Preserve transparency for PNG
        imagealphablending($squareImage, false);
        imagesavealpha($squareImage, true);
        $transparent = imagecolorallocatealpha($squareImage, 255, 255, 255, 127);
        imagefilledrectangle($squareImage, 0, 0, $finalSize, $finalSize, $transparent);

        // Copy and resize
        imagecopyresampled(
            $squareImage, $sourceImage,
            0, 0, $cropX, $cropY,
            $finalSize, $finalSize, $cropSize, $cropSize
        );

        // Save as PNG avec compression maximale
        imagepng($squareImage, $outputPath, 9);

        imagedestroy($sourceImage);
        imagedestroy($squareImage);

        // Pas de double compression - c'est déjà fait à la sauvegarde

        return $outputPath;
    }

    /**
     * Génère un fichier .ico multi-résolution
     */
    private function generateMultiResolutionIco(
        string $png16Path,
        string $png32Path,
        string $png48Path,
        string $outputPath
    ): void {
        // Use ImageMagick to create multi-resolution ICO
        if ($this->isImageMagickAvailable()) {
            $command = sprintf(
                'convert "%s" "%s" "%s" "%s" 2>&1',
                $png16Path,
                $png32Path,
                $png48Path,
                $outputPath
            );
            exec($command, $output, $returnCode);

            if ($returnCode === 0 && file_exists($outputPath)) {
                return;
            }
        }

        // Fallback: just copy 32x32 as favicon.ico
        copy($png32Path, $outputPath);
    }

    /**
     * Génère un favicon SVG (si possible)
     */
    private function generateSvgFavicon(string $sourcePath, string $outputPath): ?string
    {
        // This would require converting PNG to SVG, which is complex
        // For now, we'll skip this and just use PNG favicons
        // You can manually create an SVG favicon if needed
        return null;
    }

    /**
     * Vérifie si ImageMagick est disponible
     */
    private function isImageMagickAvailable(): bool
    {
        $check = exec("which convert 2>&1", $output, $returnCode);
        return $returnCode === 0 && !empty($check);
    }
}
