<?php

namespace Tests\Feature;

use App\Models\Commission;
use App\Models\CommissionConfiguration;
use App\Models\Order;
use App\Models\Product;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\CommissionService;
use App\Services\TreeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Multi-Tier Referral Bonus is an OrderTriggeredIncomeModule, not tied
 * to any one PlanModule — these tests deliberately run it under
 * Unilevel (rather than the retired Package Tier plan, where its tier
 * ladder used to live) to prove it stacks on top of whichever plan is
 * active.
 */
class MultiTierReferralBonusTest extends TestCase
{
    use RefreshDatabase;

    private TreeService $tree;

    private CommissionService $commissions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tree = app(TreeService::class);
        $this->commissions = app(CommissionService::class);

        SystemSetting::set('active_plan_type', 'unilevel');
        SystemSetting::set('multi_tier_referral_bonus_enabled', 'true');
        SystemSetting::set('multi_tier_referral_bonus_condition_type', 'own_package');
    }

    private function root(): User
    {
        $root = User::factory()->create(['depth' => 0]);
        $this->tree->placeRoot($root);

        return $root;
    }

    private function completedOrder(User $buyer, float $amount = 100, bool $isPackage = true): Order
    {
        $product = Product::factory()->create([
            'price' => $amount,
            'commission_value' => $amount,
            'is_package' => $isPackage,
        ]);

        return Order::create([
            'user_id' => $buyer->id,
            'product_id' => $product->id,
            'order_number' => 'ORD-'.uniqid(),
            'amount' => $amount,
            'commission_value' => $amount,
            'status' => Order::STATUS_COMPLETED,
            'order_date' => now(),
            'payment_status' => 'paid',
        ]);
    }

    public function test_can_be_disabled(): void
    {
        SystemSetting::set('multi_tier_referral_bonus_enabled', 'false');

        CommissionConfiguration::create([
            'plan_type' => Commission::TYPE_MULTI_TIER_REFERRAL_BONUS, 'level' => 1, 'percentage' => 10,
            'is_active' => true, 'settings' => [],
        ]);

        $root = $this->root();
        $sponsor = $this->tree->placeNewUser(User::factory()->make(), $root, 'unilevel');
        $buyer = $this->tree->placeNewUser(User::factory()->make(), $sponsor, 'unilevel');

        $this->completedOrder($buyer, 100);

        $this->assertSame(0, Commission::where('plan_type', Commission::TYPE_MULTI_TIER_REFERRAL_BONUS)->count());
    }

    public function test_own_package_condition_gates_the_tier(): void
    {
        SystemSetting::set('multi_tier_referral_bonus_condition_type', 'own_package');

        CommissionConfiguration::create([
            'plan_type' => Commission::TYPE_MULTI_TIER_REFERRAL_BONUS, 'level' => 1, 'percentage' => 10,
            'is_active' => true, 'settings' => ['qualifying_amount' => 500],
        ]);

        $root = $this->root();
        $sponsor = $this->tree->placeNewUser(User::factory()->make(), $root, 'unilevel');

        // Sponsor has not bought a qualifying package yet: tier withheld.
        $buyerA = $this->tree->placeNewUser(User::factory()->make(), $sponsor, 'unilevel');
        $this->completedOrder($buyerA, 100);

        $this->assertSame(0, Commission::where('plan_type', Commission::TYPE_MULTI_TIER_REFERRAL_BONUS)->where('user_id', $sponsor->id)->count());

        // Sponsor now has a $500 package purchase of their own: tier unlocks.
        $this->completedOrder($sponsor, 500);

        $buyerB = $this->tree->placeNewUser(User::factory()->make(), $sponsor, 'unilevel');
        $this->completedOrder($buyerB, 100);

        $tierCommission = Commission::where('plan_type', Commission::TYPE_MULTI_TIER_REFERRAL_BONUS)->where('user_id', $sponsor->id)->first();
        $this->assertNotNull($tierCommission);
        $this->assertEquals(10.00, $tierCommission->amount); // 100 * 10%
    }

    public function test_team_volume_condition_gates_the_tier(): void
    {
        SystemSetting::set('multi_tier_referral_bonus_condition_type', 'team_volume');

        CommissionConfiguration::create([
            'plan_type' => Commission::TYPE_MULTI_TIER_REFERRAL_BONUS, 'level' => 1, 'percentage' => 10,
            'is_active' => true, 'settings' => ['qualifying_amount' => 200],
        ]);

        $root = $this->root();
        $lowVolumeSponsor = $this->tree->placeNewUser(User::factory()->make(['sales_volume' => 50]), $root, 'unilevel');
        $highVolumeSponsor = $this->tree->placeNewUser(User::factory()->make(['sales_volume' => 250]), $root, 'unilevel');

        $buyerA = $this->tree->placeNewUser(User::factory()->make(), $lowVolumeSponsor, 'unilevel');
        $this->completedOrder($buyerA, 100);
        $this->assertSame(0, Commission::where('plan_type', Commission::TYPE_MULTI_TIER_REFERRAL_BONUS)->where('user_id', $lowVolumeSponsor->id)->count());

        $buyerB = $this->tree->placeNewUser(User::factory()->make(), $highVolumeSponsor, 'unilevel');
        $this->completedOrder($buyerB, 100);
        $this->assertSame(1, Commission::where('plan_type', Commission::TYPE_MULTI_TIER_REFERRAL_BONUS)->where('user_id', $highVolumeSponsor->id)->count());
    }

    public function test_buyer_package_condition_gates_the_tier(): void
    {
        SystemSetting::set('multi_tier_referral_bonus_condition_type', 'buyer_package');

        CommissionConfiguration::create([
            'plan_type' => Commission::TYPE_MULTI_TIER_REFERRAL_BONUS, 'level' => 1, 'percentage' => 10,
            'is_active' => true, 'settings' => ['qualifying_amount' => 150],
        ]);

        $root = $this->root();
        $sponsor = $this->tree->placeNewUser(User::factory()->make(), $root, 'unilevel');

        $smallBuyer = $this->tree->placeNewUser(User::factory()->make(), $sponsor, 'unilevel');
        $this->completedOrder($smallBuyer, 100); // below threshold
        $this->assertSame(0, Commission::where('plan_type', Commission::TYPE_MULTI_TIER_REFERRAL_BONUS)->count());

        $bigBuyer = $this->tree->placeNewUser(User::factory()->make(), $sponsor, 'unilevel');
        $this->completedOrder($bigBuyer, 200); // meets threshold
        $this->assertSame(1, Commission::where('plan_type', Commission::TYPE_MULTI_TIER_REFERRAL_BONUS)->count());
    }

    public function test_stacks_on_top_of_whichever_plan_is_active(): void
    {
        CommissionConfiguration::create([
            'plan_type' => 'unilevel', 'level' => 1, 'percentage' => 10,
            'is_active' => true, 'settings' => [],
        ]);
        CommissionConfiguration::create([
            'plan_type' => Commission::TYPE_MULTI_TIER_REFERRAL_BONUS, 'level' => 1, 'percentage' => 5,
            'is_active' => true, 'settings' => [], // no condition set: always qualifies
        ]);

        $root = $this->root();
        $sponsor = $this->tree->placeNewUser(User::factory()->make(), $root, 'unilevel');
        $buyer = $this->tree->placeNewUser(User::factory()->make(), $sponsor, 'unilevel');

        $this->completedOrder($buyer, 100);

        $this->assertEquals(5.00, Commission::where('plan_type', Commission::TYPE_MULTI_TIER_REFERRAL_BONUS)->where('user_id', $sponsor->id)->value('amount'));
        $this->assertEquals(10.00, Commission::where('plan_type', 'unilevel')->where('user_id', $sponsor->id)->value('amount'));
    }

    /**
     * Regression test: the active plan's per-period cap must not absorb
     * the bonus's payouts, and vice versa. Both are paid to the same
     * upline from the same order, but tracked as separate plan_type
     * values so LevelLadderPayer::periodCommissionSum() — which filters
     * by plan_type only, not level — never conflates them.
     */
    public function test_plan_and_bonus_caps_track_independently(): void
    {
        CommissionConfiguration::create([
            'plan_type' => 'unilevel', 'level' => 1, 'percentage' => 10,
            'cap' => 8, 'is_active' => true, 'settings' => ['cap_period' => 'monthly'],
        ]);
        CommissionConfiguration::create([
            'plan_type' => Commission::TYPE_MULTI_TIER_REFERRAL_BONUS, 'level' => 1, 'percentage' => 5,
            'cap' => 3, 'is_active' => true, 'settings' => ['cap_period' => 'monthly'],
        ]);

        $root = $this->root();
        $sponsor = $this->tree->placeNewUser(User::factory()->make(), $root, 'unilevel');
        $buyer = $this->tree->placeNewUser(User::factory()->make(), $sponsor, 'unilevel');

        $this->completedOrder($buyer, 100); // plan would earn 10, capped to 8; bonus would earn 5, capped to 3

        $planAmount = Commission::where('plan_type', 'unilevel')->where('user_id', $sponsor->id)->value('amount');
        $bonusAmount = Commission::where('plan_type', Commission::TYPE_MULTI_TIER_REFERRAL_BONUS)->where('user_id', $sponsor->id)->value('amount');

        $this->assertEquals(8.00, $planAmount);
        $this->assertEquals(3.00, $bonusAmount);
        $this->assertEquals(11.00, $sponsor->fresh()->total_earnings);
    }
}
