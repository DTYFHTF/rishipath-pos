<?php

namespace Tests\Feature;

use App\Filament\Resources\ProductResource\Pages\EditProduct;
use App\Models\Category;
use App\Models\Organization;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Services\OrganizationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The product form's image_url upload defines its own afterStateHydrated,
 * which replaces the one BaseFileUpload sets up for itself — the one that
 * normally turns a stored path into the keyed array the component works in.
 * When that conversion was missing, any product whose image_url held a plain
 * disk path (everything the website image sync writes) threw "foreach()
 * argument must be of type array|object, string given" on the next Livewire
 * round trip, which is a 500 the moment you click a tab.
 */
class ProductFormImageStateTest extends TestCase
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
            'permissions' => ['view_products', 'edit_products', 'create_product_variants'],
            'is_system_role' => true,
        ]);

        $this->actingAs(User::create([
            'organization_id' => $org->id,
            'role_id' => $role->id,
            'name' => 'Form Tester',
            'email' => 'form@test.local',
            'password' => bcrypt('secret'),
            'active' => true,
        ]));

        OrganizationContext::setCurrentOrganizationId($org->id);
    }

    private function product(?string $imageUrl): Product
    {
        return Product::factory()->create([
            'organization_id' => $this->category->organization_id,
            'category_id' => $this->category->id,
            'image_url' => $imageUrl,
        ]);
    }

    public function test_a_synced_disk_path_survives_a_round_trip(): void
    {
        $product = $this->product('product-images/web/shd-cuse-1-01m0fbepnn.jpg');

        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->assertSuccessful()
            // What the upload's own JS calls on every round trip, a tab click
            // included. This is the call that used to blow up.
            ->call('getFormUploadedFiles', 'data.image_url')
            ->assertSuccessful();

        // The stored path must not be wiped by simply opening the form.
        $this->assertSame('product-images/web/shd-cuse-1-01m0fbepnn.jpg', $product->refresh()->image_url);
    }

    public function test_a_public_folder_path_survives_a_round_trip(): void
    {
        $product = $this->product('/images/productv2-webp/cumin-seeds.webp');

        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->assertSuccessful()
            ->call('getFormUploadedFiles', 'data.image_url')
            ->assertSuccessful();

        $this->assertSame('/images/productv2-webp/cumin-seeds.webp', $product->refresh()->image_url);
    }

    public function test_a_product_with_no_image_still_opens(): void
    {
        $product = $this->product(null);

        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->assertSuccessful()
            ->call('getFormUploadedFiles', 'data.image_url')
            ->assertSuccessful();
    }
}
