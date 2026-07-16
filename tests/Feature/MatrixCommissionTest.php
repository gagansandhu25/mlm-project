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

class MatrixCommissionTest extends TestCase
{
    use RefreshDatabase;

    private TreeService $tree;

    private CommissionService $commissions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tree = app(TreeService::class);
        $this->commissions = app(CommissionService::class);

        SystemSetting::set('active_plan_type', 'matrix');
        SystemSetting::set('matrix_width', '3');

        CommissionConfiguration::create(['plan_type' => 'matrix', 'level' => 1, 'percentage' => 10, 'is_active' => true]);
        CommissionConfiguration::create(['plan_type' => 'matrix', 'level' => 2, 'percentage' => 5, 'is_active' => true]);
        CommissionConfiguration::create(['plan_type' => 'matrix', 'level' => 3, 'percentage' => 3, 'is_active' => true]);
    }

    private function root(): User
    {
        $root = User::factory()->create(['depth' => 0]);
        $root->path = (string) $root->id;
        $root->save();

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
        $level1 = $this->tree->placeNewUser(User::factory()->make(), $root, 'matrix');
        $level2Buyer = $this->tree->placeNewUser(User::factory()->make(), $level1, 'matrix');

        $this->completedOrder($level2Buyer, 100);

        $this->assertSame(2, Commission::count());

        $level1Commission = Commission::where('user_id', $level1->id)->first();
        $rootCommission = Commission::where('user_id', $root->id)->first();

        $this->assertEquals(10.00, $level1Commission->amount);
        $this->assertSame('matrix', $level1Commission->plan_type);
        $this->assertEquals(5.00, $rootCommission->amount);
    }

    public function test_matrix_width_caps_direct_children_and_spills_over(): void
    {
        $root = $this->root();

        // Width is 3: the first 3 recruits go directly under root, the 4th spills to the first child.
        $children = [];
        for ($i = 0; $i < 4; $i++) {
            $children[] = $this->tree->placeNewUser(User::factory()->make(), $root, 'matrix');
        }

        $this->assertSame($root->id, $children[0]->parent_id);
        $this->assertSame($root->id, $children[1]->parent_id);
        $this->assertSame($root->id, $children[2]->parent_id);
        $this->assertSame($children[0]->id, $children[3]->parent_id);
    }

    public function test_inactive_upline_does_not_earn_commission(): void
    {
        $root = $this->root();
        $sponsor = $this->tree->placeNewUser(User::factory()->make(['status' => User::STATUS_SUSPENDED]), $root, 'matrix');
        $buyer = $this->tree->placeNewUser(User::factory()->make(), $sponsor, 'matrix');

        $this->completedOrder($buyer, 100);

        $this->assertFalse(Commission::where('user_id', $sponsor->id)->exists());
        $this->assertSame(1, Commission::count());
    }

    public function test_rank_multiplier_scales_the_commission_amount(): void
    {
        $gold = Rank::create([
            'name' => 'Gold', 'level' => 1, 'min_sales_volume' => 0, 'min_downline' => 0, 'commission_multiplier' => 1.5,
        ]);

        $root = $this->root();
        $sponsor = $this->tree->placeNewUser(User::factory()->make(['rank_id' => $gold->id]), $root, 'matrix');
        $buyer = $this->tree->placeNewUser(User::factory()->make(), $sponsor, 'matrix');

        $this->completedOrder($buyer, 100);

        $commission = Commission::where('user_id', $sponsor->id)->first();
        $this->assertEquals(15.00, $commission->amount);
    }

    public function test_commission_credits_the_uplines_wallet(): void
    {
        $root = $this->root();
        $buyer = $this->tree->placeNewUser(User::factory()->make(), $root, 'matrix');

        $this->completedOrder($buyer, 200);

        $wallet = Wallet::where('user_id', $root->id)->first();
        $this->assertNotNull($wallet);
        $this->assertEquals(20.00, $wallet->balance);
    }
}
