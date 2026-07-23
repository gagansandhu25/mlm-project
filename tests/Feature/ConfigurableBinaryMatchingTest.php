<?php

namespace Tests\Feature;

use App\Models\Commission;
use App\Models\CommissionConfiguration;
use App\Models\Order;
use App\Models\Product;
use App\Models\Rank;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\TreeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Configurable Binary Matching is a third peer alongside Binary Pairing
 * Commission and Hybrid Binary Matching — both are explicitly disabled
 * here (same reasoning HybridBinaryMatchingTest disables Binary
 * Pairing) since Volume basis reuses the same left/right leg volume.
 */
class ConfigurableBinaryMatchingTest extends TestCase
{
    use RefreshDatabase;

    private TreeService $tree;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tree = app(TreeService::class);

        SystemSetting::set('active_plan_type', 'binary');
        SystemSetting::set('binary_pairing_commission_enabled', 'false');
        SystemSetting::set('hybrid_binary_matching_enabled', 'false');
        SystemSetting::set('configurable_binary_matching_enabled', 'true');
        SystemSetting::set('configurable_binary_matching_basis', 'volume');
        SystemSetting::set('configurable_binary_matching_qualification_rule', 'every_order');
        SystemSetting::set('configurable_binary_matching_payout_formula', 'flat_per_pair');
        SystemSetting::set('configurable_binary_matching_pool_enabled', 'false');
        SystemSetting::set('volume_basis_pair_value', '25');
        SystemSetting::set('volume_basis_active_package_resolver', 'highest_package_purchase');
        SystemSetting::set('volume_basis_daily_cap_multiplier', '2');
        SystemSetting::set('volume_basis_lifetime_cap_multiplier', '5');
        SystemSetting::set('count_basis_members_per_pair', '1');
        SystemSetting::set('count_basis_daily_cap_pairs', '1000');
        SystemSetting::set('count_basis_lifetime_cap_pairs', '100000');
        SystemSetting::set('flat_per_pair_amount', '5');
        SystemSetting::set('percentage_of_matched_volume_percentage', '7');
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

    public function test_volume_flat_per_pair_pays_flat_amount_per_matched_pair(): void
    {
        $root = $this->root();
        [$left, $right] = $this->legs($root);
        $this->completedOrder($root, 1000, true); // gives root an active package

        $this->completedOrder($left, 100);
        $this->completedOrder($right, 100); // matched 100 -> 4 pairs of $25

        $self = Commission::where('plan_type', Commission::TYPE_CONFIGURABLE_BINARY_MATCHING)->sole();
        $this->assertEquals(20.00, $self->amount); // 4 pairs * $5 flat
        $this->assertEquals(4.0, (float) $self->units_matched);
    }

    public function test_volume_percentage_of_matched_volume_matches_hybrid_math(): void
    {
        SystemSetting::set('configurable_binary_matching_payout_formula', 'percentage_of_matched_volume');

        $root = $this->root();
        [$left, $right] = $this->legs($root);
        $this->completedOrder($root, 1000, true);

        $this->completedOrder($left, 100);
        $this->completedOrder($right, 100); // matched 100 -> 4 pairs * $25 * 7%

        $self = Commission::where('plan_type', Commission::TYPE_CONFIGURABLE_BINARY_MATCHING)->sole();
        $this->assertEquals(7.00, $self->amount);
    }

    public function test_count_flat_per_pair_pays_on_member_pairs_not_volume(): void
    {
        SystemSetting::set('configurable_binary_matching_basis', 'count');

        $root = $this->root();
        [$left, $right] = $this->legs($root);

        $this->completedOrder($left, 9999); // volume shouldn't matter under Count basis
        $this->completedOrder($right, 1);

        $self = Commission::where('plan_type', Commission::TYPE_CONFIGURABLE_BINARY_MATCHING)->sole();
        $this->assertEquals(5.00, $self->amount); // 1 pair * $5 flat
        $this->assertEquals(1.0, (float) $self->units_matched);

        $root->refresh();
        $this->assertEquals(0.00, $root->left_volume);
        $this->assertEquals(0.00, $root->right_volume);
        $this->assertSame(0, $root->left_count);
        $this->assertSame(0, $root->right_count);
    }

    public function test_count_every_order_counts_repeat_orders_again(): void
    {
        SystemSetting::set('configurable_binary_matching_basis', 'count');

        $root = $this->root();
        [$left, $right] = $this->legs($root);

        $this->completedOrder($left, 50);
        $this->completedOrder($left, 50); // same buyer orders again -> counts again under every_order

        $root->refresh();
        $this->assertSame(2, $root->left_count);
        $this->assertSame(0, $root->right_count);
        $this->assertSame(0, Commission::where('plan_type', Commission::TYPE_CONFIGURABLE_BINARY_MATCHING)->count());
    }

