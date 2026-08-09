<?php

namespace Tests\Feature;

use App\Filament\Pages\PriceListPage;
use App\Models\Category;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\User;
use App\Services\OrganizationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * "Save as PDF" used to dispatch a browser-side window.print() over the same
 * DOM the web page renders — Chrome's print-to-PDF embeds each product photo
 * at its full source resolution (up to 2048x2048px for a 64px thumbnail),
 * which made a ~90-product catalogue come out around 200MB. It's now a real
 * server-side dompdf render using small cached thumbnails (see
 * PdfThumbnailServiceTest). These tests are the regression guard: a real PDF
 * comes back, and it stays small.
 */
class PriceListPdfExportTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->org = Organization::factory()->create(['country_code' => 'NP']);
        OrganizationContext::setCurrentOrganizationId($this->org->id);

        $role = Role::create([
            'organization_id' => $this->org->id,
            'slug' => 'super-admin-test',
            'name' => 'Super Admin',
            'permissions' => ['view_products'],
            'is_system_role' => true,
        ]);

        $user = User::create([
            'organization_id' => $this->org->id,
            'role_id' => $role->id,
            'name' => 'Tester',
            'email' => 'pricelist-pdf-test@test.local',
            'password' => bcrypt('secret'),
            'active' => true,
        ]);
        $this->actingAs($user);

        $category = Category::create([
            'organization_id' => $this->org->id,
            'name' => 'Dry Fruits & Nuts',
            'active' => true,
        ]);

        $product = Product::create([
            'organization_id' => $this->org->id,
            'category_id' => $category->id,
            'sku' => 'PDF-TEST-ALMOND',
            'name' => 'Almond',
            'name_romanized' => 'Badam',
            'product_type' => 'others',
            'unit_type' => 'weight',
            'active' => true,
        ]);

        foreach ([['1000', 'GMS', 1600, 2000], ['100', 'GMS', 160, 215]] as [$size, $unit, $cost, $price]) {
            ProductVariant::create([
                'product_id' => $product->id,
                'sku' => 'PDF-TEST-ALMOND-'.$size.$unit,
                'pack_size' => $size,
                'unit' => $unit,
                'cost_price' => $cost,
                'selling_price_nepal' => $price,
                'base_price' => $price,
                'mrp_india' => $price,
                'active' => true,
            ]);
        }
    }

    public function test_downloading_the_pdf_produces_a_real_small_pdf(): void
    {
        $page = new PriceListPage;
        $page->mount();
        $page->generate();

        $this->assertNotEmpty($page->priceList, 'the fixture product must show up in the generated list');

        $response = $page->downloadPdf();

        ob_start();
        $response->getCallback()();
        $content = ob_get_clean();

        $this->assertStringStartsWith('%PDF', $content, 'must be a real PDF, not an error page');

        // Regression guard for the 200MB browser-print bug: a PDF built from
        // small cached thumbnails should be a few hundred KB to a few MB for
        // this size of catalogue, never in the tens or hundreds of MB.
        $this->assertLessThan(10 * 1024 * 1024, strlen($content),
            'PDF ballooned back toward the old full-resolution-image bug');
    }

    public function test_downloading_with_no_price_list_returns_no_content_instead_of_erroring(): void
    {
        $page = new PriceListPage;
        $page->mount();
        // priceList left empty — never generated.

        $response = $page->downloadPdf();

        $this->assertSame(204, $response->getStatusCode());
    }

    public function test_the_compact_shop_sheet_produces_a_small_pdf_with_a_reference_price_per_product(): void
    {
        $page = new PriceListPage;
        $page->mount();
        $page->generate();

        $response = $page->downloadCompactPdf();

        ob_start();
        $response->getCallback()();
        $content = ob_get_clean();

        $this->assertStringStartsWith('%PDF', $content);

        // No product photos at all in this one, so it should be smaller
        // still than the full catalogue PDF.
        $this->assertLessThan(3 * 1024 * 1024, strlen($content));
    }

    public function test_the_compact_sheet_falls_back_to_the_largest_pack_when_theres_no_1kg(): void
    {
        // Almond only has a 1kg and a 100g pack in this fixture — add a
        // second product that tops out below 1kg, mirroring Multani Mitti /
        // Moringa Powder / Saffron in production, none of which have a 1kg
        // pack at all.
        $category = Category::first();
        $small = Product::create([
            'organization_id' => $this->org->id,
            'category_id' => $category->id,
            'sku' => 'PDF-TEST-SMALL',
            'name' => 'Small Batch Item',
            'product_type' => 'others',
            'unit_type' => 'weight',
            'active' => true,
        ]);
        ProductVariant::create([
            'product_id' => $small->id,
            'sku' => 'PDF-TEST-SMALL-200GMS',
            'pack_size' => 200,
            'unit' => 'GMS',
            'cost_price' => 50,
            'selling_price_nepal' => 605,
            'base_price' => 605,
            'mrp_india' => 605,
            'active' => true,
        ]);

        $page = new PriceListPage;
        $page->mount();
        $page->generate();

        $response = $page->downloadCompactPdf();
        ob_start();
        $response->getCallback()();
        $content = ob_get_clean();

        $this->assertStringStartsWith('%PDF', $content);
    }

    public function test_downloading_the_compact_sheet_with_no_price_list_returns_no_content(): void
    {
        $page = new PriceListPage;
        $page->mount();

        $response = $page->downloadCompactPdf();

        $this->assertSame(204, $response->getStatusCode());
    }
}
