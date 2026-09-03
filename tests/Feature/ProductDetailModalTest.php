<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Supplier;
use App\Models\SupplierLedgerEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The "Details" row action on the product list opens this view. It rendered
 * fine locally against SQLite with zero ledger rows, then 500'd on every
 * click on production's MySQL: the supplier-ledger query ordered by
 * transaction_date, a column customer_ledger_entries has and
 * supplier_ledger_entries does not (see SupplierLedgerEntry's migration).
 * SQLite does not validate an ORDER BY column against an empty result;
 * MySQL does regardless of row count - which is why this needs a real
 * SupplierLedgerEntry row to catch, not just an empty table.
 */
class ProductDetailModalTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_renders_for_a_product_with_no_purchase_or_sale_history(): void
    {
        $org = Organization::factory()->create();
        $product = Product::factory()->create(['organization_id' => $org->id]);

        // The modal is a content fragment (no page chrome, no product name in
        // it) - rendering without throwing is the assertion that matters here.
        $html = view('filament.pages.product-detail-modal', ['product' => $product])->render();

        $this->assertStringContainsString('activeTab', $html);
    }

    public function test_it_renders_with_a_real_supplier_ledger_entry_present(): void
    {
        $org = Organization::factory()->create();
        $category = Category::create([
            'organization_id' => $org->id,
            'name' => 'Spices',
            'slug' => 'spices',
            'active' => true,
        ]);
        $product = Product::factory()->create(['organization_id' => $org->id, 'category_id' => $category->id]);
        $variant = ProductVariant::factory()->create(['product_id' => $product->id]);

        $supplier = Supplier::factory()->create(['organization_id' => $org->id]);
        $purchase = Purchase::factory()->create(['organization_id' => $org->id, 'supplier_id' => $supplier->id]);
        PurchaseItem::factory()->create(['purchase_id' => $purchase->id, 'product_variant_id' => $variant->id]);

        SupplierLedgerEntry::create([
            'organization_id' => $org->id,
            'supplier_id' => $supplier->id,
            'purchase_id' => $purchase->id,
            'type' => 'purchase',
            'amount' => 2500,
            'balance_after' => 2500,
            'notes' => 'Test purchase entry',
            'created_at' => now(),
        ]);

        $html = view('filament.pages.product-detail-modal', ['product' => $product])->render();

        $this->assertStringContainsString($supplier->name, $html);
        // amount is positive (a purchase increases what we owe), which maps
        // to the debit column - the whole point of the amount > 0 / < 0 split.
        $this->assertStringContainsString('2,500.00', $html);
    }

    public function test_it_renders_after_a_supplier_payment_reduces_the_balance(): void
    {
        $org = Organization::factory()->create();
        $category = Category::create([
            'organization_id' => $org->id,
            'name' => 'Spices',
            'slug' => 'spices',
            'active' => true,
        ]);
        $product = Product::factory()->create(['organization_id' => $org->id, 'category_id' => $category->id]);
        $variant = ProductVariant::factory()->create(['product_id' => $product->id]);

        $supplier = Supplier::factory()->create(['organization_id' => $org->id]);
        $purchase = Purchase::factory()->create(['organization_id' => $org->id, 'supplier_id' => $supplier->id]);
        PurchaseItem::factory()->create(['purchase_id' => $purchase->id, 'product_variant_id' => $variant->id]);

        // A payment is stored as a negative amount - see
        // SupplierLedgerEntry::createPaymentEntry(). Rendering this proves the
        // credit branch (amount < 0) is reachable and does not itself error.
        SupplierLedgerEntry::create([
            'organization_id' => $org->id,
            'supplier_id' => $supplier->id,
            'purchase_id' => $purchase->id,
            'type' => 'payment',
            'amount' => -800,
            'balance_after' => 1700,
            'notes' => 'Test payment entry',
            'created_at' => now(),
        ]);

        $html = view('filament.pages.product-detail-modal', ['product' => $product])->render();

        $this->assertStringContainsString('800.00', $html);
    }
}
