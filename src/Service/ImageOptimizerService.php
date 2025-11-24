<?php

namespace App\Service;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

class ImageOptimizerService
{
    private string $uploadDir;
    private int $quality;
    private bool $generateWebP;
    private bool $generateAVIF;
    private Filesystem $filesystem;

    public function __construct(
        string $uploadDir,
        int $quality = 85,
        bool $generateWebP = true,
        bool $generateAVIF = false
    ) {
        $this->uploadDir = $uploadDir;
        $this->quality = $quality;
        $this->generateWebP = $generateWebP;
        $this->generateAVIF = $generateAVIF;
        $this->filesystem = new Filesystem();
    }

    /**
     * Optimise une image et génère toutes les variantes nécessaires
     *
     * @param string $filePath Chemin absolu vers le fichier source
     * @param array $options Options d'optimisation [sizes, formats, quality]
     * @return array Tableau avec les chemins de tous les fichiers générés
     */
    public function optimize(string $filePath, array $options = []): array
    {
        if (!file_exists($filePath)) {
            throw new \InvalidArgumentException("File not found: $filePath");
        }

        $quality = $options['quality'] ?? $this->quality;
        $generateFormats = $options['formats'] ?? ['webp'];
        $sizes = $options['sizes'] ?? [];

        $results = [
            'original' => $filePath,
            'variants' => []
        ];

        // Compression de l'image originale
        $this->compress($filePath, $quality);

        // Génération des formats alternatifs
        if (in_array('webp', $generateFormats) && $this->generateWebP) {
            $webpPath = $this->generateWebP($filePath, $quality);
            if ($webpPath) {
                $results['webp'] = $webpPath;
            }
        }

        if (in_array('avif', $generateFormats) && $this->generateAVIF) {
            $avifPath = $this->generateAVIF($filePath, $quality);
            if ($avifPath) {
                $results['avif'] = $avifPath;
            }
        }

        // Génération des tailles multiples (si demandées)
        foreach ($sizes as $sizeName) {
            $sizeVariants = $this->generateSizeVariants($filePath, $sizeName, $quality);
            $results['variants'][$sizeName] = $sizeVariants;
        }

        return $results;
    }

    /**
     * Compresse une image en place
     */
    public function compress(string $filePath, int $quality = null): void
    {
        $quality = $quality ?? $this->quality;
        $imageInfo = getimagesize($filePath);

        if (!$imageInfo) {
            throw new \RuntimeException("Cannot read image: $filePath");
        }

        $mimeType = $imageInfo['mime'];
        $image = null;

        switch ($mimeType) {
            case 'image/jpeg':
                $image = imagecreatefromjpeg($filePath);
                if ($image) {
                    imagejpeg($image, $filePath, $quality);
                }
                break;
            case 'image/png':
                $image = imagecreatefrompng($filePath);
                if ($image) {
                    // PNG compression level: 0 (no compression) to 9 (max compression)
                    $pngCompression = (int) (9 - ($quality / 100 * 9));
                    imagepng($image, $filePath, $pngCompression);
                }
                break;
            case 'image/webp':
                $image = imagecreatefromwebp($filePath);
                if ($image) {
                    imagewebp($image, $filePath, $quality);
                }
                break;
        }

        if ($image) {
            imagedestroy($image);
        }

        // Strip EXIF metadata using ImageMagick if available
        if ($this->isImageMagickAvailable()) {
            $process = new Process(['convert', $filePath, '-strip', $filePath]);
            $process->run();
            // Ignore errors, this is just metadata stripping
        }
    }

