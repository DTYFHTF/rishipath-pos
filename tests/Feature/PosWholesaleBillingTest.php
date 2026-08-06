<?php

namespace Tests\Feature;

use App\Filament\Pages\EnhancedPOS;
use App\Models\Category;
use App\Models\Organization;
use App\Models\PosSession;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\RetailStore;
use App\Models\Role;
use App\Models\Sale;
use App\Models\StockLevel;
use App\Models\Store;
use App\Models\Terminal;
use App\Models\User;
use App\Services\PricingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PosWholesaleBillingTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $org;

    protected Store $store;

    protected User $admin;

    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::factory()->create(['country_code' => 'NP']);
        $this->store = Store::factory()->create(['organization_id' => $this->org->id]);

        // sales.terminal_id is NOT NULL — every till needs a terminal on record.
        Terminal::create([
            'organization_id' => $this->org->id,
            'store_id' => $this->store->id,
            'code' => 'T1',
            'name' => 'Counter 1',
            'active' => true,
        ]);

        $this->admin = $this->makeUser('Super Admin', [
            'access_pos_billing', 'create_sales', 'use_wholesale_billing',
        ]);

        $this->product = $this->makeProduct('Black Cardamom', [
            // pack label => [cost, retail]
            '100' => [100.0, 250.0],
            '500' => [450.0, 900.0],
            '1000' => [880.0, 1700.0],
        ]);

        $this->actingAs($this->admin);
    }

    private function makeUser(string $roleName, array $permissions): User
    {
        $role = Role::create([
            'organization_id' => $this->org->id,
            'name' => $roleName,
            'slug' => str($roleName)->slug()->value(),
            'permissions' => $permissions,
            'is_system_role' => true,
        ]);

        return User::create([
            'organization_id' => $this->org->id,
            'role_id' => $role->id,
            'name' => $roleName,
            'email' => str($roleName)->slug()->value().'@test.local',
            'password' => bcrypt('secret'),
            'active' => true,
        ]);
    }

    /** @param  array<string, array{0: float, 1: float}>  $packs */
    private function makeProduct(string $name, array $packs, int $stock = 20): Product
    {
        $category = Category::firstOrCreate(
            ['organization_id' => $this->org->id, 'name' => 'Spices'],
            ['active' => true]
        );

        $product = Product::create([
            'organization_id' => $this->org->id,
            'category_id' => $category->id,
            'sku' => 'P-'.str($name)->slug()->upper()->value(),
            'name' => $name,
            'name_nepali' => 'अलैंची',
            'product_type' => 'simple',
            'unit_type' => 'weight',
            'active' => true,
        ]);

        foreach ($packs as $packSize => [$cost, $retail]) {
            $variant = ProductVariant::create([
                'product_id' => $product->id,
                'sku' => $product->sku.'-'.$packSize,
                'barcode' => '890'.random_int(1000000, 9999999),
                'pack_size' => (float) $packSize,
                'unit' => 'g',
                'cost_price' => $cost,
                'base_price' => $retail,
                'mrp_india' => $retail,
                'selling_price_nepal' => $retail,
                'active' => true,
            ]);

            StockLevel::create([
                'product_variant_id' => $variant->id,
                'store_id' => $this->store->id,
                'quantity' => $stock,
                'reserved_quantity' => 0,
            ]);

        }

        return $product->fresh('variants');
    }

    /**
     * Drop buffer stock to zero so a sale takes the procurement-on-demand
     * path. Batches can only be produced by receiving a purchase order, and
     * selling from a stock level with no matching batch is not a real state.
     */
    private function clearBufferStock(): void
    {
        StockLevel::query()->update(['quantity' => 0]);
    }

    private function pos()
    {
        return Livewire::test(EnhancedPOS::class);
    }

    // ── Wholesale pricing ─────────────────────────────────────────────────

    public function test_wholesale_price_is_cost_plus_thirteen_percent_rounded_up_to_the_nearest_five(): void
    {
        $variant = $this->product->variants->firstWhere('pack_size', 100.0);

        // 100 * 1.13 = 113 -> next Rs5
        $this->assertSame(115.0, PricingService::getWholesalePrice($variant));

        // 450 * 1.13 = 508.5 -> 510
        $this->assertSame(510.0, PricingService::getWholesalePrice(
            $this->product->variants->firstWhere('pack_size', 500.0)
        ));
    }

    public function test_a_one_gram_pack_keeps_whole_rupee_wholesale_rounding(): void
    {
        $product = $this->makeProduct('Saffron', ['1' => [270.0, 375.0]]);
        $variant = $product->variants->first();

        // 270 * 1.13 = 305.1 -> 306 (not stepped to a multiple of 5).
        $this->assertSame(306.0, PricingService::getWholesalePrice($variant));
    }

    public function test_wholesale_price_is_null_without_a_cost_so_we_never_sell_at_zero(): void
    {
        $variant = $this->product->variants->first();
        $variant->update(['cost_price' => 0]);

        $this->assertNull(PricingService::getWholesalePrice($variant->fresh()));

        // getPosPrice must fall back to retail rather than returning 0.
        $this->assertSame(250.0, PricingService::getPosPrice(
            $variant->fresh(), $this->store->id, $this->org, wholesale: true
        ));
    }

    public function test_toggling_wholesale_reprices_the_whole_cart(): void
    {
        $small = $this->product->variants->firstWhere('pack_size', 100.0);
        $large = $this->product->variants->firstWhere('pack_size', 500.0);

        $component = $this->pos()
            ->call('addToCart', $small->id)
            ->call('addToCart', $large->id);

        $cart = collect($component->get('sessions'))->first()['cart'];
        $this->assertSame([250.0, 900.0], array_map(fn ($i) => (float) $i['price'], $cart), 'starts at retail');

        $component->call('toggleWholesale');
        $cart = collect($component->get('sessions'))->first()['cart'];
        $this->assertSame([115.0, 510.0], array_map(fn ($i) => (float) $i['price'], $cart), 'dealer rates applied');

        // …and back again.
        $component->call('toggleWholesale');
        $cart = collect($component->get('sessions'))->first()['cart'];
        $this->assertSame([250.0, 900.0], array_map(fn ($i) => (float) $i['price'], $cart));
    }

    public function test_items_added_while_in_wholesale_mode_use_dealer_rates(): void
    {
        $variant = $this->product->variants->firstWhere('pack_size', 100.0);

        $component = $this->pos()
            ->call('toggleWholesale')
            ->call('addToCart', $variant->id);

        $cart = collect($component->get('sessions'))->first()['cart'];
        $this->assertSame(115.0, (float) $cart[0]['price']);
    }

    public function test_a_role_without_the_permission_cannot_switch_to_wholesale(): void
    {
        $cashier = $this->makeUser('Cashier', ['access_pos_billing', 'create_sales']);
        $this->actingAs($cashier);

        $variant = $this->product->variants->firstWhere('pack_size', 100.0);

        $component = $this->pos()
            ->call('addToCart', $variant->id)
            ->call('toggleWholesale');

        $cart = collect($component->get('sessions'))->first()['cart'];
        $this->assertSame(250.0, (float) $cart[0]['price'], 'price must stay at retail');
        $component->assertNotified();
    }

    public function test_the_toggle_is_hidden_from_roles_without_the_permission(): void
    {
        $this->pos()->assertSee('Retail bill (MRP)');

        $this->actingAs($this->makeUser('Cashier', ['access_pos_billing', 'create_sales']));
        $this->pos()->assertDontSee('Retail bill (MRP)');
    }

    public function test_selecting_a_retail_store_customer_switches_to_wholesale(): void
    {
        $retailStore = RetailStore::create([
            'organization_id' => $this->org->id,
            'store_name' => 'Bajrangawoli Kold Store',
            'contact_number' => '9841784088',
            'status' => 'active',
        ]);
        $customer = $retailStore->syncLinkedCustomer();

        $variant = $this->product->variants->firstWhere('pack_size', 100.0);

        $component = $this->pos()
            ->call('addToCart', $variant->id)
            ->call('selectCustomer', $customer->id);

        $session = collect($component->get('sessions'))->first();
        $this->assertTrue($session['is_wholesale']);
        $this->assertSame(115.0, (float) $session['cart'][0]['price']);

        // Switching to a walk-in returns to retail rates.
        $component->call('selectCustomer', null);
        $session = collect($component->get('sessions'))->first();
        $this->assertFalse($session['is_wholesale']);
        $this->assertSame(250.0, (float) $session['cart'][0]['price']);
    }

    public function test_a_wholesale_sale_records_the_channel_and_cost_basis(): void
    {
        $variant = $this->product->variants->firstWhere('pack_size', 500.0); // cost 450, dealer 509
        $this->clearBufferStock();

        $this->pos()
            ->call('toggleWholesale')
            ->call('addToCart', $variant->id)
            ->call('updateQuantity', 0, 4)
            ->call('completeSale');

        $sale = Sale::latest('id')->first();

        $this->assertSame('wholesale', $sale->order_channel);
        $this->assertSame(4 * 510.0, (float) $sale->subtotal, 'billed at dealer rates, not MRP');

        // Cost basis drives agent commission: 4 × 450.
        $this->assertSame(1800.0, (float) $sale->wholesale_base_amount);
        $this->assertSame(
            round((float) $sale->total_amount - 1800.0, 2),
            (float) $sale->company_profit_amount
        );
    }

    public function test_a_retail_sale_still_records_the_retail_channel(): void
    {
        $variant = $this->product->variants->firstWhere('pack_size', 100.0);
        $this->clearBufferStock();

        $this->pos()->call('addToCart', $variant->id)->call('completeSale');

        $sale = Sale::latest('id')->first();
        $this->assertSame('retail', $sale->order_channel);
        $this->assertSame(250.0, (float) $sale->subtotal, 'billed at MRP');
        $this->assertSame(100.0, (float) $sale->wholesale_base_amount);
    }

    public function test_wholesale_mode_survives_parking_and_reopening_a_cart(): void
    {
        $variant = $this->product->variants->firstWhere('pack_size', 100.0);

        $component = $this->pos()
            ->call('toggleWholesale')
            ->call('addToCart', $variant->id);

        $key = $component->get('activeSessionKey');
        $this->assertTrue((bool) PosSession::where('session_key', $key)->first()->is_wholesale);

        // A fresh page load must rehydrate the flag, not silently revert to MRP.
        $reloaded = $this->pos();
        $this->assertTrue(collect($reloaded->get('sessions'))->first()['is_wholesale']);
    }

    // ── Two-step search ───────────────────────────────────────────────────

    public function test_search_returns_one_row_per_product_not_per_pack_size(): void
    {
        $this->makeProduct('Green Cardamom', ['100' => [200.0, 400.0], '500' => [900.0, 1800.0]]);

        $results = $this->pos()->set('quickSearchInput', 'cardamom')->instance()->searchResults;

        $this->assertCount(2, $results, 'two products, not five variants');
        $this->assertEqualsCanonicalizing(
            ['Black Cardamom', 'Green Cardamom'],
            $results->pluck('name')->all()
        );
        $this->assertSame(3, $results->firstWhere('name', 'Black Cardamom')['pack_count']);
    }

    public function test_search_matches_nepali_names_and_barcodes(): void
    {
        $this->assertCount(1, $this->pos()->set('quickSearchInput', 'अलैंची')->instance()->searchResults);

        $barcode = $this->product->variants->first()->barcode;
        $this->assertCount(1, $this->pos()->set('quickSearchInput', $barcode)->instance()->searchResults);
    }

    public function test_selecting_a_product_offers_its_pack_sizes(): void
    {
        $component = $this->pos()
            ->set('quickSearchInput', 'cardamom')
            ->call('selectProduct', $this->product->id);

        $picked = $component->instance()->selectedProduct;

        $this->assertSame('Black Cardamom', $picked['name']);
        $this->assertSame(['100 G', '500 G', '1000 G'], array_column($picked['variants'], 'pack_label'));
        $this->assertSame([250.0, 900.0, 1700.0], array_map(fn ($v) => (float) $v['price'], $picked['variants']));
        $this->assertSame(20, $picked['variants'][0]['available_stock']);

        // Nothing is in the cart until a pack size is chosen.
        $this->assertEmpty(collect($component->get('sessions'))->first()['cart']);
    }

    public function test_pack_sizes_are_ordered_by_real_weight_not_raw_number(): void
    {
        $product = Product::create([
            'organization_id' => $this->org->id,
            'category_id' => Category::first()->id,
            'sku' => 'P-CUMIN',
            'name' => 'Cumin Seeds',
            'product_type' => 'simple',
            'unit_type' => 'weight',
            'active' => true,
        ]);

        // A 1 KG pack stores pack_size = 1, so ordering by the raw column
        // would list it before the 20 G pack.
        foreach ([[20, 'g'], [500, 'g'], [1, 'kg'], [100, 'g']] as [$size, $unit]) {
            ProductVariant::create([
                'product_id' => $product->id,
                'sku' => "CUMIN-{$size}{$unit}",
                'pack_size' => $size,
                'unit' => $unit,
                'cost_price' => 50,
                'base_price' => 100,
                'selling_price_nepal' => 100,
                'active' => true,
            ]);
        }

        $picked = $this->pos()->call('selectProduct', $product->id)->instance()->selectedProduct;

        $this->assertSame(
            ['20 G', '100 G', '500 G', '1 KG'],
            array_column($picked['variants'], 'pack_label')
        );
    }

    public function test_pack_sizes_show_dealer_rates_in_wholesale_mode(): void
    {
        $picked = $this->pos()
            ->call('toggleWholesale')
            ->call('selectProduct', $this->product->id)
            ->instance()->selectedProduct;

        $this->assertSame([115.0, 510.0, 995.0], array_map(fn ($v) => (float) $v['price'], $picked['variants']));
        // The struck-through retail price stays available for comparison.
        $this->assertSame([250.0, 900.0, 1700.0], array_map(fn ($v) => (float) $v['retail_price'], $picked['variants']));
    }

    public function test_a_single_pack_product_skips_the_second_step(): void
    {
        $single = $this->makeProduct('Saffron', ['5' => [900.0, 1500.0]]);

        $component = $this->pos()->call('selectProduct', $single->id);

        $this->assertNull($component->get('selectedProductId'), 'no pack-size step for a one-pack product');
        $this->assertCount(1, collect($component->get('sessions'))->first()['cart']);
    }

    public function test_scanning_a_barcode_adds_to_cart_in_one_step(): void
    {
        $variant = $this->product->variants->firstWhere('pack_size', 500.0);

        $component = $this->pos()
            ->set('quickSearchInput', $variant->barcode)
            ->call('handleQuickInput');

        $cart = collect($component->get('sessions'))->first()['cart'];
        $this->assertCount(1, $cart, 'a scanner must never be forced through the pack-size step');
        $this->assertSame($variant->id, $cart[0]['variant_id']);
        $this->assertSame('', $component->get('quickSearchInput'));
    }

    public function test_enter_on_a_name_search_opens_the_pack_picker(): void
    {
        $component = $this->pos()
            ->set('quickSearchInput', 'Black Cardamom')
            ->call('handleQuickInput');

        $this->assertSame($this->product->id, $component->get('selectedProductId'));
        $this->assertEmpty(collect($component->get('sessions'))->first()['cart']);
    }

    public function test_changing_the_search_term_drops_back_to_step_one(): void
    {
        $component = $this->pos()
            ->call('selectProduct', $this->product->id)
            ->set('quickSearchInput', 'saffron');

        $this->assertNull($component->get('selectedProductId'));
    }

    // ── Rendering ─────────────────────────────────────────────────────────

    public function test_the_pos_page_renders_search_cart_and_checkout(): void
    {
        $variant = $this->product->variants->firstWhere('pack_size', 100.0);

        $this->pos()
            ->assertOk()
            ->assertSee('Search or scan')
            ->assertSee('Cart is empty')
            // The sticky mobile checkout bar and the desktop button both exist.
            ->assertSee('Complete Sale')
            ->call('addToCart', $variant->id)
            ->assertOk()
            ->assertSee('Black Cardamom')
            ->assertDontSee('Cart is empty');
    }

    public function test_the_pack_picker_renders_every_size(): void
    {
        $this->pos()
            ->set('quickSearchInput', 'cardamom')
            ->call('selectProduct', $this->product->id)
            ->assertOk()
            ->assertSee('Pick a pack size')
            ->assertSee('100 G')
            ->assertSee('500 G')
            ->assertSee('1000 G');
    }
}
