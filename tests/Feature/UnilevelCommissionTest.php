<?php

namespace Tests\Feature;

use App\Models\Commission;
use App\Models\CommissionConfiguration;
use App\Models\Order;
use App\Models\Product;
use App\Models\Rank;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\Wallet;
use App\Services\CommissionService;
use App\Services\TreeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnilevelCommissionTest extends TestCase
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

        CommissionConfiguration::create(['plan_type' => 'unilevel', 'level' => 1, 'percentage' => 10, 'is_active' => true]);
        CommissionConfiguration::create(['plan_type' => 'unilevel', 'level' => 2, 'percentage' => 5, 'is_active' => true]);
        CommissionConfiguration::create(['plan_type' => 'unilevel', 'level' => 3, 'percentage' => 3, 'is_active' => true]);
    }

    private function root(): User
    {
        $root = User::factory()->create(['depth' => 0]);
        $this->tree->placeRoot($root);

        return $root;
    }

    private function completedOrder(User $buyer, float $amount = 100): Order
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

    public function test_each_qualifying_upline_earns_their_configured_level_percentage(): void
    {
        $root = $this->root();
        $level1 = $this->tree->placeNewUser(User::factory()->make(), $root, 'unilevel');
        $level2Buyer = $this->tree->placeNewUser(User::factory()->make(), $level1, 'unilevel');

        // The OrderObserver runs the commission engine the moment the order is saved as completed.
        $this->completedOrder($level2Buyer, 100);

        $this->assertSame(2, Commission::count());

        $level1Commission = Commission::where('user_id', $level1->id)->first();
        $rootCommission = Commission::where('user_id', $root->id)->first();

        $this->assertEquals(10.00, $level1Commission->amount); // 100 * 10%
        $this->assertSame(1, $level1Commission->level);

        $this->assertEquals(5.00, $rootCommission->amount); // 100 * 5%
        $this->assertSame(2, $rootCommission->level);
    }

    public function test_commission_is_capped_beyond_configured_max_level(): void
    {
        // Only 3 levels configured; a 4th-level ancestor should not be paid.
        $root = $this->root();
        $l1 = $this->tree->placeNewUser(User::factory()->make(), $root, 'unilevel');
        $l2 = $this->tree->placeNewUser(User::factory()->make(), $l1, 'unilevel');
        $l3 = $this->tree->placeNewUser(User::factory()->make(), $l2, 'unilevel');
        $buyer = $this->tree->placeNewUser(User::factory()->make(), $l3, 'unilevel');

        $this->completedOrder($buyer, 100);

        $this->assertSame(3, Commission::count()); // l3, l2, l1 — not root (level 4)
        $this->assertFalse(Commission::where('user_id', $root->id)->exists());
    }

    public function test_inactive_upline_does_not_earn_commission(): void
    {
        $root = $this->root();
        $sponsor = $this->tree->placeNewUser(User::factory()->make(['status' => User::STATUS_SUSPENDED]), $root, 'unilevel');
        $buyer = $this->tree->placeNewUser(User::factory()->make(), $sponsor, 'unilevel');

        $this->completedOrder($buyer, 100);

        $this->assertFalse(Commission::where('user_id', $sponsor->id)->exists());
        $this->assertSame(1, Commission::count()); // only root (level 2) gets paid
    }

    public function test_rank_multiplier_scales_the_commission_amount(): void
    {
        $gold = Rank::create([
            'name' => 'Gold', 'level' => 1, 'min_sales_volume' => 0, 'min_downline' => 0, 'commission_multiplier' => 1.5,
        ]);

        $root = $this->root();
        $sponsor = $this->tree->placeNewUser(User::factory()->make(['rank_id' => $gold->id]), $root, 'unilevel');
        $buyer = $this->tree->placeNewUser(User::factory()->make(), $sponsor, 'unilevel');

        $this->completedOrder($buyer, 100);

        $commission = Commission::where('user_id', $sponsor->id)->first();
        $this->assertEquals(15.00, $commission->amount); // 100 * 10% * 1.5x
    }

    public function test_per_period_cap_limits_total_payout_to_an_upline(): void
    {
        CommissionConfiguration::where('plan_type', 'unilevel')->where('level', 1)->update([
            'cap' => 12, 'settings' => ['cap_period' => 'monthly'],
        ]);

        $root = $this->root();
        $sponsor = $this->tree->placeNewUser(User::factory()->make(), $root, 'unilevel');
        $buyer = $this->tree->placeNewUser(User::factory()->make(), $sponsor, 'unilevel');

        // First order pays $10 (10% of $100), within the $12 cap.
        $this->completedOrder($buyer, 100);
        // Second order would pay another $10, but only $2 of cap remains.
        $this->completedOrder($buyer, 100);

        $total = Commission::where('user_id', $sponsor->id)->sum('amount');
        $this->assertEquals(12.00, $total);
    }

    public function test_commission_credits_the_uplines_wallet_and_creates_a_transaction(): void
    {
        $root = $this->root();
        $buyer = $this->tree->placeNewUser(User::factory()->make(), $root, 'unilevel');

        $this->completedOrder($buyer, 200);

        $wallet = Wallet::where('user_id', $root->id)->first();
        $this->assertNotNull($wallet);
        $this->assertEquals(20.00, $wallet->balance); // 200 * 10%

        $this->assertDatabaseHas('wallet_transactions', [
            'user_id' => $root->id,
            'type' => 'credit',
            'transaction_type' => 'commission',
            'amount' => 20.00,
        ]);
    }

    public function test_order_is_only_processed_once(): void
    {
        $root = $this->root();
        $buyer = $this->tree->placeNewUser(User::factory()->make(), $root, 'unilevel');

        $order = $this->completedOrder($buyer, 100);
        $this->commissions->calculateForOrder($order);
        $secondRun = $this->commissions->calculateForOrder($order->refresh());

        $this->assertCount(0, $secondRun);
        $this->assertSame(1, Commission::where('user_id', $root->id)->count());
    }
}