    /**
     * Génère une version WebP d'une image
     *
     * @return string|null Chemin du fichier WebP généré ou null si échec
     */
    public function generateWebP(string $sourcePath, int $quality = null): ?string
    {
        if (!function_exists('imagewebp')) {
            return null;
        }

        $quality = $quality ?? $this->quality;
        $imageInfo = getimagesize($sourcePath);

        if (!$imageInfo) {
            return null;
        }

        $mimeType = $imageInfo['mime'];
        $image = null;

        switch ($mimeType) {
            case 'image/jpeg':
                $image = imagecreatefromjpeg($sourcePath);
                break;
            case 'image/png':
                $image = imagecreatefrompng($sourcePath);
                break;
            case 'image/webp':
                // Already WebP, just compress
                $image = imagecreatefromwebp($sourcePath);
                break;
        }

        if (!$image) {
            return null;
        }

        $webpPath = preg_replace('/\.(jpe?g|png|webp)$/i', '.webp', $sourcePath);

        // If same filename, add -webp suffix
        if ($webpPath === $sourcePath) {
            $webpPath = preg_replace('/\.(\w+)$/', '-webp.$1', $sourcePath);
        }

        $success = imagewebp($image, $webpPath, $quality);
        imagedestroy($image);

        return $success ? $webpPath : null;
    }

    /**
     * Génère une version AVIF d'une image (si outils disponibles)
     *
     * @return string|null Chemin du fichier AVIF généré ou null si échec
     */
    public function generateAVIF(string $sourcePath, int $quality = null): ?string
    {
        // Check if avifenc is available
        if (!$this->isCommandAvailable('avifenc')) {
            return null;
        }

        $quality = $quality ?? $this->quality;
        $avifPath = preg_replace('/\.(jpe?g|png|webp)$/i', '.avif', $sourcePath);

        // Convert using avifenc
        $process = new Process(['avifenc', '-q', (string) $quality, $sourcePath, $avifPath]);
        $process->run();

        return ($process->isSuccessful() && file_exists($avifPath)) ? $avifPath : null;
    }

    /**
     * Redimensionne une image
     *
     * @return string Chemin du fichier redimensionné
     */
    public function resize(string $sourcePath, int $width, int $height, string $suffix = ''): string
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
        $ratio = min($width / $originalWidth, $height / $originalHeight, 1.0);
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

        // Generate output path
        $pathInfo = pathinfo($sourcePath);
        $outputPath = $pathInfo['dirname'] . '/' . $pathInfo['filename'] .
                      ($suffix ? '-' . $suffix : '') . '.' . $pathInfo['extension'];

        // Save resized image
        switch ($mimeType) {
            case 'image/jpeg':
                imagejpeg($resizedImage, $outputPath, $this->quality);
                break;
            case 'image/png':
                imagepng($resizedImage, $outputPath, 9);
                break;
            case 'image/webp':
                imagewebp($resizedImage, $outputPath, $this->quality);
                break;
        }

        imagedestroy($sourceImage);
        imagedestroy($resizedImage);

        return $outputPath;
    }

    /**
     * Génère les variantes de taille pour une image
     *
     * @param string $sizeName 'thumbnail', 'medium', 'large'
     * @return array Chemins des fichiers générés
     */
    private function generateSizeVariants(string $filePath, string $sizeName, int $quality): array
    {
        $sizes = [
            'thumbnail' => [400, 300],
            'medium' => [800, 600],
            'large' => [1600, 1200],
        ];

        if (!isset($sizes[$sizeName])) {
            throw new \InvalidArgumentException("Unknown size: $sizeName");
        }

        [$width, $height] = $sizes[$sizeName];

        $variants = [];

        // Generate resized PNG/JPG
        $resizedPath = $this->resize($filePath, $width, $height, $sizeName);
        $variants['default'] = $resizedPath;

        // Generate WebP version if enabled
        if ($this->generateWebP) {
            $webpPath = $this->generateWebP($resizedPath, $quality);
            if ($webpPath) {
                $variants['webp'] = $webpPath;
            }
        }

        return $variants;
    }

    /**
     * Vérifie si une commande est disponible
     */
    private function isCommandAvailable(string $command): bool
    {
        $process = new Process(['which', $command]);
        $process->run();
        return $process->isSuccessful();
    }

    /**
     * Vérifie si ImageMagick est disponible
     */
    private function isImageMagickAvailable(): bool
    {
        return $this->isCommandAvailable('convert');
    }
}
