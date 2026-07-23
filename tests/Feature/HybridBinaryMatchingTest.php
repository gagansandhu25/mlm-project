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
 * Hybrid Binary Matching is a peer to Binary Pairing Commission, not a
 * replacement — both independently consume left/right leg volume, so
 * Binary Pairing Commission is explicitly disabled in setUp() to avoid
 * double-crediting the same order's volume onto the same legs.
 */
class HybridBinaryMatchingTest extends TestCase
{
    use RefreshDatabase;

    private TreeService $tree;

    private CommissionService $commissions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tree = app(TreeService::class);
        $this->commissions = app(CommissionService::class);

        SystemSetting::set('active_plan_type', 'binary');
        SystemSetting::set('binary_pairing_commission_enabled', 'false');
        SystemSetting::set('hybrid_binary_matching_enabled', 'true');
        SystemSetting::set('hybrid_binary_matching_pair_value', '25');
        SystemSetting::set('hybrid_binary_matching_self_percentage', '7');
        SystemSetting::set('hybrid_binary_matching_active_package_resolver', 'highest_package_purchase');
        SystemSetting::set('hybrid_binary_matching_daily_cap_multiplier', '2');
        SystemSetting::set('hybrid_binary_matching_lifetime_cap_multiplier', '5');
    }

    private function root(): User
    {
        $root = User::factory()->create(['depth' => 0]);
        $this->tree->placeRoot($root);

        return $root;
    }

    /** Places two direct children under $sponsor: the first lands left, the second lands right. */
    private function legs(User $sponsor): array
    {
        $left = $this->tree->placeNewUser(User::factory()->make(), $sponsor, 'binary');
        $right = $this->tree->placeNewUser(User::factory()->make(), $sponsor, 'binary');

        return [$left, $right];
    }

    private function completedOrder(User $buyer, float $amount, bool $isPackage = false): Order
    {
        $product = Product::factory()->create(['price' => $amount, 'commission_value' => $amount, 'is_package' => $isPackage]);

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

    private function onePoolLevel(): void
    {
        CommissionConfiguration::create([
            'plan_type' => Commission::TYPE_HYBRID_BINARY_POOL, 'level' => 1, 'percentage' => 100,
            'is_active' => true, 'settings' => [],
        ]);
    }

    public function test_pays_self_in_discrete_pairs(): void
    {
        $this->onePoolLevel();

        $root = $this->root();
        [$left, $right] = $this->legs($root);
        $this->completedOrder($root, 1000, true); // gives root an active package

        $this->completedOrder($left, 100);
        $this->completedOrder($right, 100); // matched 100 -> 4 pairs

        $self = Commission::where('plan_type', Commission::TYPE_HYBRID_BINARY_MATCHING)->where('user_id', $root->id)->sole();
        $this->assertEquals(7.00, $self->amount); // 4 pairs * $25 * 7%
        $this->assertSame('right', $self->position);
        $this->assertSame(0, $self->level);
    }

    public function test_partial_pairs_leave_remainder_unconsumed(): void
    {
        $root = $this->root();
        [$left, $right] = $this->legs($root);
        $this->completedOrder($root, 1000, true);

        $this->completedOrder($left, 100);
        $this->completedOrder($right, 60); // matched 60 -> 2 pairs ($50 consumed), $10 leftover

        $self = Commission::where('plan_type', Commission::TYPE_HYBRID_BINARY_MATCHING)->sole();
        $this->assertEquals(3.50, $self->amount); // 2 pairs * $25 * 7%

        $root->refresh();
        $this->assertEquals(50.00, $root->left_volume); // 100 - 50 consumed
        $this->assertEquals(10.00, $root->right_volume); // 60 - 50 consumed
    }

    public function test_pool_is_funded_at_the_actual_self_amount_and_paid_to_the_matched_persons_own_upline(): void
    {
        $this->onePoolLevel();

        $grandsponsor = $this->root();
        $sponsor = $this->tree->placeNewUser(User::factory()->make(), $grandsponsor, 'binary');
        [$left, $right] = $this->legs($sponsor);
        $this->completedOrder($sponsor, 1000, true);

        $this->completedOrder($left, 100);
        $this->completedOrder($right, 100); // sponsor matched 100 -> self = $7.00

        $pool = Commission::where('plan_type', Commission::TYPE_HYBRID_BINARY_POOL)->where('user_id', $grandsponsor->id)->sole();
        $this->assertEquals(7.00, $pool->amount); // 100% of the $7 pool
        $this->assertSame(1, $pool->level);
    }

    public function test_dynamic_number_of_pool_levels_pays_each_configured_level(): void
    {
        CommissionConfiguration::create(['plan_type' => Commission::TYPE_HYBRID_BINARY_POOL, 'level' => 1, 'percentage' => 50, 'is_active' => true, 'settings' => []]);
        CommissionConfiguration::create(['plan_type' => Commission::TYPE_HYBRID_BINARY_POOL, 'level' => 2, 'percentage' => 30, 'is_active' => true, 'settings' => []]);
        CommissionConfiguration::create(['plan_type' => Commission::TYPE_HYBRID_BINARY_POOL, 'level' => 3, 'percentage' => 20, 'is_active' => true, 'settings' => []]);

        $topRoot = $this->root();
        $greatGrandsponsor = $this->tree->placeNewUser(User::factory()->make(), $topRoot, 'binary');
        $grandsponsor = $this->tree->placeNewUser(User::factory()->make(), $greatGrandsponsor, 'binary');
        $sponsor = $this->tree->placeNewUser(User::factory()->make(), $grandsponsor, 'binary');
        [$left, $right] = $this->legs($sponsor);
        $this->completedOrder($sponsor, 1000, true);

        $this->completedOrder($left, 100);
        $this->completedOrder($right, 100); // self = $7.00, pool = $7.00

        $this->assertEquals(3.50, Commission::where('plan_type', Commission::TYPE_HYBRID_BINARY_POOL)->where('user_id', $grandsponsor->id)->value('amount')); // 50%
        $this->assertEquals(2.10, Commission::where('plan_type', Commission::TYPE_HYBRID_BINARY_POOL)->where('user_id', $greatGrandsponsor->id)->value('amount')); // 30%
        $this->assertEquals(1.40, Commission::where('plan_type', Commission::TYPE_HYBRID_BINARY_POOL)->where('user_id', $topRoot->id)->value('amount')); // 20%
    }

    public function test_pool_shrinks_when_the_daily_cap_truncates_self(): void
    {
        $this->onePoolLevel();
        SystemSetting::set('hybrid_binary_matching_lifetime_cap_multiplier', '100000'); // isolate the daily cap

        $grandsponsor = $this->root();
        $sponsor = $this->tree->placeNewUser(User::factory()->make(), $grandsponsor, 'binary');
        [$left, $right] = $this->legs($sponsor);
        $this->completedOrder($sponsor, 10, true); // active_package = $10, daily cap = $20

        $this->completedOrder($left, 1000);
        $this->completedOrder($right, 1000); // matched 1000 -> 40 pairs -> theoretical $70, capped to $20

        $self = Commission::where('plan_type', Commission::TYPE_HYBRID_BINARY_MATCHING)->sole();
        $this->assertEquals(20.00, $self->amount);

        $pool = Commission::where('plan_type', Commission::TYPE_HYBRID_BINARY_POOL)->sole();
        $this->assertEquals(20.00, $pool->amount); // funded at the actual, capped self amount — not the theoretical $70
    }

    public function test_daily_cap_blocks_further_self_payouts_the_same_day(): void
    {
        SystemSetting::set('hybrid_binary_matching_lifetime_cap_multiplier', '100000');

        $root = $this->root();
        [$left, $right] = $this->legs($root);
        $this->completedOrder($root, 10, true); // daily cap = $20

        $this->completedOrder($left, 1000);
        $this->completedOrder($right, 1000); // first match consumes the entire $20 daily cap

        $this->completedOrder($left, 1000);
        $this->completedOrder($right, 1000); // second match same day: no daily room left

        $this->assertSame(1, Commission::where('plan_type', Commission::TYPE_HYBRID_BINARY_MATCHING)->count());
        $this->assertEquals(20.00, Commission::where('plan_type', Commission::TYPE_HYBRID_BINARY_MATCHING)->sum('amount'));
    }

    public function test_lifetime_cap_never_resets_across_days(): void
    {
        SystemSetting::set('hybrid_binary_matching_daily_cap_multiplier', '100000'); // isolate the lifetime cap

        $root = $this->root();
        [$left, $right] = $this->legs($root);
        $this->completedOrder($root, 10, true); // lifetime cap = $50

        // Day 1: pairs=8 -> base $14.00, well under the $50 lifetime cap.
        $this->completedOrder($left, 200);
        $this->completedOrder($right, 200);
        $this->assertEquals(14.00, Commission::where('plan_type', Commission::TYPE_HYBRID_BINARY_MATCHING)->sum('amount'));

        // Day 2 (daily cap would have reset, but lifetime keeps accumulating): another $14.00 -> cumulative $28.00.
        $this->travelTo(now()->addDay());
        $this->completedOrder($left, 200);
        $this->completedOrder($right, 200);
        $this->assertEquals(28.00, Commission::where('plan_type', Commission::TYPE_HYBRID_BINARY_MATCHING)->sum('amount'));

        // Day 3: theoretical $35.00 (pairs=20), but only $22.00 of lifetime room remains (50 - 28).
        $this->travelTo(now()->addDay());
        $this->completedOrder($left, 500);
        $this->completedOrder($right, 500);
        $this->assertEquals(50.00, Commission::where('plan_type', Commission::TYPE_HYBRID_BINARY_MATCHING)->sum('amount'));

        // Day 4: lifetime cap fully spent — nothing further pays out, ever.
        $this->travelTo(now()->addDay());
        $this->completedOrder($left, 500);
        $this->completedOrder($right, 500);
        $this->assertEquals(50.00, Commission::where('plan_type', Commission::TYPE_HYBRID_BINARY_MATCHING)->sum('amount'));
    }

    public function test_rank_multiplier_scales_the_self_payout(): void
    {
        $gold = Rank::create([
            'name' => 'Gold', 'level' => 1, 'min_sales_volume' => 0, 'min_downline' => 0, 'commission_multiplier' => 1.5,
        ]);

        $root = $this->root();
        $root->forceFill(['rank_id' => $gold->id])->save();
        [$left, $right] = $this->legs($root);
        $this->completedOrder($root, 1000, true);

        $this->completedOrder($left, 100);
        $this->completedOrder($right, 100); // 4 pairs * $25 * 7% * 1.5x

        $this->assertEquals(10.50, Commission::where('plan_type', Commission::TYPE_HYBRID_BINARY_MATCHING)->sole()->amount);
    }

    public function test_can_be_disabled(): void
    {
        SystemSetting::set('hybrid_binary_matching_enabled', 'false');

        $root = $this->root();
        [$left, $right] = $this->legs($root);
        $this->completedOrder($root, 1000, true);

        $this->completedOrder($left, 100);
        $this->completedOrder($right, 100);

        $this->assertSame(0, Commission::where('plan_type', Commission::TYPE_HYBRID_BINARY_MATCHING)->count());
        $this->assertSame(0, Commission::where('plan_type', Commission::TYPE_HYBRID_BINARY_POOL)->count());
        $root->refresh();
        $this->assertEquals(0.00, $root->left_volume); // handle() never even runs, so volume is never credited
    }

    public function test_inactive_upline_does_not_earn_or_accumulate_volume(): void
    {
        $root = $this->root();
        $sponsor = $this->tree->placeNewUser(User::factory()->make(['status' => User::STATUS_SUSPENDED]), $root, 'binary');
        [$left, $right] = $this->legs($sponsor);

        $this->completedOrder($left, 100);
        $this->completedOrder($right, 100);

        $sponsor->refresh();
        $this->assertEquals(0.00, $sponsor->left_volume);
        $this->assertEquals(0.00, $sponsor->right_volume);
        $this->assertFalse(Commission::where('user_id', $sponsor->id)->exists());
    }
}