    public function test_count_first_order_only_does_not_recount_repeat_orders(): void
    {
        SystemSetting::set('configurable_binary_matching_basis', 'count');
        SystemSetting::set('configurable_binary_matching_qualification_rule', 'first_order_only');

        $root = $this->root();
        [$left, $right] = $this->legs($root);

        $this->completedOrder($left, 50);
        $this->completedOrder($left, 50); // same buyer's second order doesn't count again
        $this->completedOrder($right, 50); // a different buyer's first order does count

        $self = Commission::where('plan_type', Commission::TYPE_CONFIGURABLE_BINARY_MATCHING)->sole();
        $this->assertEquals(5.00, $self->amount); // exactly 1 pair matched
        $this->assertEquals(1.0, (float) $self->units_matched);
    }

    public function test_count_daily_cap_truncates_pairs_and_carries_remainder(): void
    {
        SystemSetting::set('configurable_binary_matching_basis', 'count');
        SystemSetting::set('count_basis_daily_cap_pairs', '1');

        $root = $this->root();
        [$left, $right] = $this->legs($root);

        $this->completedOrder($left, 10);
        $this->completedOrder($left, 10);
        $this->completedOrder($left, 10); // left_count -> 3

        $this->completedOrder($right, 10);
        $this->completedOrder($right, 10);
        $this->completedOrder($right, 10); // right_count -> 3, uncapped 3 pairs, capped to 1/day

        $this->assertSame(1, Commission::where('plan_type', Commission::TYPE_CONFIGURABLE_BINARY_MATCHING)->count());
        $this->assertEquals(5.00, Commission::where('plan_type', Commission::TYPE_CONFIGURABLE_BINARY_MATCHING)->sum('amount'));

        $root->refresh();
        $this->assertSame(2, $root->left_count); // 3 - 1 consumed by the one paid pair
        $this->assertSame(2, $root->right_count);
    }

    public function test_volume_daily_cap_truncates_self_payout(): void
    {
        SystemSetting::set('configurable_binary_matching_payout_formula', 'percentage_of_matched_volume');
        SystemSetting::set('volume_basis_lifetime_cap_multiplier', '100000'); // isolate the daily cap

        $root = $this->root();
        [$left, $right] = $this->legs($root);
        $this->completedOrder($root, 10, true); // active package = $10, daily cap = $20

        $this->completedOrder($left, 1000);
        $this->completedOrder($right, 1000); // matched 1000 -> 40 pairs -> theoretical $70, capped to $20

        $self = Commission::where('plan_type', Commission::TYPE_CONFIGURABLE_BINARY_MATCHING)->sole();
        $this->assertEquals(20.00, $self->amount);
    }

    public function test_pool_distribution_pays_matched_persons_upline(): void
    {
        SystemSetting::set('configurable_binary_matching_pool_enabled', 'true');
        CommissionConfiguration::create([
            'plan_type' => Commission::TYPE_CONFIGURABLE_BINARY_POOL, 'level' => 1, 'percentage' => 100,
            'is_active' => true, 'settings' => [],
        ]);

        $grandsponsor = $this->root();
        $sponsor = $this->tree->placeNewUser(User::factory()->make(), $grandsponsor, 'binary');
        [$left, $right] = $this->legs($sponsor);
        $this->completedOrder($sponsor, 1000, true);

        $this->completedOrder($left, 100);
        $this->completedOrder($right, 100); // sponsor matched 100 -> 4 pairs * $5 = $20 self

        $pool = Commission::where('plan_type', Commission::TYPE_CONFIGURABLE_BINARY_POOL)->where('user_id', $grandsponsor->id)->sole();
        $this->assertEquals(20.00, $pool->amount);
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
        $this->completedOrder($right, 100); // 4 pairs * $5 * 1.5x

        $this->assertEquals(30.00, Commission::where('plan_type', Commission::TYPE_CONFIGURABLE_BINARY_MATCHING)->sole()->amount);
    }

    public function test_can_be_disabled(): void
    {
        SystemSetting::set('configurable_binary_matching_enabled', 'false');

        $root = $this->root();
        [$left, $right] = $this->legs($root);
        $this->completedOrder($root, 1000, true);

        $this->completedOrder($left, 100);
        $this->completedOrder($right, 100);

        $this->assertSame(0, Commission::where('plan_type', Commission::TYPE_CONFIGURABLE_BINARY_MATCHING)->count());
        $root->refresh();
        $this->assertEquals(0.00, $root->left_volume);
    }

    public function test_inactive_upline_does_not_earn_or_accumulate(): void
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
