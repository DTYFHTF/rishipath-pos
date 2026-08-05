<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Every screen in the admin panel still renders.
 *
 * Filament discovers resources, pages and widgets from the filesystem, so a
 * class deleted in one place and still referenced in another fails only when
 * somebody opens the page. Removing the bulk order inquiry feature touched
 * shared screens (feedback, retail stores), which is exactly the situation
 * where that bites.
 */
class AdminPanelSmokeTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $org = Organization::factory()->create(['country_code' => 'NP']);

        // Some resources gate on the slug via isSuperAdmin(), others check a
        // named permission directly, so the real seeded role is needed to get
        // past both and actually render the tables.
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);

        $role = Role::where('organization_id', $org->id)
            ->where('slug', 'super-admin')
            ->firstOrFail();

        $this->admin = User::create([
            'organization_id' => $org->id,
            'role_id' => $role->id,
            'name' => 'Admin',
            'email' => 'smoke@test.local',
            'password' => bcrypt('secret'),
            'active' => true,
        ]);

        $this->actingAs($this->admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_every_discovered_page_class_is_loadable(): void
    {
        $panel = Filament::getPanel('admin');

        $classes = array_merge(
            $panel->getResources(),
            $panel->getPages(),
            $panel->getWidgets(),
        );

        $this->assertGreaterThan(40, count($classes), 'the panel should discover its screens');

        foreach ($classes as $class) {
            $this->assertTrue(class_exists($class), "{$class} is registered but missing");
        }
    }

    /**
     * Renders every resource listing in the panel.
     *
     * A dangling relation only fails at render — RetailStoreResource kept a
     * ->counts('bulkOrderInquiries') column long after the relation was gone,
     * and no grep for the class name would ever have found it.
     */
    public function test_every_resource_listing_renders(): void
    {
        $failures = [];

        foreach (Filament::getPanel('admin')->getResources() as $resource) {
            $listPage = $resource::getPages()['index'] ?? null;

            if (! $listPage) {
                continue;
            }

            $page = $listPage->getPage();

            try {
                Livewire::test($page)->assertOk();
            } catch (\Throwable $e) {
                $failures[] = class_basename($resource).': '.
                    explode("\n", $e->getMessage())[0];
            }
        }

        $this->assertSame([], $failures, "resource listings failed to render:\n".implode("\n", $failures));
    }

    public function test_the_removed_screens_are_gone(): void
    {
        foreach ([
            'App\Models\BulkOrderInquiry',
            'App\Models\Invoice',
            'App\Models\InvoiceLine',
            'App\Filament\Resources\BulkOrderInquiryResource',
            'App\Filament\Resources\FeedbackResource\Pages\ListBulkOrderFeedbacks',
            'App\Notifications\NewBulkOrderInquiry',
        ] as $class) {
            $this->assertFalse(class_exists($class), "{$class} should have been removed");
        }

        $uris = collect(app('router')->getRoutes())->map(fn ($r) => $r->uri());
        $this->assertEmpty(
            $uris->filter(fn ($u) => str_contains($u, 'bulk-order'))->all(),
            'no bulk-order route should survive'
        );
    }

    public function test_sale_invoice_pdf_route_survives(): void
    {
        // Sale receipts render straight to PDF and never persisted an Invoice
        // row, so dropping the invoices tables must not touch this.
        $uris = collect(app('router')->getRoutes())->map(fn ($r) => $r->uri());

        $this->assertNotEmpty(
            $uris->filter(fn ($u) => str_contains($u, 'invoice'))->all(),
            'the sale invoice PDF route must still exist'
        );
    }
}
