<?php

namespace App\Service;

use Psr\Log\LoggerInterface;

class ImageResizerService
{
    private const MAX_WIDTH = 1200;
    private const MAX_HEIGHT = 1200;
    private const QUALITY_JPEG = 85;
    private const QUALITY_PNG = 8; // 0-9, lower is better quality
    private const MAX_FILE_SIZE = 500 * 1024; // 500 KB

    // Tailles responsive pour les portraits (affichés à 260x350 sur desktop)
    private const PORTRAIT_SIZES = [
        '1x' => ['width' => 260, 'height' => 350],
        '2x' => ['width' => 520, 'height' => 700],
    ];

    // Tailles responsive pour les photos larges (affichées à ~600px max)
    private const WIDE_SIZES = [
        '1x' => ['width' => 600, 'height' => 400],
        '2x' => ['width' => 1200, 'height' => 800],
    ];

    public function __construct(
        private LoggerInterface $logger,
        private string $projectDir
    ) {
    }

    /**
     * Redimensionne une image si nécessaire et la compresse
     *
     * @param string $relativePath Chemin relatif depuis public/ (ex: uploads/profile/photo.jpg)
     * @param int|null $maxWidth Largeur max (null = utiliser la constante)
     * @param int|null $maxHeight Hauteur max (null = utiliser la constante)
     * @return bool True si l'image a été redimensionnée
     */
    public function resize(string $relativePath, ?int $maxWidth = null, ?int $maxHeight = null): bool
    {
        $fullPath = $this->projectDir . '/public/' . $relativePath;

        if (!file_exists($fullPath)) {
            $this->logger->warning('Image not found for resizing: ' . $fullPath);
            return false;
        }

        $maxWidth = $maxWidth ?? self::MAX_WIDTH;
        $maxHeight = $maxHeight ?? self::MAX_HEIGHT;

        // Get image info
        $imageInfo = @getimagesize($fullPath);
        if ($imageInfo === false) {
            $this->logger->warning('Could not get image info: ' . $fullPath);
            return false;
        }

        [$width, $height, $type] = $imageInfo;

        // Check if resize is needed
        $fileSize = filesize($fullPath);
        $needsResize = $width > $maxWidth || $height > $maxHeight || $fileSize > self::MAX_FILE_SIZE;

        if (!$needsResize) {
            return false;
        }

        // Calculate new dimensions while maintaining aspect ratio
        $ratio = min($maxWidth / $width, $maxHeight / $height);
        if ($ratio >= 1 && $fileSize <= self::MAX_FILE_SIZE) {
            return false; // No resize needed
        }

        $newWidth = $ratio < 1 ? (int)($width * $ratio) : $width;
        $newHeight = $ratio < 1 ? (int)($height * $ratio) : $height;

        // Create image resource based on type
        $source = match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($fullPath),
            IMAGETYPE_PNG => @imagecreatefrompng($fullPath),
            IMAGETYPE_GIF => @imagecreatefromgif($fullPath),
            IMAGETYPE_WEBP => @imagecreatefromwebp($fullPath),
            default => null,
        };

        if ($source === null || $source === false) {
            $this->logger->warning('Could not create image resource: ' . $fullPath);
            return false;
        }

        // Create new image
        $destination = imagecreatetruecolor($newWidth, $newHeight);

        // Preserve transparency for PNG and GIF
        if ($type === IMAGETYPE_PNG || $type === IMAGETYPE_GIF) {
            imagealphablending($destination, false);
            imagesavealpha($destination, true);
            $transparent = imagecolorallocatealpha($destination, 0, 0, 0, 127);
            imagefilledrectangle($destination, 0, 0, $newWidth, $newHeight, $transparent);
        }

        // Resize
        imagecopyresampled(
            $destination,
            $source,
            0, 0, 0, 0,
            $newWidth, $newHeight,
            $width, $height
        );

        // Save
        $success = match ($type) {
            IMAGETYPE_JPEG => imagejpeg($destination, $fullPath, self::QUALITY_JPEG),
            IMAGETYPE_PNG => imagepng($destination, $fullPath, self::QUALITY_PNG),
            IMAGETYPE_GIF => imagegif($destination, $fullPath),
            IMAGETYPE_WEBP => imagewebp($destination, $fullPath, self::QUALITY_JPEG),
            default => false,
        };

        // Free memory
        imagedestroy($source);
        imagedestroy($destination);

        if ($success) {
            $newFileSize = filesize($fullPath);
            $this->logger->info(sprintf(
                'Image resized: %s (%dx%d -> %dx%d, %s -> %s)',
                $relativePath,
                $width, $height,
                $newWidth, $newHeight,
                $this->formatBytes($fileSize),
                $this->formatBytes($newFileSize)
            ));
        }

