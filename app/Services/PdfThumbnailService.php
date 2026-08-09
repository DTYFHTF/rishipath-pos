<?php

namespace App\Services;

/**
 * Small, cached, print-sized copies of product photos for the price list PDF.
 *
 * The catalogue's photos are shot for the web page (up to 2048x2048px) and
 * shown there as a 64px thumbnail — fine on screen, since the browser only
 * decodes what it displays. A PDF embeds the source file's actual pixel data
 * regardless of the CSS box it's placed in, so reusing those same files in a
 * ~90-product PDF meant embedding ~90 full 2048px photos: a 200MB file that
 * took 86 seconds to render. This resizes each photo once, to the size the
 * PDF actually needs, and caches the result — after that, generating the PDF
 * is just re-reading small JPEGs already on disk.
 */
class PdfThumbnailService
{
    /** Longest edge of the cached thumbnail, in pixels. */
    private const MAX_DIMENSION = 160;

    private const JPEG_QUALITY = 72;

    private const CACHE_DIR = 'price-list-thumbs';

    /**
     * Absolute filesystem path to a small JPEG copy of $sourcePath, or null
     * if the source doesn't exist or isn't a format GD can read.
     */
    public static function resolve(?string $sourcePath): ?string
    {
        if (! $sourcePath || ! is_file($sourcePath)) {
            return null;
        }

        $cacheDir = storage_path('app/'.self::CACHE_DIR);

        if (! is_dir($cacheDir)) {
            mkdir($cacheDir, 0775, true);
        }

        $cachePath = $cacheDir.'/'.md5($sourcePath).'.jpg';

        // Regenerate only if the source photo is newer than the cached
        // thumbnail — a replaced product photo picks up automatically.
        if (is_file($cachePath) && filemtime($cachePath) >= filemtime($sourcePath)) {
            return $cachePath;
        }

        $source = self::load($sourcePath);

        if (! $source) {
            return null;
        }

        $thumb = self::resizeOntoWhite($source, self::MAX_DIMENSION);
        imagedestroy($source);

        imagejpeg($thumb, $cachePath, self::JPEG_QUALITY);
        imagedestroy($thumb);

        return $cachePath;
    }

    private static function load(string $path): \GdImage|false
    {
        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'webp' => @imagecreatefromwebp($path),
            'png' => @imagecreatefrompng($path),
            'jpg', 'jpeg' => @imagecreatefromjpeg($path),
            default => false,
        };
    }

    /**
     * Downscale onto a white canvas — JPEG has no alpha channel, and most
     * source photos have a transparent background, so compositing onto white
     * here (rather than leaving it to whatever renders the JPEG later) is
     * what keeps the transparent bag photos from turning black.
     */
    private static function resizeOntoWhite(\GdImage $source, int $maxDimension): \GdImage
    {
        $width = imagesx($source);
        $height = imagesy($source);
        $scale = min(1, $maxDimension / max($width, $height));
        $targetWidth = max(1, (int) round($width * $scale));
        $targetHeight = max(1, (int) round($height * $scale));

        $canvas = imagecreatetruecolor($targetWidth, $targetHeight);
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
        imagealphablending($canvas, true);
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

        return $canvas;
    }
}
