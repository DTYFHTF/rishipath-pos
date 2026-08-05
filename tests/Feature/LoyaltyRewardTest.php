<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Organization;
use App\Models\Reward;
use App\Models\Sale;
use App\Models\Store;
use App\Models\Terminal;
use App\Models\User;
use App\Services\LoyaltyService;
use Database\Seeders\LoyaltyRewardSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The business rule: spend Rs100,000, get Rs1,000 off.
 *
 * Points are Rs1 = 1 point, so the rule is expressed entirely as data —
 * a discount_fixed reward at 100,000 points worth Rs1,000.
 */
class LoyaltyRewardTest extends TestCase
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
            'name' => 'Cashier',
            'email' => 'cashier@test.local',
            'password' => bcrypt('secret'),
            'active' => true,
        ]);

        $this->seed(LoyaltyRewardSeeder::class);
    }

    private function customer(): Customer
    {
        return Customer::create([
            'organization_id' => $this->org->id,
            'customer_code' => 'C-'.uniqid(),
            'name' => 'Bina Shrestha',
            'phone' => '98'.random_int(10000000, 99999999),
            'active' => true,
        ]);
    }

    private function sale(Customer $customer, float $total): Sale
    {
        return Sale::create([
            'organization_id' => $this->org->id,
            'store_id' => $this->store->id,
            'terminal_id' => $this->terminalId,
            'cashier_id' => $this->cashier->id,
            'customer_id' => $customer->id,
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

    private function reward(): Reward
    {
        return Reward::where('organization_id', $this->org->id)
            ->where('name', 'Rs1,000 off')
            ->firstOrFail();
    }

    public function test_the_reward_is_seeded_as_100k_points_for_1000_off(): void
    {
        $reward = $this->reward();

        $this->assertSame('discount_fixed', $reward->type);
        $this->assertSame(100000, $reward->points_required);
        $this->assertSame(1000.0, (float) $reward->discount_value);
        $this->assertTrue((bool) $reward->active);
    }

    /** First purchase also enrols the customer, which carries a welcome bonus. */
    private const WELCOME_BONUS = 50;

    public function test_spending_earns_a_point_per_rupee_plus_a_welcome_bonus(): void
    {
        $customer = $this->customer();
        $service = app(LoyaltyService::class);

        $service->awardPointsForSale($this->sale($customer, 25000));

        $this->assertSame(25000 + self::WELCOME_BONUS, $customer->fresh()->loyalty_points);

        // The bonus is one-off — the second purchase earns Rs1 = 1 point flat.
        $service->awardPointsForSale($this->sale($customer, 10000));

        $this->assertSame(35000 + self::WELCOME_BONUS, $customer->fresh()->loyalty_points);
    }

    public function test_points_accumulate_across_purchases_until_the_reward_unlocks(): void
    {
        $customer = $this->customer();
        $service = app(LoyaltyService::class);
        $reward = $this->reward();

        // Rs25,000 three times — the reward is not a single-sale threshold.
        foreach (range(1, 3) as $ignored) {
            $service->awardPointsForSale($this->sale($customer, 25000));
        }

        $this->assertSame(75000 + self::WELCOME_BONUS, $customer->fresh()->loyalty_points);
        $this->assertFalse($reward->canBeRedeemedBy($customer->fresh()), 'Rs75,000 spent is not yet enough');

        $service->awardPointsForSale($this->sale($customer, 25000));

        $this->assertTrue($reward->canBeRedeemedBy($customer->fresh()), 'Rs100,000 spent unlocks it');
    }

    public function test_redeeming_deducts_the_points_and_returns_a_1000_discount(): void
    {
        $customer = $this->customer();
        $service = app(LoyaltyService::class);
        $service->awardPointsForSale($this->sale($customer, 100000));

        $before = $customer->fresh()->loyalty_points;

        $result = $service->redeemReward($customer->fresh(), $this->reward(), $this->cashier->id);

        $this->assertTrue($result['success']);
        $this->assertSame(1000.0, (float) $result['discount_value']);
        $this->assertSame('discount_fixed', $result['discount_type']);
        $this->assertSame($before - 100000, $customer->fresh()->loyalty_points, 'exactly 100,000 points are spent');
    }

    public function test_a_customer_below_the_threshold_cannot_redeem(): void
    {
        $customer = $this->customer();
        $service = app(LoyaltyService::class);

        // Deliberately short of 100,000 even after the welcome bonus.
        $service->awardPointsForSale($this->sale($customer, 90000));
        $before = $customer->fresh()->loyalty_points;

        $result = $service->redeemReward($customer->fresh(), $this->reward(), $this->cashier->id);

        $this->assertFalse($result['success']);
        $this->assertSame($before, $customer->fresh()->loyalty_points, 'a failed redemption must not spend points');
    }

    public function test_the_reward_can_be_earned_again_after_more_spending(): void
    {
        $customer = $this->customer();
        $service = app(LoyaltyService::class);

        $service->awardPointsForSale($this->sale($customer, 100000));
        $service->redeemReward($customer->fresh(), $this->reward(), $this->cashier->id);

        $service->awardPointsForSale($this->sale($customer, 100000));

        $this->assertTrue(
            $this->reward()->canBeRedeemedBy($customer->fresh()),
            'a loyal customer must be able to earn it a second time'
        );
    }

    public function test_reseeding_does_not_duplicate_the_reward(): void
    {
        $this->seed(LoyaltyRewardSeeder::class);
        $this->seed(LoyaltyRewardSeeder::class);

        $this->assertSame(1, Reward::where('organization_id', $this->org->id)->count());
    }
}
