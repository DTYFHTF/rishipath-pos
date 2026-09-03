<?php

namespace Tests\Feature;

use App\Filament\Pages\PriceListPage;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublicPriceListTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $org;

    protected function setUp(): void
    {
        parent::setUp();

        // The price list cache is a real file on the default disk, and this
        // test writes to it. Without a fake, running the suite locally
        // overwrites the developer's own generated price list with fixtures.
        Storage::fake();

        $this->org = Organization::factory()->create([
            'name' => 'Shuddhidham',
            'active' => true,
        ]);

        $this->writeCache();
    }

    private function writeCache(?array $items = null): void
    {
        Storage::put(PriceListPage::CACHE_FILE, json_encode([
            'version' => PriceListPage::CACHE_VERSION,
            'generated_at' => now()->toDateTimeString(),
            'price_list' => [[
                'category' => 'Spices',
                'items' => $items ?? [[
                    'row_key' => '1:1',
                    'product_id' => 1,
                    'variant_id' => 1,
                    'product_name' => 'Cumin Seeds (जिरा / Jeera)',
                    'product_name_english' => 'Cumin Seeds',
                    'image_slug' => 'cumin-seeds',
                    'image_url' => 'product-images/web/cumin.jpg',
                    'image_src' => 'http://localhost/storage/product-images/web/cumin.jpg',
                    'pack_size' => '100 G',
                    'pack_size_grams' => 100.0,
                    'pack_code' => '100g',
                    'pack_color_class' => 'bg-blue-100',
                    'wholesale' => 137.0,
                    'mrp' => 185.0,
                    'cost_price' => 91.5,
                    'price_changed' => true,
                    'one_gram_mrp' => 2,
                    'missing_mandatory_packs' => ['1kg'],
                    'is_weight_product' => true,
                ]],
            ]],
        ]));
    }

    public function test_a_valid_token_opens_the_price_list(): void
    {
        $token = $this->org->ensurePriceListToken();

        $this->get("/prices/{$token}")
            ->assertOk()
            ->assertSee('Shuddhidham')
            ->assertSee('Cumin Seeds', false);
    }

    public function test_cost_and_wholesale_never_reach_the_page(): void
    {
        $token = $this->org->ensurePriceListToken();

        $body = $this->get("/prices/{$token}")->assertOk()->getContent();

        // The figures themselves, not just the labels: the payload is embedded
        // as JSON, so a leaked key would be readable in the page source. The
        // keys are matched in their JSON form ("wholesale":) rather than as
        // bare words, which would also match the prose explaining their absence.
        $this->assertStringNotContainsString('91.5', $body);
        $this->assertStringNotContainsString('137', $body);
        $this->assertStringNotContainsString('"cost_price"', $body);
        $this->assertStringNotContainsString('"wholesale"', $body);
        // The retail price is the one number that should be there.
        $this->assertStringContainsString('185', $body);
    }

    public function test_internal_flags_are_not_published(): void
    {
        $token = $this->org->ensurePriceListToken();

        $body = $this->get("/prices/{$token}")->assertOk()->getContent();

        $this->assertStringNotContainsString('"price_changed"', $body);
        $this->assertStringNotContainsString('"missing_mandatory_packs"', $body);
    }

    public function test_an_unknown_token_is_a_404(): void
    {
        $this->org->ensurePriceListToken();

        $this->get('/prices/'.str_repeat('a', 40))->assertNotFound();
    }

    public function test_an_inactive_organization_does_not_serve_its_link(): void
    {
        $token = $this->org->ensurePriceListToken();
        $this->org->forceFill(['active' => false])->save();

        $this->get("/prices/{$token}")->assertNotFound();
    }

    public function test_rotating_the_token_kills_the_old_link(): void
    {
        $old = $this->org->ensurePriceListToken();
        $new = $this->org->rotatePriceListToken();

        $this->assertNotSame($old, $new);
        $this->get("/prices/{$old}")->assertNotFound();
        $this->get("/prices/{$new}")->assertOk();
    }

    public function test_a_cache_from_an_older_schema_is_not_served(): void
    {
        $token = $this->org->ensurePriceListToken();

        Storage::put(PriceListPage::CACHE_FILE, json_encode([
            'version' => PriceListPage::CACHE_VERSION - 1,
            'generated_at' => now()->toDateTimeString(),
            'price_list' => [['category' => 'Spices', 'items' => [['product_name' => 'Stale Product']]]],
        ]));

        $this->get("/prices/{$token}")
            ->assertOk()
            ->assertDontSee('Stale Product')
            ->assertSee('has not been published yet');
    }

    public function test_it_survives_having_no_cache_at_all(): void
    {
        $token = $this->org->ensurePriceListToken();
        Storage::delete(PriceListPage::CACHE_FILE);

        $this->get("/prices/{$token}")
            ->assertOk()
            ->assertSee('has not been published yet');
    }

    public function test_the_token_is_only_created_when_asked_for(): void
    {
        $this->assertNull($this->org->fresh()->price_list_public_token);

        $token = $this->org->ensurePriceListToken();

        $this->assertSame(40, strlen($token));
        $this->assertSame($token, $this->org->fresh()->price_list_public_token);
        // Asking again keeps the same link rather than quietly breaking it.
        $this->assertSame($token, $this->org->ensurePriceListToken());
    }
}
