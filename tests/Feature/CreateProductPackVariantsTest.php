<?php

namespace Tests\Feature;

use App\Filament\Resources\ProductResource\Pages\CreateProduct;
use App\Models\Category;
use App\Models\Organization;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Services\OrganizationContext;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CreateProductPackVariantsTest extends TestCase
{
    use RefreshDatabase;

    protected Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $org = Organization::factory()->create();
        $this->category = Category::create([
            'organization_id' => $org->id,
            'name' => 'Spices',
            'slug' => 'spices',
            'active' => true,
        ]);
        $role = Role::create([
            'organization_id' => $org->id,
            'name' => 'Super Admin',
            'slug' => 'super-admin',
            'permissions' => ['view_products', 'create_products', 'edit_products', 'create_product_variants'],
            'is_system_role' => true,
        ]);

        $this->actingAs(User::create([
            'organization_id' => $org->id,
            'role_id' => $role->id,
            'name' => 'Catalog Tester',
            'email' => 'catalog@test.local',
            'password' => bcrypt('secret'),
            'active' => true,
        ]));

        // The panel puts this in the session on login; CreateProduct reads it.
        OrganizationContext::setCurrentOrganizationId($org->id);
    }

    public function test_creating_a_product_with_packs_ticked_builds_its_variants(): void
    {
        Livewire::test(CreateProduct::class)
            ->fillForm([
                'name' => 'Test Spice',
                'category_id' => $this->category->id,
                'product_type' => 'others',
                'unit_type' => 'weight',
                'tax_category' => 'standard',
                'has_variants' => true,
                'generate_pack_sizes' => [100, 500, 1000],
                'generate_cost_per_kg' => 320,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $variants = ProductVariant::whereHas('product', fn ($q) => $q->where('name', 'Test Spice'))->get();

        $this->assertCount(3, $variants);
        $this->assertEqualsWithDelta(32.0, (float) $variants->firstWhere('pack_size', 100.000)->cost_price, 0.01);
        $this->assertNotNull($variants->firstWhere('unit', 'KG')->selling_price_nepal);
    }

    public function test_the_pack_section_only_appears_once_the_variants_toggle_is_on(): void
    {
        // The section is driven by a live toggle, so it is hidden until the
        // product is actually marked as having variants.
        Livewire::test(CreateProduct::class)
            ->fillForm(['has_variants' => false])
            ->assertFormFieldIsHidden('generate_pack_sizes')
            ->fillForm(['has_variants' => true])
            ->assertFormFieldIsVisible('generate_pack_sizes')
            ->assertFormFieldIsVisible('generate_cost_per_kg');
    }

    public function test_the_standard_packs_are_ticked_by_default(): void
    {
        Livewire::test(CreateProduct::class)
            ->fillForm(['has_variants' => true])
            ->assertFormSet(['generate_pack_sizes' => [20, 50, 100, 200, 250, 500, 1000]]);
    }

    public function test_a_product_without_variants_gets_none(): void
    {
        Livewire::test(CreateProduct::class)
            ->fillForm([
                'name' => 'Single Item',
                'category_id' => $this->category->id,
                'product_type' => 'others',
                'unit_type' => 'piece',
                'tax_category' => 'standard',
                'has_variants' => false,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertCount(0, ProductVariant::whereHas('product', fn ($q) => $q->where('name', 'Single Item'))->get());
    }
}
