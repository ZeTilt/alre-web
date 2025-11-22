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
     * Redimensionne une image pour un format portrait
     */
    public function resizePortrait(string $relativePath): bool
    {
        return $this->resize($relativePath, 600, 800);
    }

    /**
     * Redimensionne une image pour un format paysage/large
     */
    public function resizeWide(string $relativePath): bool
    {
        return $this->resize($relativePath, 1200, 800);
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
