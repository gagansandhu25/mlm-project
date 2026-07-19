<?php

namespace Tests\Feature;

use App\Models\Commission;
use App\Models\CommissionConfiguration;
use App\Models\Order;
use App\Models\Product;
use App\Models\Rank;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\CommissionService;
use App\Services\TreeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PackageTierCommissionTest extends TestCase
{
    use RefreshDatabase;

    private TreeService $tree;

    private CommissionService $commissions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tree = app(TreeService::class);
        $this->commissions = app(CommissionService::class);

        SystemSetting::set('active_plan_type', 'package_tier');
        SystemSetting::set('package_tier_direct_reward_enabled', 'true');
        SystemSetting::set('package_tier_direct_reward_percentage', '5');
        SystemSetting::set('package_tier_condition_type', 'own_package');
    }

    private function root(): User
    {
        $root = User::factory()->create(['depth' => 0]);
        $root->path = (string) $root->id;
        $root->save();

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

    public function test_direct_reward_pays_the_sponsor_unconditionally(): void
    {
        $root = $this->root();
        $sponsor = $this->tree->placeNewUser(User::factory()->make(), $root, 'package_tier');
        $buyer = $this->tree->placeNewUser(User::factory()->make(), $sponsor, 'package_tier');

        $this->completedOrder($buyer, 100);

        $reward = Commission::where('plan_type', Commission::TYPE_PACKAGE_TIER_DIRECT)->first();

        $this->assertNotNull($reward);
        $this->assertSame($sponsor->id, $reward->user_id);
        $this->assertEquals(5.00, $reward->amount); // 100 * 5%
        $this->assertSame(0, $reward->level);
    }

    public function test_direct_reward_can_be_disabled(): void
    {
        SystemSetting::set('package_tier_direct_reward_enabled', 'false');

        $root = $this->root();
        $sponsor = $this->tree->placeNewUser(User::factory()->make(), $root, 'package_tier');
        $buyer = $this->tree->placeNewUser(User::factory()->make(), $sponsor, 'package_tier');

        $this->completedOrder($buyer, 100);

        $this->assertSame(0, Commission::where('plan_type', Commission::TYPE_PACKAGE_TIER_DIRECT)->count());
    }

    public function test_own_package_condition_gates_the_tier(): void
    {
        SystemSetting::set('package_tier_condition_type', 'own_package');

        CommissionConfiguration::create([
            'plan_type' => 'package_tier', 'level' => 1, 'percentage' => 10,
            'is_active' => true, 'settings' => ['qualifying_amount' => 500],
        ]);

        $root = $this->root();
        $sponsor = $this->tree->placeNewUser(User::factory()->make(), $root, 'package_tier');

        // Sponsor has not bought a qualifying package yet: tier withheld.
        $buyerA = $this->tree->placeNewUser(User::factory()->make(), $sponsor, 'package_tier');
        $this->completedOrder($buyerA, 100);

        $this->assertSame(0, Commission::where('plan_type', 'package_tier')->where('user_id', $sponsor->id)->count());

        // Sponsor now has a $500 package purchase of their own: tier unlocks.
        $this->completedOrder($sponsor, 500);

        $buyerB = $this->tree->placeNewUser(User::factory()->make(), $sponsor, 'package_tier');
        $this->completedOrder($buyerB, 100);

        $tierCommission = Commission::where('plan_type', 'package_tier')->where('user_id', $sponsor->id)->first();
        $this->assertNotNull($tierCommission);
        $this->assertEquals(10.00, $tierCommission->amount); // 100 * 10%
    }

    public function test_team_volume_condition_gates_the_tier(): void
    {
        SystemSetting::set('package_tier_condition_type', 'team_volume');

        CommissionConfiguration::create([
            'plan_type' => 'package_tier', 'level' => 1, 'percentage' => 10,
            'is_active' => true, 'settings' => ['qualifying_amount' => 200],
        ]);

        $root = $this->root();
        $lowVolumeSponsor = $this->tree->placeNewUser(User::factory()->make(['sales_volume' => 50]), $root, 'package_tier');
        $highVolumeSponsor = $this->tree->placeNewUser(User::factory()->make(['sales_volume' => 250]), $root, 'package_tier');

        $buyerA = $this->tree->placeNewUser(User::factory()->make(), $lowVolumeSponsor, 'package_tier');
        $this->completedOrder($buyerA, 100);
        $this->assertSame(0, Commission::where('plan_type', 'package_tier')->where('user_id', $lowVolumeSponsor->id)->count());

        $buyerB = $this->tree->placeNewUser(User::factory()->make(), $highVolumeSponsor, 'package_tier');
        $this->completedOrder($buyerB, 100);
        $this->assertSame(1, Commission::where('plan_type', 'package_tier')->where('user_id', $highVolumeSponsor->id)->count());
    }

    public function test_buyer_package_condition_gates_the_tier(): void
    {
        SystemSetting::set('package_tier_condition_type', 'buyer_package');

        CommissionConfiguration::create([
            'plan_type' => 'package_tier', 'level' => 1, 'percentage' => 10,
            'is_active' => true, 'settings' => ['qualifying_amount' => 150],
        ]);

        $root = $this->root();
        $sponsor = $this->tree->placeNewUser(User::factory()->make(), $root, 'package_tier');

        $smallBuyer = $this->tree->placeNewUser(User::factory()->make(), $sponsor, 'package_tier');
        $this->completedOrder($smallBuyer, 100); // below threshold
        $this->assertSame(0, Commission::where('plan_type', 'package_tier')->count());

        $bigBuyer = $this->tree->placeNewUser(User::factory()->make(), $sponsor, 'package_tier');
        $this->completedOrder($bigBuyer, 200); // meets threshold
        $this->assertSame(1, Commission::where('plan_type', 'package_tier')->count());
    }

    public function test_direct_reward_and_tier_commission_both_apply_on_the_same_order(): void
    {
        CommissionConfiguration::create([
            'plan_type' => 'package_tier', 'level' => 1, 'percentage' => 10,
            'is_active' => true, 'settings' => [], // no condition set: always qualifies
        ]);

        $root = $this->root();
        $sponsor = $this->tree->placeNewUser(User::factory()->make(), $root, 'package_tier');
        $buyer = $this->tree->placeNewUser(User::factory()->make(), $sponsor, 'package_tier');

        $this->completedOrder($buyer, 100);

        $this->assertEquals(5.00, Commission::where('plan_type', Commission::TYPE_PACKAGE_TIER_DIRECT)->where('user_id', $sponsor->id)->value('amount'));
        $this->assertEquals(10.00, Commission::where('plan_type', 'package_tier')->where('user_id', $sponsor->id)->value('amount'));
    }

    /**
     * Regression test: a tier's per-period cap must not absorb the
     * direct reward's payouts. Both are paid to the same upline from
     * the same order, but must be tracked as separate plan_type values
     * (Commission::TYPE_PACKAGE_TIER vs TYPE_PACKAGE_TIER_DIRECT) so
     * LevelBasedCommissionCalculator::periodCommissionSum() — which
     * filters by plan_type only, not level — never conflates them.
     */
    public function test_tier_cap_does_not_count_the_direct_reward(): void
    {
        CommissionConfiguration::create([
            'plan_type' => 'package_tier', 'level' => 1, 'percentage' => 10,
            'cap' => 8, 'is_active' => true, 'settings' => ['cap_period' => 'monthly'],
        ]);

        $root = $this->root();
        $sponsor = $this->tree->placeNewUser(User::factory()->make(), $root, 'package_tier');
        $buyer = $this->tree->placeNewUser(User::factory()->make(), $sponsor, 'package_tier');

        $this->completedOrder($buyer, 100); // tier would earn 10, capped to 8; direct reward earns 5, uncapped

        $tierAmount = Commission::where('plan_type', 'package_tier')->where('user_id', $sponsor->id)->value('amount');
        $directAmount = Commission::where('plan_type', Commission::TYPE_PACKAGE_TIER_DIRECT)->where('user_id', $sponsor->id)->value('amount');

        $this->assertEquals(8.00, $tierAmount);
        $this->assertEquals(5.00, $directAmount);
        $this->assertEquals(13.00, $sponsor->fresh()->total_earnings);
    }

    public function test_rank_multiplier_scales_both_direct_reward_and_tier_commission(): void
    {
        $gold = Rank::create([
            'name' => 'Gold', 'level' => 1, 'min_sales_volume' => 0, 'min_downline' => 0, 'commission_multiplier' => 1.5,
        ]);

        CommissionConfiguration::create([
            'plan_type' => 'package_tier', 'level' => 1, 'percentage' => 10,
            'is_active' => true, 'settings' => [],
        ]);

        $root = $this->root();
        $sponsor = $this->tree->placeNewUser(User::factory()->make(['rank_id' => $gold->id]), $root, 'package_tier');
        $buyer = $this->tree->placeNewUser(User::factory()->make(), $sponsor, 'package_tier');

        $this->completedOrder($buyer, 100);

        $this->assertEquals(7.50, Commission::where('plan_type', Commission::TYPE_PACKAGE_TIER_DIRECT)->value('amount')); // 100*5%*1.5
        $this->assertEquals(15.00, Commission::where('plan_type', 'package_tier')->value('amount')); // 100*10%*1.5
    }
}
