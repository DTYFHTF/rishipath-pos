<?php

namespace Tests\Feature;

use App\Filament\Resources\ProductResource\Pages\EditProduct;
use App\Filament\Resources\ProductResource\Pages\ListProducts;
use App\Models\Category;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\User;
use App\Services\OrganizationContext;
use App\Services\PackVariantGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProductVariantStatusTest extends TestCase
{
    use RefreshDatabase;

    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $org = Organization::factory()->create();
        $role = Role::create([
            'organization_id' => $org->id,
            'name' => 'Super Admin',
            'slug' => 'super-admin',
            'permissions' => ['view_products', 'edit_products', 'create_product_variants', 'edit_product_variants'],
            'is_system_role' => true,
        ]);

        $this->actingAs(User::create([
            'organization_id' => $org->id,
            'role_id' => $role->id,
            'name' => 'Catalog Tester',
            'email' => 'variant-status@test.local',
            'password' => bcrypt('secret'),
            'active' => true,
        ]));

        OrganizationContext::setCurrentOrganizationId($org->id);

        $category = Category::create([
            'organization_id' => $org->id,
            'name' => 'Spices',
            'slug' => 'spices',
            'active' => true,
        ]);

        $this->product = Product::factory()->create([
            'organization_id' => $org->id,
            'category_id' => $category->id,
            'sku' => 'SHD-STATUS',
            'name' => 'Status Spice',
            'active' => true,
        ]);

        PackVariantGenerator::generate($this->product, [100, 500, 1000], 320.0);
    }

    public function test_the_list_counts_active_packs_against_the_total(): void
    {
        ProductVariant::where('product_id', $this->product->id)
            ->where('pack_size', 100.000)
            ->update(['active' => false]);

        Livewire::test(ListProducts::class)
            ->assertSee('2/3');
    }

    public function test_a_product_with_every_pack_switched_off_is_still_visible_as_such(): void
    {
        ProductVariant::where('product_id', $this->product->id)->update(['active' => false]);

        Livewire::test(ListProducts::class)
            ->assertSee('0/3');
    }

    public function test_the_status_tab_lists_each_pack_and_its_state(): void
    {
        Livewire::test(EditProduct::class, ['record' => $this->product->getKey()])
            ->assertSee('3 of 3 packs active')
            ->assertSee('100 G')
            ->assertSee('1 KG');
    }

    public function test_the_summary_calls_out_packs_that_are_switched_off(): void
    {
        ProductVariant::where('product_id', $this->product->id)
            ->where('pack_size', 100.000)
            ->update(['active' => false]);

        Livewire::test(EditProduct::class, ['record' => $this->product->getKey()])
            ->assertSee('2 of 3 packs active')
            ->assertSee('hidden from the POS and price list');
    }

    public function test_a_pack_can_be_switched_back_on_from_the_product_page(): void
    {
        $variant = ProductVariant::where('product_id', $this->product->id)
            ->where('pack_size', 100.000)
            ->sole();
        $variant->forceFill(['active' => false])->save();

        $page = Livewire::test(EditProduct::class, ['record' => $this->product->getKey()]);

        // Find the repeater row holding this variant and switch it back on.
        $state = $page->get('data.variants');
        $key = collect($state)->search(fn ($row) => (int) ($row['id'] ?? 0) === $variant->id);
        $this->assertNotFalse($key, 'the variant should appear in the Status tab repeater');

        $state[$key]['active'] = true;

        $page->set('data.variants', $state)
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue($variant->refresh()->active);
    }
}
