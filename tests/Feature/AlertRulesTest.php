<?php

namespace Tests\Feature;

use App\Models\AlertRule;
use App\Models\Category;
use App\Models\Notification;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\StockLevel;
use App\Models\Store;
use App\Models\Terminal;
use App\Models\User;
use App\Services\AlertService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Alerts never fired in production for two reasons: no rules existed, and
 * four of the five checkers referenced columns or models that do not exist.
 */
class AlertRulesTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $org;

    protected Store $store;

    protected User $cashier;

    protected int $terminalId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::factory()->create(['country_code' => 'NP']);
        $this->store = Store::factory()->create(['organization_id' => $this->org->id]);

        $this->terminalId = Terminal::create([
            'organization_id' => $this->org->id,
            'store_id' => $this->store->id,
            'code' => 'T1',
            'name' => 'Counter 1',
            'active' => true,
        ])->id;

        $this->cashier = User::create([
            'organization_id' => $this->org->id,
            'name' => 'Bina',
            'email' => 'bina@test.local',
            'password' => bcrypt('secret'),
            'active' => true,
        ]);
    }

    private function rule(string $type, array $conditions, array $overrides = []): AlertRule
    {
        return AlertRule::create(array_merge([
            'name' => ucfirst(str_replace('_', ' ', $type)),
            'type' => $type,
            'conditions' => $conditions,
            'recipients' => ['info@shuddhidham.com'],
            'frequency' => 'daily',
            'active' => true,
            'trigger_count' => 0,
        ], $overrides));
    }

    private function variantWithStock(int $quantity, string $name = 'Cumin Seeds'): ProductVariant
    {
        $category = Category::firstOrCreate(
            ['organization_id' => $this->org->id, 'name' => 'Spices'],
            ['active' => true]
        );

        $product = Product::create([
            'organization_id' => $this->org->id,
            'category_id' => $category->id,
            'sku' => 'P-'.uniqid(),
            'name' => $name,
            'product_type' => 'simple',
            'unit_type' => 'weight',
            'active' => true,
        ]);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => $product->sku.'-1000',
            'pack_size' => 1000,
            'unit' => 'g',
            'cost_price' => 400,
            'base_price' => 520,
            'selling_price_nepal' => 520,
            'active' => true,
        ]);

        StockLevel::create([
            'product_variant_id' => $variant->id,
            'store_id' => $this->store->id,
            'quantity' => $quantity,
            'reserved_quantity' => 0,
        ]);

        return $variant;
    }

    private function sale(float $total): Sale
    {
        return Sale::create([
            'organization_id' => $this->org->id,
            'store_id' => $this->store->id,
            'terminal_id' => $this->terminalId,
            'cashier_id' => $this->cashier->id,
            'receipt_number' => 'R-'.uniqid(),
            'invoice_number' => 'INV-'.uniqid(),
            'date' => now(),
            'time' => now()->toTimeString(),
            'subtotal' => $total,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => $total,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
            'amount_paid' => $total,
            'status' => 'completed',
        ]);
    }

    // ── Low stock ─────────────────────────────────────────────────────────

    public function test_low_stock_reads_stock_levels_not_the_variant_table(): void
    {
        $this->variantWithStock(3, 'Almost Out');
        $this->variantWithStock(500, 'Plenty Left');

        $fired = app(AlertService::class)->checkAlert($this->rule('low_stock', ['threshold' => 10]));

        $this->assertTrue($fired, 'a variant at 3 units must trigger a threshold of 10');

        $notification = Notification::where('type', 'low_stock')->firstOrFail();
        $items = collect($notification->data['items']);

        $this->assertCount(1, $items, 'only the low item is reported');
        $this->assertSame('Almost Out', $items[0]['product']);
        $this->assertSame(3, $items[0]['stock']);
        $this->assertSame($this->store->name, $items[0]['store']);
    }

    public function test_low_stock_stays_quiet_when_everything_is_stocked(): void
    {
        $this->variantWithStock(500);

        $fired = app(AlertService::class)->checkAlert($this->rule('low_stock', ['threshold' => 10]));

        $this->assertFalse($fired);
        $this->assertSame(0, Notification::where('type', 'low_stock')->count());
    }

    public function test_low_stock_ignores_deactivated_variants(): void
    {
        $variant = $this->variantWithStock(1, 'Discontinued');
        $variant->update(['active' => false]);

        $fired = app(AlertService::class)->checkAlert($this->rule('low_stock', ['threshold' => 10]));

        $this->assertFalse($fired, 'a product we no longer sell is not a stock problem');
    }

    // ── High value sale ───────────────────────────────────────────────────

    public function test_a_large_sale_alerts_and_names_the_cashier(): void
    {
        $this->sale(50000);

        $fired = app(AlertService::class)->checkAlert($this->rule('high_value_sale', ['threshold' => 25000]));

        $this->assertTrue($fired);

        $notification = Notification::where('type', 'high_value_sale')->firstOrFail();

        // Sale has a cashier relation, not a user one — reading ->user->name
        // threw the moment a large sale actually happened.
        $this->assertSame('Bina', $notification->data['cashier']);
        $this->assertStringContainsString('Bina', $notification->message);
        $this->assertSame(50000.0, (float) $notification->data['amount']);
    }

    public function test_a_sale_below_the_threshold_does_not_alert(): void
    {
        $this->sale(5000);

        $fired = app(AlertService::class)->checkAlert($this->rule('high_value_sale', ['threshold' => 25000]));

        $this->assertFalse($fired);
    }

    // ── Sales target ──────────────────────────────────────────────────────

    public function test_hitting_the_daily_target_alerts(): void
    {
        $this->sale(30000);
        $this->sale(25000);

        $fired = app(AlertService::class)->checkAlert(
            $this->rule('sales_target', ['target' => 50000, 'period' => 'daily'])
        );

        $this->assertTrue($fired);
        $this->assertSame(1, Notification::where('type', 'sales_target')->count());
    }

    public function test_missing_the_target_stays_quiet(): void
    {
        $this->sale(10000);

        $fired = app(AlertService::class)->checkAlert(
            $this->rule('sales_target', ['target' => 50000, 'period' => 'daily'])
        );

        $this->assertFalse($fired);
    }

    // ── Plumbing ──────────────────────────────────────────────────────────

    public function test_an_unknown_alert_type_is_ignored_rather_than_fatal(): void
    {
        // cashier_variance and inventory_discrepancy were removed because the
        // models they queried do not exist in this codebase.
        $fired = app(AlertService::class)->checkAlert($this->rule('cashier_variance', ['threshold' => 100]));

        $this->assertFalse($fired);
    }

    public function test_triggering_records_when_it_last_fired(): void
    {
        $this->variantWithStock(1);
        $rule = $this->rule('low_stock', ['threshold' => 10]);

        $this->assertNull($rule->last_triggered_at);

        app(AlertService::class)->checkAlert($rule);

        $this->assertNotNull($rule->fresh()->last_triggered_at);
    }

    public function test_an_inactive_rule_is_skipped_by_the_sweep(): void
    {
        $this->variantWithStock(1);
        $this->rule('low_stock', ['threshold' => 10], ['active' => false]);

        $fired = app(AlertService::class)->checkAllAlerts();

        $this->assertSame(0, $fired);
    }

    public function test_the_sweep_runs_every_active_rule(): void
    {
        $this->variantWithStock(1);
        $this->sale(50000);

        $this->rule('low_stock', ['threshold' => 10]);
        $this->rule('high_value_sale', ['threshold' => 25000], ['name' => 'Large sale', 'frequency' => 'hourly']);

        $this->assertSame(2, app(AlertService::class)->checkAllAlerts());
    }
}