        return $success;
    }

    /**
     * Redimensionne une image pour un format portrait et crée les versions responsive
     * @return array Chemins relatifs des versions créées ['1x' => '...', '2x' => '...']
     */
    public function resizePortrait(string $relativePath): array
    {
        return $this->createResponsiveVersions($relativePath, self::PORTRAIT_SIZES);
    }

    /**
     * Redimensionne une image pour un format paysage/large et crée les versions responsive
     * @return array Chemins relatifs des versions créées ['1x' => '...', '2x' => '...']
     */
    public function resizeWide(string $relativePath): array
    {
        return $this->createResponsiveVersions($relativePath, self::WIDE_SIZES);
    }

    /**
     * Crée plusieurs versions d'une image pour les différentes résolutions
     * @return array Chemins relatifs des versions créées
     */
    private function createResponsiveVersions(string $relativePath, array $sizes): array
    {
        $fullPath = $this->projectDir . '/public/' . $relativePath;

        if (!file_exists($fullPath)) {
            $this->logger->warning('Image not found for responsive versions: ' . $fullPath);
            return [];
        }

        $pathInfo = pathinfo($fullPath);
        $baseName = $pathInfo['filename'];
        $extension = strtolower($pathInfo['extension'] ?? 'jpg');
        $directory = $pathInfo['dirname'];
        $relativeDir = pathinfo($relativePath, PATHINFO_DIRNAME);

        $versions = [];

        foreach ($sizes as $suffix => $dimensions) {
            $newFilename = sprintf('%s-%s.%s', $baseName, $suffix, $extension);
            $newFullPath = $directory . '/' . $newFilename;
            $newRelativePath = $relativeDir . '/' . $newFilename;

            if ($this->createResizedVersion(
                $fullPath,
                $newFullPath,
                $dimensions['width'],
                $dimensions['height']
            )) {
                $versions[$suffix] = $newRelativePath;
            }
        }

        // Aussi redimensionner l'original pour ne pas garder un fichier trop lourd
        $this->resize($relativePath, 1200, 1200);

        return $versions;
    }

    /**
     * Crée une version redimensionnée d'une image (crop centré pour remplir les dimensions)
     */
    private function createResizedVersion(
        string $sourcePath,
        string $destPath,
        int $targetWidth,
        int $targetHeight
    ): bool {
        $imageInfo = @getimagesize($sourcePath);
        if ($imageInfo === false) {
            return false;
        }

        [$width, $height, $type] = $imageInfo;

        // Create source image
        $source = match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($sourcePath),
            IMAGETYPE_PNG => @imagecreatefrompng($sourcePath),
            IMAGETYPE_GIF => @imagecreatefromgif($sourcePath),
            IMAGETYPE_WEBP => @imagecreatefromwebp($sourcePath),
            default => null,
        };

        if ($source === null || $source === false) {
            return false;
        }

        // Calculer le crop centré (remplir les dimensions cibles)
        $sourceRatio = $width / $height;
        $targetRatio = $targetWidth / $targetHeight;

        if ($sourceRatio > $targetRatio) {
            // Image plus large que la cible : couper les côtés
            $cropHeight = $height;
            $cropWidth = (int)($height * $targetRatio);
            $cropX = (int)(($width - $cropWidth) / 2);
            $cropY = 0;
        } else {
            // Image plus haute que la cible : couper le haut/bas
            $cropWidth = $width;
            $cropHeight = (int)($width / $targetRatio);
            $cropX = 0;
            $cropY = (int)(($height - $cropHeight) / 2);
        }

        // Create destination image
        $destination = imagecreatetruecolor($targetWidth, $targetHeight);

        // Preserve transparency
        if ($type === IMAGETYPE_PNG || $type === IMAGETYPE_GIF) {
            imagealphablending($destination, false);
            imagesavealpha($destination, true);
            $transparent = imagecolorallocatealpha($destination, 0, 0, 0, 127);
            imagefilledrectangle($destination, 0, 0, $targetWidth, $targetHeight, $transparent);
        }

        // Crop and resize
        imagecopyresampled(
            $destination,
            $source,
            0, 0,
            $cropX, $cropY,
            $targetWidth, $targetHeight,
            $cropWidth, $cropHeight
        );

        // Save
        $success = match ($type) {
            IMAGETYPE_JPEG => imagejpeg($destination, $destPath, self::QUALITY_JPEG),
            IMAGETYPE_PNG => imagepng($destination, $destPath, self::QUALITY_PNG),
            IMAGETYPE_GIF => imagegif($destination, $destPath),
            IMAGETYPE_WEBP => imagewebp($destination, $destPath, self::QUALITY_JPEG),
            default => false,
        };

        imagedestroy($source);
        imagedestroy($destination);

        if ($success) {
            $this->logger->info(sprintf(
                'Responsive version created: %s (%dx%d)',
                basename($destPath),
                $targetWidth,
                $targetHeight
            ));
        }

        return $success;
    }

    /**
     * Obtient les chemins des versions responsive d'une image
     * @return array ['1x' => '...', '2x' => '...'] ou tableau vide si pas de versions
     */
    public function getResponsiveVersions(string $relativePath): array
    {
        $fullPath = $this->projectDir . '/public/' . $relativePath;
        $pathInfo = pathinfo($fullPath);
        $baseName = $pathInfo['filename'];
        $extension = strtolower($pathInfo['extension'] ?? 'jpg');
        $directory = $pathInfo['dirname'];
        $relativeDir = pathinfo($relativePath, PATHINFO_DIRNAME);

        $versions = [];
        foreach (['1x', '2x'] as $suffix) {
            $versionPath = $directory . '/' . $baseName . '-' . $suffix . '.' . $extension;
            if (file_exists($versionPath)) {
                $versions[$suffix] = $relativeDir . '/' . $baseName . '-' . $suffix . '.' . $extension;
            }
        }

        return $versions;
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        } elseif ($bytes < 1024 * 1024) {
            return round($bytes / 1024, 1) . ' KB';
        } else {
            return round($bytes / (1024 * 1024), 1) . ' MB';
        }
    }
}
