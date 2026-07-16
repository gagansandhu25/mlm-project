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

class BinaryCommissionTest extends TestCase
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

        CommissionConfiguration::create(['plan_type' => 'binary', 'level' => 1, 'percentage' => 10, 'is_active' => true]);
    }

    private function root(): User
    {
        $root = User::factory()->create(['depth' => 0]);
        $root->path = (string) $root->id;
        $root->save();

        return $root;
    }

    /** Places two direct children under $sponsor: the first lands left, the second lands right. */
    private function legs(User $sponsor): array
    {
        $left = $this->tree->placeNewUser(User::factory()->make(), $sponsor, 'binary');
        $right = $this->tree->placeNewUser(User::factory()->make(), $sponsor, 'binary');

        return [$left, $right];
    }

    private function completedOrder(User $buyer, float $amount): Order
    {
        $product = Product::factory()->create(['price' => $amount, 'commission_value' => $amount]);

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

    public function test_no_payout_until_both_legs_carry_volume(): void
    {
        $root = $this->root();
        [$left, $right] = $this->legs($root);

        $this->completedOrder($left, 100);

        $this->assertSame(0, Commission::count());
        $root->refresh();
        $this->assertEquals(100.00, $root->left_volume);
        $this->assertEquals(0.00, $root->right_volume);
    }

    public function test_matched_volume_is_paid_and_excess_carries_forward(): void
    {
        $root = $this->root();
        [$left, $right] = $this->legs($root);

        $this->completedOrder($left, 100);
        $this->completedOrder($right, 60);

        $commission = Commission::where('user_id', $root->id)->sole();
        $this->assertEquals(6.00, $commission->amount); // matched 60 * 10%
        $this->assertSame(Commission::TYPE_BINARY, $commission->plan_type);

        $root->refresh();
        $this->assertEquals(40.00, $root->left_volume); // 100 - 60 matched, carries forward
        $this->assertEquals(0.00, $root->right_volume);

        // A further right-leg order matches against the carried-forward left excess.
        $this->completedOrder($right, 50);

        $this->assertEquals(10.00, Commission::where('user_id', $root->id)->sum('amount')); // 6 + 4 (40 * 10%)
        $root->refresh();
        $this->assertEquals(0.00, $root->left_volume);
        $this->assertEquals(10.00, $root->right_volume);
    }

    public function test_rank_multiplier_scales_the_pairing_bonus(): void
    {
        $gold = Rank::create([
            'name' => 'Gold', 'level' => 1, 'min_sales_volume' => 0, 'min_downline' => 0, 'commission_multiplier' => 1.5,
        ]);

        $root = $this->root();
        $root->forceFill(['rank_id' => $gold->id])->save();
        [$left, $right] = $this->legs($root);

        $this->completedOrder($left, 100);
        $this->completedOrder($right, 100);

        $commission = Commission::where('user_id', $root->id)->sole();
        $this->assertEquals(15.00, $commission->amount); // 100 matched * 10% * 1.5x
    }

    public function test_per_period_cap_truncates_payout_and_leaves_the_remainder_unconsumed(): void
    {
        CommissionConfiguration::where('plan_type', 'binary')->where('level', 1)->update([
            'cap' => 5, 'settings' => ['cap_period' => 'monthly'],
        ]);

        $root = $this->root();
        [$left, $right] = $this->legs($root);

        $this->completedOrder($left, 100);
        $this->completedOrder($right, 100);

        $commission = Commission::where('user_id', $root->id)->sole();
        $this->assertEquals(5.00, $commission->amount); // capped from 10.00

        $root->refresh();
        // Only half the matched volume (proportional to 5/10 paid) was consumed.
        $this->assertEquals(50.00, $root->left_volume);
        $this->assertEquals(50.00, $root->right_volume);

        // Cap already met for the period: no further payout even though volume still matches.
        $this->completedOrder($right, 10);
        $this->assertSame(1, Commission::where('user_id', $root->id)->count());
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
