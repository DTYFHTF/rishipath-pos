<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SyncWebProductImagesTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $org;

    protected string $mapPath;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->org = Organization::factory()->create();
        $this->mapPath = storage_path('framework/testing/web_product_images.json');

        @mkdir(dirname($this->mapPath), 0755, true);
    }

    protected function tearDown(): void
    {
        @unlink($this->mapPath);

        parent::tearDown();
    }

    /** @param  array<string, string>  $map */
    private function writeMap(array $map): void
    {
        file_put_contents($this->mapPath, json_encode(['products' => $map]));
    }

    /** @param  list<array<string, mixed>>  $images */
    private function fakeCatalogue(string $slug, array $images): void
    {
        Http::fake([
            '*/api/products*' => Http::response([
                'data' => [['slug' => $slug, 'name' => 'Whatever', 'images' => $images, 'variants' => []]],
                'lastPage' => 1,
            ]),
            'res.cloudinary.com/*' => Http::response('binary-image-bytes', 200, ['Content-Type' => 'image/jpeg']),
            '*' => Http::response('should-not-be-fetched', 200, ['Content-Type' => 'image/jpeg']),
        ]);
    }

    public function test_it_stores_a_published_photo_and_points_the_product_at_it(): void
    {
        $product = Product::factory()->create(['organization_id' => $this->org->id, 'sku' => 'SHD-TEST', 'image_url' => null]);
        $this->writeMap(['SHD-TEST' => 'test-product']);
        $this->fakeCatalogue('test-product', [[
            'url' => 'https://res.cloudinary.com/dhknx0eac/image/upload/f_auto,q_auto/shuddhidham/products/ABC123',
            'cloudinaryId' => 'shuddhidham/products/ABC123',
        ]]);

        $this->artisan('products:sync-web-images', ['--map' => $this->mapPath])->assertSuccessful();

        $product->refresh();
        $this->assertStringStartsWith('product-images/web/', $product->image_url);
        // The POS grid reads image_url, the variant fallback reads image_1.
        $this->assertSame($product->image_url, $product->image_1);
        Storage::disk('public')->assertExists($product->image_url);

        // Originals are ~1MB each; the sync must ask Cloudinary to resize.
        Http::assertSent(fn (Request $r) => ! str_contains($r->url(), 'res.cloudinary.com')
            || str_contains($r->url(), 'w_800'));
    }

    public function test_it_leaves_the_existing_image_when_the_website_serves_a_stock_photo(): void
    {
        $product = Product::factory()->create([
            'organization_id' => $this->org->id,
            'sku' => 'SHD-STOCK',
            'image_url' => '/images/productv2-webp/asafoetida.webp',
        ]);
        $this->writeMap(['SHD-STOCK' => 'stock-product']);
        $this->fakeCatalogue('stock-product', [[
            'url' => 'https://images.unsplash.com/photo-1596040033229-a9821ebd058d?w=800',
            'cloudinaryId' => null,
        ]]);

        $this->artisan('products:sync-web-images', ['--map' => $this->mapPath])->assertSuccessful();

        $this->assertSame('/images/productv2-webp/asafoetida.webp', $product->refresh()->image_url);
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'unsplash'));
    }

    public function test_it_leaves_the_existing_image_when_the_website_serves_a_relative_path(): void
    {
        $product = Product::factory()->create([
            'organization_id' => $this->org->id,
            'sku' => 'SHD-REL',
            'image_url' => '/images/productv2-webp/dry-ginger.webp',
        ]);
        $this->writeMap(['SHD-REL' => 'relative-product']);
        $this->fakeCatalogue('relative-product', [['url' => '/images/products/dry-ginger.jpg', 'cloudinaryId' => null]]);

        $this->artisan('products:sync-web-images', ['--map' => $this->mapPath])->assertSuccessful();

        $this->assertSame('/images/productv2-webp/dry-ginger.webp', $product->refresh()->image_url);
    }

    public function test_a_second_run_does_not_download_anything_again(): void
    {
        Product::factory()->create(['organization_id' => $this->org->id, 'sku' => 'SHD-TWICE', 'image_url' => null]);
        $this->writeMap(['SHD-TWICE' => 'twice-product']);
        $this->fakeCatalogue('twice-product', [[
            'url' => 'https://res.cloudinary.com/dhknx0eac/image/upload/f_auto,q_auto/shuddhidham/products/ABC123',
            'cloudinaryId' => 'shuddhidham/products/ABC123',
        ]]);

        $this->artisan('products:sync-web-images', ['--map' => $this->mapPath])->assertSuccessful();
        // One catalogue request plus one image download.
        Http::assertSentCount(2);

        $this->artisan('products:sync-web-images', ['--map' => $this->mapPath])->assertSuccessful();

        // The second run re-reads the catalogue but must not re-fetch the photo.
        Http::assertSentCount(3);
    }

    public function test_dry_run_writes_nothing(): void
    {
        $product = Product::factory()->create(['organization_id' => $this->org->id, 'sku' => 'SHD-DRY', 'image_url' => null]);
        $this->writeMap(['SHD-DRY' => 'dry-product']);
        $this->fakeCatalogue('dry-product', [[
            'url' => 'https://res.cloudinary.com/dhknx0eac/image/upload/f_auto,q_auto/shuddhidham/products/ABC123',
            'cloudinaryId' => 'shuddhidham/products/ABC123',
        ]]);

        $this->artisan('products:sync-web-images', ['--map' => $this->mapPath, '--dry-run' => true])->assertSuccessful();

        $this->assertNull($product->refresh()->image_url);
        $this->assertEmpty(Storage::disk('public')->allFiles());
    }
}
