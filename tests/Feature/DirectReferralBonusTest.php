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

/**
 * Direct Referral Bonus is an OrderTriggeredIncomeModule, not tied to
 * any one PlanModule — these tests deliberately run it under Unilevel
 * (rather than Package Tier, where it used to live) to prove it stacks
 * on top of whichever plan is active.
 */
class DirectReferralBonusTest extends TestCase
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
        SystemSetting::set('direct_referral_bonus_enabled', 'true');
        SystemSetting::set('direct_referral_bonus_percentage', '5');
    }

    private function root(): User
    {
        $root = User::factory()->create(['depth' => 0]);
        $this->tree->placeRoot($root);

        return $root;
    }

    private function completedOrder(User $buyer, float $amount = 100): Order
    {
        $product = Product::factory()->create([
            'price' => $amount,
            'commission_value' => $amount,
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

    public function test_pays_the_sponsor_unconditionally(): void
    {
        $root = $this->root();
        $sponsor = $this->tree->placeNewUser(User::factory()->make(), $root, 'unilevel');
        $buyer = $this->tree->placeNewUser(User::factory()->make(), $sponsor, 'unilevel');

        $this->completedOrder($buyer, 100);

        $bonus = Commission::where('plan_type', Commission::TYPE_DIRECT_REFERRAL_BONUS)->first();

        $this->assertNotNull($bonus);
        $this->assertSame($sponsor->id, $bonus->user_id);
        $this->assertEquals(5.00, $bonus->amount); // 100 * 5%
        $this->assertSame(0, $bonus->level);
    }

    public function test_can_be_disabled(): void
    {
        SystemSetting::set('direct_referral_bonus_enabled', 'false');

        $root = $this->root();
        $sponsor = $this->tree->placeNewUser(User::factory()->make(), $root, 'unilevel');
        $buyer = $this->tree->placeNewUser(User::factory()->make(), $sponsor, 'unilevel');

        $this->completedOrder($buyer, 100);

        $this->assertSame(0, Commission::where('plan_type', Commission::TYPE_DIRECT_REFERRAL_BONUS)->count());
    }

    public function test_stacks_on_top_of_whichever_plan_is_active(): void
    {
        CommissionConfiguration::create([
            'plan_type' => 'unilevel', 'level' => 1, 'percentage' => 10,
            'is_active' => true, 'settings' => [],
        ]);

        $root = $this->root();
        $sponsor = $this->tree->placeNewUser(User::factory()->make(), $root, 'unilevel');
        $buyer = $this->tree->placeNewUser(User::factory()->make(), $sponsor, 'unilevel');

        $this->completedOrder($buyer, 100);

        $this->assertEquals(5.00, Commission::where('plan_type', Commission::TYPE_DIRECT_REFERRAL_BONUS)->where('user_id', $sponsor->id)->value('amount'));
        $this->assertEquals(10.00, Commission::where('plan_type', 'unilevel')->where('user_id', $sponsor->id)->value('amount'));
    }

    /**
     * Regression test: the active plan's per-period cap must not absorb
     * the bonus's payouts. Both are paid to the same upline from the
     * same order, but tracked as separate plan_type values so
     * LevelLadderPayer::periodCommissionSum() — which
     * filters by plan_type only, not level — never conflates them.
     */
    public function test_plan_cap_does_not_count_the_bonus(): void
    {
        CommissionConfiguration::create([
            'plan_type' => 'unilevel', 'level' => 1, 'percentage' => 10,
            'cap' => 8, 'is_active' => true, 'settings' => ['cap_period' => 'monthly'],
        ]);

        $root = $this->root();
        $sponsor = $this->tree->placeNewUser(User::factory()->make(), $root, 'unilevel');
        $buyer = $this->tree->placeNewUser(User::factory()->make(), $sponsor, 'unilevel');

        $this->completedOrder($buyer, 100); // plan would earn 10, capped to 8; bonus earns 5, uncapped

        $planAmount = Commission::where('plan_type', 'unilevel')->where('user_id', $sponsor->id)->value('amount');
        $bonusAmount = Commission::where('plan_type', Commission::TYPE_DIRECT_REFERRAL_BONUS)->where('user_id', $sponsor->id)->value('amount');

        $this->assertEquals(8.00, $planAmount);
        $this->assertEquals(5.00, $bonusAmount);
        $this->assertEquals(13.00, $sponsor->fresh()->total_earnings);
    }

    public function test_rank_multiplier_scales_the_bonus(): void
    {
        $gold = Rank::create([
            'name' => 'Gold', 'level' => 1, 'min_sales_volume' => 0, 'min_downline' => 0, 'commission_multiplier' => 1.5,
        ]);

        $root = $this->root();
        $sponsor = $this->tree->placeNewUser(User::factory()->make(['rank_id' => $gold->id]), $root, 'unilevel');
        $buyer = $this->tree->placeNewUser(User::factory()->make(), $sponsor, 'unilevel');

        $this->completedOrder($buyer, 100);

        $this->assertEquals(7.50, Commission::where('plan_type', Commission::TYPE_DIRECT_REFERRAL_BONUS)->value('amount')); // 100*5%*1.5
    }
}
