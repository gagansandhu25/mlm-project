<?php

namespace Tests\Feature;

use App\Models\Commission;
use App\Models\FixedYieldDailyAccrual;
use App\Models\Order;
use App\Models\Product;
use App\Models\Rank;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\Wallet;
use App\Modules\Income\FixedYieldInvestment\FixedYieldInvestmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The yield principal is a completed, is_package Order — there's no
 * separate "investment" record anymore, so every test creates real
 * orders via completedPackageOrder() rather than a FixedYieldInvestment
 * row.
 */
class FixedYieldInvestmentTest extends TestCase
{
    use RefreshDatabase;

    private FixedYieldInvestmentService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(FixedYieldInvestmentService::class);
    }

    /**
     * min_sales_volume set unreachably high so RankService::evaluate()
     * (called at the end of every payout) never auto-promotes the test
     * user into this rank on its own — tests that need to control rank
     * assignment manually assign rank_id directly instead.
     */
    private function makeRank(string $name, float $monthlyRate, int $level = 1): Rank
    {
        return Rank::create([
            'name' => $name, 'level' => $level,
            'min_sales_volume' => 999999, 'min_downline' => 0,
            'rank_commission_rate' => $monthlyRate,
        ]);
    }

    private function completedPackageOrder(User $buyer, float $amount, bool $isPackage = true, string $status = Order::STATUS_COMPLETED): Order
    {
        $product = Product::factory()->create(['price' => $amount, 'commission_value' => $amount, 'is_package' => $isPackage]);

        return Order::create([
            'user_id' => $buyer->id,
            'product_id' => $product->id,
            'order_number' => 'ORD-'.uniqid(),
            'amount' => $amount,
            'commission_value' => $amount,
            'status' => $status,
            'order_date' => now(),
            'payment_status' => 'paid',
        ]);
    }

    public function test_disabled_flag_pays_nothing(): void
    {
        SystemSetting::set('fixed_yield_investment_enabled', 'false');

        $rank = $this->makeRank('Bronze', 30);
        $user = User::factory()->create(['rank_id' => $rank->id]);
        $this->completedPackageOrder($user, 1000);

        $result = $this->service->runDaily();

        $this->assertCount(0, $result);
        $this->assertSame(0, FixedYieldDailyAccrual::count());
    }

    public function test_zero_rank_rate_pays_nothing(): void
    {
        SystemSetting::set('fixed_yield_investment_enabled', 'true');

        $rank = $this->makeRank('No Yield', 0);
        $user = User::factory()->create(['rank_id' => $rank->id]);
        $this->completedPackageOrder($user, 1000);

        $result = $this->service->runDaily();

        $this->assertCount(0, $result);
    }

    public function test_pays_daily_cash_from_the_rank_rate_and_the_orders_own_amount(): void
    {
        SystemSetting::set('fixed_yield_investment_enabled', 'true');
        SystemSetting::set('fixed_yield_investment_cap_multiplier', '100'); // keep the cap out of the way

        $rank = $this->makeRank('Bronze', 30); // 30% / 30 = 1% per day
        $user = User::factory()->create(['rank_id' => $rank->id]);
        $order = $this->completedPackageOrder($user, 1000);

        $result = $this->service->runDaily();

        $this->assertCount(1, $result);

        $accrual = FixedYieldDailyAccrual::where('order_id', $order->id)->first();
        $this->assertNotNull($accrual);
        $this->assertEquals(30.00, $accrual->monthly_rate);
        $this->assertEquals(10.00, $accrual->base_amount); // 1000 * 1%
        $this->assertEquals(10.00, $accrual->amount);
        $this->assertSame(FixedYieldDailyAccrual::STATUS_PENDING, $accrual->status);
        $this->assertSame(now()->toDateString(), $accrual->accrued_on->toDateString());

        $wallet = Wallet::where('user_id', $user->id)->first();
        $this->assertEquals(10.00, $wallet->balance);
        $this->assertEquals(10.00, $user->fresh()->total_earnings);
    }

    public function test_rate_is_looked_up_dynamically_from_the_buyers_current_rank(): void
    {
        SystemSetting::set('fixed_yield_investment_enabled', 'true');
        SystemSetting::set('fixed_yield_investment_cap_multiplier', '100');

        $bronze = $this->makeRank('Bronze', 30, level: 1); // 1%/day
        $gold = $this->makeRank('Gold', 60, level: 2); // 2%/day

        $user = User::factory()->create(['rank_id' => $bronze->id]);
        $order = $this->completedPackageOrder($user, 1000);

        $this->service->runDaily();
        $day1 = FixedYieldDailyAccrual::where('order_id', $order->id)->sole();
        $this->assertEquals(10.00, $day1->amount); // bronze's 1%/day

        // Rank changes mid-stream — no snapshot was taken at purchase
        // time, so the very next day should reflect it.
        $user->forceFill(['rank_id' => $gold->id])->save();
        $this->travelTo(now()->addDay());

        $this->service->runDaily();
        $day2 = FixedYieldDailyAccrual::where('order_id', $order->id)->where('id', '!=', $day1->id)->sole();
        $this->assertEquals(20.00, $day2->amount); // gold's 2%/day, applied immediately
    }

    public function test_running_twice_the_same_day_does_not_double_pay(): void
    {
        SystemSetting::set('fixed_yield_investment_enabled', 'true');
        SystemSetting::set('fixed_yield_investment_cap_multiplier', '100');

        $rank = $this->makeRank('Bronze', 30);
        $user = User::factory()->create(['rank_id' => $rank->id]);
        $order = $this->completedPackageOrder($user, 1000);

        $first = $this->service->runDaily();
        $second = $this->service->runDaily();

        $this->assertCount(1, $first);
        $this->assertCount(0, $second);
        $this->assertSame(1, FixedYieldDailyAccrual::where('order_id', $order->id)->count());
        $this->assertEquals(10.00, $user->fresh()->total_earnings);
    }

    public function test_cap_truncates_the_final_payout_and_further_runs_pay_nothing_more(): void
    {
        SystemSetting::set('fixed_yield_investment_enabled', 'true');
        SystemSetting::set('fixed_yield_investment_cap_multiplier', '2'); // cap = 2x order amount = 200

        $rank = $this->makeRank('Fast', 300); // 10%/day
        $user = User::factory()->create(['rank_id' => $rank->id]);
        $order = $this->completedPackageOrder($user, 100);

        // Simulate a prior day having already paid out 196 of the 200 cap.
        FixedYieldDailyAccrual::create([
            'order_id' => $order->id, 'accrued_on' => now()->subDay()->toDateString(),
            'monthly_rate' => 300, 'base_amount' => 196, 'amount' => 196,
            'status' => FixedYieldDailyAccrual::STATUS_PAID,
        ]);

        $this->service->runDaily(); // base_amount would be 100*10%=10.00, only 4.00 of cap room remains

        $today = FixedYieldDailyAccrual::where('order_id', $order->id)->whereDate('accrued_on', now()->toDateString())->sole();
        $this->assertEquals(10.00, $today->base_amount);
        $this->assertEquals(4.00, $today->amount); // truncated to the remaining cap room

        // Fully capped out: no persisted status anywhere — a further run
        // (even a new day) simply recomputes zero room and pays nothing more.
        $this->travelTo(now()->addDay());
        $this->service->runDaily();
        $this->assertSame(2, FixedYieldDailyAccrual::where('order_id', $order->id)->count());
    }

    public function test_multiple_concurrent_package_orders_are_tracked_and_capped_independently(): void
    {
        SystemSetting::set('fixed_yield_investment_enabled', 'true');
        SystemSetting::set('fixed_yield_investment_cap_multiplier', '100');

        $rank = $this->makeRank('Bronze', 30); // 1%/day
        $user = User::factory()->create(['rank_id' => $rank->id]);

        $first = $this->completedPackageOrder($user, 1000);
        $second = $this->completedPackageOrder($user, 500);

        $result = $this->service->runDaily();

        $this->assertCount(2, $result);
        $this->assertEquals(10.00, FixedYieldDailyAccrual::where('order_id', $first->id)->sole()->amount);
        $this->assertEquals(5.00, FixedYieldDailyAccrual::where('order_id', $second->id)->sole()->amount);
        $this->assertEquals(15.00, $user->fresh()->total_earnings);
    }

    public function test_non_qualifying_orders_are_skipped(): void
    {
        SystemSetting::set('fixed_yield_investment_enabled', 'true');

        $rank = $this->makeRank('Bronze', 30);
        $user = User::factory()->create(['rank_id' => $rank->id]);

        $this->completedPackageOrder($user, 1000, isPackage: true, status: Order::STATUS_CANCELLED);
        $this->completedPackageOrder($user, 1000, isPackage: true, status: Order::STATUS_REFUNDED);
        $this->completedPackageOrder($user, 1000, isPackage: false, status: Order::STATUS_COMPLETED); // not a package

        $result = $this->service->runDaily();

        $this->assertCount(0, $result);
    }

    public function test_does_not_write_to_the_commissions_table(): void
    {
        SystemSetting::set('fixed_yield_investment_enabled', 'true');

        $rank = $this->makeRank('Bronze', 30);
        $user = User::factory()->create(['rank_id' => $rank->id]);
        $this->completedPackageOrder($user, 1000);

        $this->service->runDaily();

        $this->assertSame(0, Commission::count());
        $this->assertSame(1, FixedYieldDailyAccrual::count());
    }
}
