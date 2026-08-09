<?php

namespace Tests\Unit;

use App\Services\PdfThumbnailService;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The catalogue's product photos run up to 2048x2048px — sized for the web
 * page's 64px thumbnail. A PDF embeds a file's actual pixel data regardless
 * of the CSS box it sits in, so embedding those source files directly made a
 * 90-product price list PDF come out at ~200MB and take over a minute to
 * render. This service exists to shrink each photo once, to what a PDF
 * thumbnail actually needs, and cache the result.
 */
class PdfThumbnailServiceTest extends TestCase
{
    private string $cacheDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cacheDir = storage_path('app/price-list-thumbs');
        Storage::disk('local')->deleteDirectory('price-list-thumbs');
    }

    protected function tearDown(): void
    {
        Storage::disk('local')->deleteDirectory('price-list-thumbs');
        parent::tearDown();
    }

    private function makeSourceImage(int $width, int $height): string
    {
        $path = tempnam(sys_get_temp_dir(), 'pdfthumb').'.png';
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, imagecolorallocate($image, 200, 50, 50));
        imagepng($image, $path);
        imagedestroy($image);

        return $path;
    }

    public function test_a_large_source_image_is_downscaled(): void
    {
        $source = $this->makeSourceImage(2048, 2048);

        $thumbPath = PdfThumbnailService::resolve($source);

        $this->assertNotNull($thumbPath);
        $this->assertFileExists($thumbPath);

        [$width, $height] = getimagesize($thumbPath);
        $this->assertLessThanOrEqual(160, $width);
        $this->assertLessThanOrEqual(160, $height);

        unlink($source);
    }

    public function test_the_thumbnail_is_dramatically_smaller_than_the_source(): void
    {
        $source = $this->makeSourceImage(2048, 2048);

        $thumbPath = PdfThumbnailService::resolve($source);

        // A flat-color 2048x2048 PNG is already small by pathological-case
        // standards; a real photo compresses far less. Even so the resized
        // JPEG must be a small fraction of the source — this is the
        // regression guard for the 200MB PDF.
        $this->assertLessThan(filesize($source) / 4, filesize($thumbPath));

        unlink($source);
    }

    public function test_a_missing_source_returns_null_instead_of_erroring(): void
    {
        $this->assertNull(PdfThumbnailService::resolve('/no/such/file.webp'));
        $this->assertNull(PdfThumbnailService::resolve(null));
    }

    public function test_a_thumbnail_is_cached_and_reused(): void
    {
        $source = $this->makeSourceImage(500, 500);

        $first = PdfThumbnailService::resolve($source);
        $mtimeBefore = filemtime($first);

        // Calling again for the same source must not regenerate the file.
        sleep(1);
        $second = PdfThumbnailService::resolve($source);

        $this->assertSame($first, $second);
        $this->assertSame($mtimeBefore, filemtime($second));

        unlink($source);
    }

    public function test_a_replaced_source_photo_regenerates_the_cached_thumbnail(): void
    {
        $source = $this->makeSourceImage(500, 500);
        $first = PdfThumbnailService::resolve($source);
        $firstBytes = file_get_contents($first);

        sleep(1);
        // Overwrite with a different, larger image at the same path — this
        // is what happens when someone drops a new product photo in.
        $image = imagecreatetruecolor(1000, 1000);
        imagefill($image, 0, 0, imagecolorallocate($image, 10, 200, 10));
        imagepng($image, $source);
        imagedestroy($image);
        touch($source, time() + 5);

        $second = PdfThumbnailService::resolve($source);

        $this->assertNotSame($firstBytes, file_get_contents($second));

        unlink($source);
    }

    public function test_webp_sources_are_supported(): void
    {
        $image = imagecreatetruecolor(300, 300);
        imagefill($image, 0, 0, imagecolorallocate($image, 50, 50, 200));
        $path = tempnam(sys_get_temp_dir(), 'pdfthumb').'.webp';
        imagewebp($image, $path);
        imagedestroy($image);

        $thumbPath = PdfThumbnailService::resolve($path);

        $this->assertNotNull($thumbPath);
        $this->assertFileExists($thumbPath);

        unlink($path);
    }
}
