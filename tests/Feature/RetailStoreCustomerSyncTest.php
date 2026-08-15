<?php

namespace Tests\Feature;

use App\Filament\Resources\RetailStoreResource\Pages\ListRetailStores;
use App\Models\Customer;
use App\Models\Organization;
use App\Models\RetailStore;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RetailStoreCustomerSyncTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Organization $org;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::factory()->create();

        $role = Role::create([
            'organization_id' => $this->org->id,
            'name' => 'Super Admin',
            'slug' => 'super-admin',
            'permissions' => ['view_retail_stores'],
            'is_system_role' => true,
        ]);

        $this->user = User::create([
            'organization_id' => $this->org->id,
            'role_id' => $role->id,
            'name' => 'Sync Tester',
            'email' => 'sync@test.local',
            'password' => bcrypt('secret'),
            'active' => true,
        ]);
    }

    private function store(string $name, ?string $phone): RetailStore
    {
        return RetailStore::create([
            'organization_id' => $this->org->id,
            'store_name' => $name,
            'contact_number' => $phone,
            'status' => 'active',
        ]);
    }

    /** Field teams record several numbers in one field; customers.phone holds 20 chars. */
    public function test_a_multi_number_contact_field_syncs_only_the_first_number(): void
    {
        $store = $this->store('Rolpa Agro Udhyog', '9847378934, 9847967263');

        $this->assertSame('9847378934', $store->fresh()->linkedCustomer?->phone);
    }

    public function test_recording_a_visit_survives_an_overlong_contact_number(): void
    {
        $store = $this->store('Laxmi Khadya Store', '9843455721, 01-5152031, 9841637123');

        $store->visits()->create([
            'organization_id' => $this->org->id,
            'visited_by' => $this->user->id,
            'visit_date' => now()->toDateString(),
            'visit_time' => now()->format('H:i:s'),
            'visit_purpose' => 'sales',
            'visit_outcome' => 'successful',
        ]);

        $store->markVisited();

        $this->assertSame(1, $store->visits()->count());
        $this->assertNotNull($store->fresh()->last_visited_at);
    }

    /** customers.phone is globally unique — a shared number must not collide or hijack. */
    public function test_a_shared_phone_number_does_not_steal_another_stores_customer(): void
    {
        $first = $this->store('Durga Cold Store', '9849108072');
        $second = $this->store('Durja cold store', '9849108072');

        $firstCustomer = $first->fresh()->linkedCustomer;
        $secondCustomer = $second->fresh()->linkedCustomer;

        $this->assertNotNull($firstCustomer);
        $this->assertNotNull($secondCustomer);
        $this->assertNotEquals($firstCustomer->id, $secondCustomer->id);
        $this->assertSame('9849108072', $firstCustomer->phone);
        $this->assertNull($secondCustomer->phone);
    }

    /** A timestamp bump must not re-run the sync and cannot fail the save. */
    public function test_mark_visited_does_not_touch_the_linked_customer(): void
    {
        $store = $this->store('Gyani Store', '9841337883');
        $customer = $store->fresh()->linkedCustomer;

        $customer->update(['name' => 'Manually Renamed']);

        $store->markVisited();

        $this->assertSame('Manually Renamed', $customer->fresh()->name);
    }

    public function test_a_store_with_no_contact_number_still_gets_a_customer(): void
    {
        $store = $this->store('Kirana Pasal', null);

        $customer = $store->fresh()->linkedCustomer;

        $this->assertNotNull($customer);
        $this->assertNull($customer->phone);
        $this->assertSame('Kirana Pasal', $customer->name);
    }

    public function test_sending_a_store_to_pos_hands_over_its_customer_id(): void
    {
        $store = $this->store('Bhim Kirana Store', '9843161911');

        $customer = $store->syncLinkedCustomer();

        $this->assertInstanceOf(Customer::class, $customer);
        $this->assertSame($store->id, $customer->retail_store_id);
    }

    public function test_the_record_visit_action_logs_a_visit_without_leaving_the_page(): void
    {
        $store = $this->store('Supriya Dairy', '9862905774, 9861926928');

        Livewire::actingAs($this->user)
            ->test(ListRetailStores::class)
            ->callTableAction('recordVisit', $store, [
                'visit_purpose' => 'sales',
                'visit_outcome' => 'successful',
                'notes' => 'Stock checked',
            ])
            ->assertHasNoTableActionErrors()
            ->assertNoRedirect();

        $this->assertSame(1, $store->visits()->count());
        $this->assertFalse((bool) $store->visits()->first()->order_placed);
    }

    public function test_save_and_take_order_records_the_visit_then_opens_pos_with_the_shop_selected(): void
    {
        $store = $this->store('Kabin Khadya Bhandar', '9803573682, 9860212427');

        Livewire::actingAs($this->user)
            ->test(ListRetailStores::class)
            ->callTableAction('recordVisit', $store, [
                'visit_purpose' => 'sales',
                'visit_outcome' => 'successful',
            ], arguments: ['takeOrder' => true])
            ->assertRedirect(route('filament.admin.pages.enhanced-p-o-s'));

        $visit = $store->visits()->first();

        $this->assertNotNull($visit);
        $this->assertTrue((bool) $visit->order_placed);
        $this->assertSame(
            $store->fresh()->linkedCustomer->id,
            session('new_customer_id'),
        );
    }

    public function test_record_visit_captures_the_follow_up_and_field_detail(): void
    {
        $store = $this->store('Ilam Store', '9863838503');

        Livewire::actingAs($this->user)
            ->test(ListRetailStores::class)
            ->callTableAction('recordVisit', $store, [
                'visit_purpose' => 'sales',
                'visit_outcome' => 'successful',
                'order_placed' => true,
                'order_value' => 4500,
                'next_visit_date' => now()->addDays(21)->toDateString(),
                'issues_found' => 'Shelf dusty, stock at back',
                'action_items' => 'Bring a new display rack',
                'competitor_notes' => 'Rival masala at Rs 180/kg',
            ])
            ->assertHasNoTableActionErrors();

        $visit = $store->visits()->first();

        $this->assertSame('4500.00', $visit->order_value);
        $this->assertSame(
            now()->addDays(21)->toDateString(),
            $visit->next_visit_date->toDateString(),
        );
        $this->assertSame('Shelf dusty, stock at back', $visit->issues_found);
        $this->assertSame('Bring a new display rack', $visit->action_items);
        $this->assertSame('Rival masala at Rs 180/kg', $visit->competitor_notes);
    }

    public function test_the_take_order_action_jumps_straight_to_pos(): void
    {
        $store = $this->store('Manjushree Masala', '9851361340, 9803973867');

        Livewire::actingAs($this->user)
            ->test(ListRetailStores::class)
            ->callTableAction('takeOrder', $store)
            ->assertRedirect(route('filament.admin.pages.enhanced-p-o-s'));

        $this->assertSame(0, $store->visits()->count());
        $this->assertSame($store->fresh()->linkedCustomer->id, session('new_customer_id'));
    }
}
