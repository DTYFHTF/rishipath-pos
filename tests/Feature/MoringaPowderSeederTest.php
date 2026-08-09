<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\PricingService;
use Database\Seeders\MoringaPowderSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MoringaPowderSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Organization::factory()->create(['slug' => 'rishipath']);
    }

    public function test_moringa_powder_seeds_with_its_fixed_retail_and_wholesale_price(): void
    {
        $this->seed(MoringaPowderSeeder::class);

        $variant = ProductVariant::where('sku', 'PC-MORINGA-100GMS')->firstOrFail();

        $this->assertSame(100.0, (float) $variant->cost_price, '1000/kg for a 100g pack');
        $this->assertSame(250.0, (float) $variant->selling_price_nepal);
        $this->assertTrue((bool) $variant->manual_price_locked);
        $this->assertSame(175.0, PricingService::getWholesalePrice($variant),
            'the fixed dealer price, not the 13%-over-cost formula');
        $this->assertTrue((bool) $variant->product->active);
    }

    public function test_reseeding_does_not_overwrite_a_manual_price_change(): void
    {
        $this->seed(MoringaPowderSeeder::class);

        $variant = ProductVariant::where('sku', 'PC-MORINGA-100GMS')->firstOrFail();
        $variant->update(['selling_price_nepal' => 280.0, 'wholesale_price' => 200.0]);

        $this->seed(MoringaPowderSeeder::class);

        $fresh = $variant->fresh();
        $this->assertSame(280.0, (float) $fresh->selling_price_nepal,
            'a later admin edit must survive a reseed');
        $this->assertSame(200.0, (float) $fresh->wholesale_price);
    }

    public function test_the_product_is_reactivated_if_something_else_deactivated_it(): void
    {
        $this->seed(MoringaPowderSeeder::class);

        Product::where('name', 'Moringa Powder')->update(['active' => false]);

        $this->seed(MoringaPowderSeeder::class);

        $this->assertTrue((bool) Product::where('name', 'Moringa Powder')->first()->active);
    }
}
