<?php

namespace Tests\Feature;

use App\Models\Commission;
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
 * Sideline Growth Bonus is an OrderTriggeredIncomeModule, independent
 * of whichever PlanModule is active — it walks the buyer's ancestor
 * chain looking for the first upline who is NOT one of their own
 * parent's first two ("A/B leg") children, and pays only that one
 * upline. Tree shapes here are built via `placeNewUser(..., 'unilevel')`
 * for precise, deterministic control over parent/child structure,
 * except where the test is specifically about Matrix or Binary shape.
 */
class SidelineGrowthBonusTest extends TestCase
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
        SystemSetting::set('sideline_growth_bonus_enabled', 'true');
        SystemSetting::set('sideline_growth_bonus_percentage', '10');
    }

    private function root(): User
    {
        $root = User::factory()->create(['depth' => 0]);
        $this->tree->placeRoot($root);

        return $root;
    }

    private function packageOrder(User $buyer, float $amount = 100): Order
    {
        $product = Product::factory()->create([
            'price' => $amount,
            'commission_value' => $amount,
            'is_package' => true,
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

    private function nonPackageOrder(User $buyer, float $amount = 100): Order
    {
        $product = Product::factory()->create([
            'price' => $amount,
            'commission_value' => $amount,
            'is_package' => false,
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

    /**
     * Builds: Root - U1 - U2 - U3 - {A, B, U4} - U5 - Buyer, where U3's
     * three children are A (rank 1), B (rank 2), U4 (rank 3). Matches
     * the diagram walked through with the user during design.
     */
    private function diagramTree(): array
    {
        $root = $this->root();
        $u1 = $this->tree->placeNewUser(User::factory()->make(), $root, 'unilevel');
        $u2 = $this->tree->placeNewUser(User::factory()->make(), $u1, 'unilevel');
        $u3 = $this->tree->placeNewUser(User::factory()->make(), $u2, 'unilevel');
        $a = $this->tree->placeNewUser(User::factory()->make(), $u3, 'unilevel');
        $b = $this->tree->placeNewUser(User::factory()->make(), $u3, 'unilevel');
        $u4 = $this->tree->placeNewUser(User::factory()->make(), $u3, 'unilevel');
        $u5 = $this->tree->placeNewUser(User::factory()->make(), $u4, 'unilevel');
        $buyer = $this->tree->placeNewUser(User::factory()->make(), $u5, 'unilevel');

        return compact('root', 'u1', 'u2', 'u3', 'a', 'b', 'u4', 'u5', 'buyer');
    }

    public function test_pays_the_first_ancestor_beyond_the_ab_leg(): void
    {
        $tree = $this->diagramTree();

        $this->packageOrder($tree['buyer'], 100);

        $bonus = Commission::where('plan_type', Commission::TYPE_SIDELINE_GROWTH_BONUS)->first();

        $this->assertNotNull($bonus);
        $this->assertSame($tree['u4']->id, $bonus->user_id);
        $this->assertEquals(10.00, $bonus->amount); // 100 * 10%
        $this->assertSame(0, $bonus->level);
        $this->assertSame(1, Commission::where('plan_type', Commission::TYPE_SIDELINE_GROWTH_BONUS)->count());
    }

    public function test_can_be_disabled(): void
    {
        SystemSetting::set('sideline_growth_bonus_enabled', 'false');

        $tree = $this->diagramTree();
        $this->packageOrder($tree['buyer'], 100);

        $this->assertSame(0, Commission::where('plan_type', Commission::TYPE_SIDELINE_GROWTH_BONUS)->count());
    }

    public function test_does_not_pay_for_a_non_package_order(): void
    {
        $tree = $this->diagramTree();
        $this->nonPackageOrder($tree['buyer'], 100);

        $this->assertSame(0, Commission::where('plan_type', Commission::TYPE_SIDELINE_GROWTH_BONUS)->count());
    }

    public function test_direct_sponsor_can_qualify_immediately(): void
    {
        // Root's children: X1 (rank 1), X2 (rank 2), X3 (rank 3) — the
        // buyer's direct sponsor, X3, already qualifies on its own, so
        // the walk stops after a single hop rather than needing several.
        $root = $this->root();
        $x1 = $this->tree->placeNewUser(User::factory()->make(), $root, 'unilevel');
        $this->tree->placeNewUser(User::factory()->make(), $root, 'unilevel'); // X2
        $x3 = $this->tree->placeNewUser(User::factory()->make(), $root, 'unilevel');
        $buyer = $this->tree->placeNewUser(User::factory()->make(), $x3, 'unilevel');

        $this->packageOrder($buyer, 100);

        $bonus = Commission::where('plan_type', Commission::TYPE_SIDELINE_GROWTH_BONUS)->first();
        $this->assertNotNull($bonus);
        $this->assertSame($x3->id, $bonus->user_id);
        $this->assertNotSame($x1->id, $bonus->user_id);
    }

    public function test_nobody_is_paid_when_every_ancestor_is_an_ab_leg_position(): void
    {
        // Same shape as diagramTree() but with U4 removed and U5 placed
        // under B instead — every ancestor up to Root is now a rank
        // 1 or 2 child, so there's no sideline position anywhere in
        // the chain.
        $root = $this->root();
        $u1 = $this->tree->placeNewUser(User::factory()->make(), $root, 'unilevel');
        $u2 = $this->tree->placeNewUser(User::factory()->make(), $u1, 'unilevel');
        $u3 = $this->tree->placeNewUser(User::factory()->make(), $u2, 'unilevel');
        $this->tree->placeNewUser(User::factory()->make(), $u3, 'unilevel'); // A
        $b = $this->tree->placeNewUser(User::factory()->make(), $u3, 'unilevel');
        $u5 = $this->tree->placeNewUser(User::factory()->make(), $b, 'unilevel');
        $buyer = $this->tree->placeNewUser(User::factory()->make(), $u5, 'unilevel');

        $this->packageOrder($buyer, 100);

        $this->assertSame(0, Commission::where('plan_type', Commission::TYPE_SIDELINE_GROWTH_BONUS)->count());
    }

    public function test_inactive_qualifying_ancestor_means_nobody_is_paid(): void
    {
        // The walk never cascades past the first qualifying position —
        // if U4 (rank 3 under U3) can't be paid, nothing pays out at
        // all, even though U3/U2/U1/Root are still further up the chain.
        $tree = $this->diagramTree();
        $tree['u4']->forceFill(['status' => User::STATUS_SUSPENDED])->save();

        $this->packageOrder($tree['buyer'], 100);

        $this->assertSame(0, Commission::where('plan_type', Commission::TYPE_SIDELINE_GROWTH_BONUS)->count());
    }

    public function test_never_fires_under_a_pure_binary_tree(): void
    {
        // Binary only ever fills exactly 2 slots (left/right) per
        // parent, so no descendant is ever anyone's 3rd child — the
        // bonus should structurally never find a qualifying ancestor.
        SystemSetting::set('active_plan_type', 'binary');

        $root = $this->root();
        $left = $this->tree->placeNewUser(User::factory()->make(), $root, 'binary');
        $this->tree->placeNewUser(User::factory()->make(), $root, 'binary'); // right
        $spillover = $this->tree->placeNewUser(User::factory()->make(), $root, 'binary');
        $deepBuyer = $this->tree->placeNewUser(User::factory()->make(), $spillover, 'binary');

        $this->packageOrder($left, 100);
        $this->packageOrder($deepBuyer, 100);

        $this->assertSame(0, Commission::where('plan_type', Commission::TYPE_SIDELINE_GROWTH_BONUS)->count());
    }

    public function test_fires_under_matrix_once_width_allows_a_third_child(): void
    {
        SystemSetting::set('active_plan_type', 'matrix');
        SystemSetting::set('matrix_width', '3');

        $root = $this->root();
        $this->tree->placeNewUser(User::factory()->make(), $root, 'matrix'); // slot 1
        $this->tree->placeNewUser(User::factory()->make(), $root, 'matrix'); // slot 2
        $c3 = $this->tree->placeNewUser(User::factory()->make(), $root, 'matrix'); // slot 3 — sideline
        $buyer = $this->tree->placeNewUser(User::factory()->make(), $c3, 'matrix');

        $this->packageOrder($buyer, 100);

        $bonus = Commission::where('plan_type', Commission::TYPE_SIDELINE_GROWTH_BONUS)->first();
        $this->assertNotNull($bonus);
        $this->assertSame($c3->id, $bonus->user_id);
    }

    public function test_rank_multiplier_scales_the_bonus(): void
    {
        $gold = Rank::create([
            'name' => 'Gold', 'level' => 1, 'min_sales_volume' => 0, 'min_downline' => 0, 'commission_multiplier' => 1.5,
        ]);

        $root = $this->root();
        $u1 = $this->tree->placeNewUser(User::factory()->make(), $root, 'unilevel');
        $u2 = $this->tree->placeNewUser(User::factory()->make(), $u1, 'unilevel');
        $u3 = $this->tree->placeNewUser(User::factory()->make(), $u2, 'unilevel');
        $this->tree->placeNewUser(User::factory()->make(), $u3, 'unilevel'); // A
        $this->tree->placeNewUser(User::factory()->make(), $u3, 'unilevel'); // B
        $u4 = $this->tree->placeNewUser(User::factory()->make(['rank_id' => $gold->id]), $u3, 'unilevel');
        $buyer = $this->tree->placeNewUser(User::factory()->make(), $u4, 'unilevel');

        $this->packageOrder($buyer, 100);

        $this->assertEquals(15.00, Commission::where('plan_type', Commission::TYPE_SIDELINE_GROWTH_BONUS)->value('amount')); // 100*10%*1.5
    }
}
