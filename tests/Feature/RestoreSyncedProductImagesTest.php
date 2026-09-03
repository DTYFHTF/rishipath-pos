<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Product;
use Database\Seeders\ProductImageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RestoreSyncedProductImagesTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $org;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::factory()->create();
    }

    private function product(array $attributes): Product
    {
        return Product::factory()->create($attributes + ['organization_id' => $this->org->id]);
    }

    public function test_it_points_image_url_back_at_the_synced_photo(): void
    {
        $product = $this->product([
            'sku' => 'SHD-REVERTED',
            'image_url' => '/images/productv2-webp/cumin-seeds.webp',
            'image_1' => 'product-images/web/shd-cuse-1-abc.jpg',
        ]);

        $this->artisan('products:restore-synced-images')->assertSuccessful();

        $this->assertSame('product-images/web/shd-cuse-1-abc.jpg', $product->refresh()->image_url);
    }

    public function test_it_fills_in_a_product_that_had_no_image_at_all(): void
    {
        $product = $this->product([
            'sku' => 'SHD-EMPTY',
            'image_url' => null,
            'image_1' => 'product-images/web/shd-empty-1-abc.jpg',
        ]);

        $this->artisan('products:restore-synced-images')->assertSuccessful();

        $this->assertSame('product-images/web/shd-empty-1-abc.jpg', $product->refresh()->image_url);
    }

    public function test_it_leaves_a_product_that_already_shows_its_synced_photo(): void
    {
        $product = $this->product([
            'sku' => 'SHD-FINE',
            'image_url' => 'product-images/web/shd-fine-1-abc.jpg',
            'image_1' => 'product-images/web/shd-fine-1-abc.jpg',
        ]);
        $before = $product->updated_at;

        $this->artisan('products:restore-synced-images')
            ->expectsOutputToContain('Nothing to restore')
            ->assertSuccessful();

        $this->assertEquals($before, $product->refresh()->updated_at);
    }

    public function test_it_ignores_a_product_with_no_synced_photo_to_restore(): void
    {
        $product = $this->product([
            'sku' => 'SHD-LEGACY',
            'image_url' => '/images/productv2-webp/legacy.webp',
            'image_1' => null,
        ]);

        $this->artisan('products:restore-synced-images')->assertSuccessful();

        $this->assertSame('/images/productv2-webp/legacy.webp', $product->refresh()->image_url);
    }

    public function test_a_dry_run_changes_nothing(): void
    {
        $product = $this->product([
            'sku' => 'SHD-DRY',
            'image_url' => '/images/productv2-webp/dry.webp',
            'image_1' => 'product-images/web/shd-dry-1-abc.jpg',
        ]);

        $this->artisan('products:restore-synced-images', ['--dry-run' => true])
            ->expectsOutputToContain('Dry run')
            ->assertSuccessful();

        $this->assertSame('/images/productv2-webp/dry.webp', $product->refresh()->image_url);
    }

    /**
     * The other half of the fix: without this the seeder would undo the
     * restore above on the very next deploy.
     */
    public function test_the_seeder_will_not_overwrite_a_real_photo(): void
    {
        // A synced or uploaded photo - a storage-disk path, no leading slash.
        $this->assertFalse(ProductImageSeeder::mayReplaceImageUrl('product-images/web/shd-cuse-1-abc.jpg'));
        $this->assertFalse(ProductImageSeeder::mayReplaceImageUrl('product-images/uploaded.png'));

        // Its own legacy files, and products with no photo yet, stay its job.
        $this->assertTrue(ProductImageSeeder::mayReplaceImageUrl('/images/productv2-webp/cumin-seeds.webp'));
        $this->assertTrue(ProductImageSeeder::mayReplaceImageUrl(null));
        $this->assertTrue(ProductImageSeeder::mayReplaceImageUrl(''));
    }
}
