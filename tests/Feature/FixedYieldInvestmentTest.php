<?php

namespace Tests\Feature;

use App\Models\Commission;
use App\Models\FixedYieldDailyAccrual;
use App\Models\FixedYieldInvestment;
use App\Models\Rank;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\Wallet;
use App\Modules\Income\FixedYieldInvestment\FixedYieldInvestmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

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

    public function test_disabled_flag_pays_nothing(): void
    {
        SystemSetting::set('fixed_yield_investment_enabled', 'false');

        $rank = $this->makeRank('Bronze', 30);
        $user = User::factory()->create(['rank_id' => $rank->id]);
        FixedYieldInvestment::create(['user_id' => $user->id, 'invested_amount' => 1000, 'invested_at' => now(), 'status' => 'active']);

        $result = $this->service->runDaily();

        $this->assertCount(0, $result);
        $this->assertSame(0, FixedYieldDailyAccrual::count());
    }

    public function test_zero_rank_rate_pays_nothing(): void
    {
        SystemSetting::set('fixed_yield_investment_enabled', 'true');

        $rank = $this->makeRank('No Yield', 0);
        $user = User::factory()->create(['rank_id' => $rank->id]);
        FixedYieldInvestment::create(['user_id' => $user->id, 'invested_amount' => 1000, 'invested_at' => now(), 'status' => 'active']);

        $result = $this->service->runDaily();

        $this->assertCount(0, $result);
    }

    public function test_pays_daily_cash_from_the_rank_rate_and_invested_amount(): void
    {
        SystemSetting::set('fixed_yield_investment_enabled', 'true');
        SystemSetting::set('fixed_yield_investment_cap_multiplier', '100'); // keep the cap out of the way

        $rank = $this->makeRank('Bronze', 30); // 30% / 30 = 1% per day
        $user = User::factory()->create(['rank_id' => $rank->id]);
        $investment = FixedYieldInvestment::create(['user_id' => $user->id, 'invested_amount' => 1000, 'invested_at' => now(), 'status' => 'active']);

        $result = $this->service->runDaily();

        $this->assertCount(1, $result);

        $accrual = FixedYieldDailyAccrual::where('investment_id', $investment->id)->first();
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

    public function test_rate_is_looked_up_dynamically_from_the_investors_current_rank(): void
    {
        SystemSetting::set('fixed_yield_investment_enabled', 'true');
        SystemSetting::set('fixed_yield_investment_cap_multiplier', '100');

        $bronze = $this->makeRank('Bronze', 30, level: 1); // 1%/day
        $gold = $this->makeRank('Gold', 60, level: 2); // 2%/day

        $user = User::factory()->create(['rank_id' => $bronze->id]);
        $investment = FixedYieldInvestment::create(['user_id' => $user->id, 'invested_amount' => 1000, 'invested_at' => now(), 'status' => 'active']);

        $this->service->runDaily();
        $day1 = FixedYieldDailyAccrual::where('investment_id', $investment->id)->sole();
        $this->assertEquals(10.00, $day1->amount); // bronze's 1%/day

        // Rank changes mid-investment — no snapshot was taken at
        // investment time, so the very next day should reflect it.
        $user->forceFill(['rank_id' => $gold->id])->save();
        $this->travelTo(now()->addDay());

        $this->service->runDaily();
        $day2 = FixedYieldDailyAccrual::where('investment_id', $investment->id)->where('id', '!=', $day1->id)->sole();
        $this->assertEquals(20.00, $day2->amount); // gold's 2%/day, applied immediately
    }

    public function test_running_twice_the_same_day_does_not_double_pay(): void
    {
        SystemSetting::set('fixed_yield_investment_enabled', 'true');
        SystemSetting::set('fixed_yield_investment_cap_multiplier', '100');

        $rank = $this->makeRank('Bronze', 30);
        $user = User::factory()->create(['rank_id' => $rank->id]);
        $investment = FixedYieldInvestment::create(['user_id' => $user->id, 'invested_amount' => 1000, 'invested_at' => now(), 'status' => 'active']);

        $first = $this->service->runDaily();
        $second = $this->service->runDaily();

        $this->assertCount(1, $first);
        $this->assertCount(0, $second);
        $this->assertSame(1, FixedYieldDailyAccrual::where('investment_id', $investment->id)->count());
        $this->assertEquals(10.00, $user->fresh()->total_earnings);
    }

    public function test_cap_truncates_the_final_payout_and_marks_the_investment_capped_out(): void
    {
        SystemSetting::set('fixed_yield_investment_enabled', 'true');
        SystemSetting::set('fixed_yield_investment_cap_multiplier', '2'); // cap = 2x invested = 200

        $rank = $this->makeRank('Fast', 300); // 10%/day
        $user = User::factory()->create(['rank_id' => $rank->id]);
        $investment = FixedYieldInvestment::create(['user_id' => $user->id, 'invested_amount' => 100, 'invested_at' => now()->subDay(), 'status' => 'active']);

        // Simulate prior days already having paid out 196 of the 200 cap.
        FixedYieldDailyAccrual::create([
            'investment_id' => $investment->id, 'accrued_on' => now()->subDay()->toDateString(),
            'monthly_rate' => 300, 'base_amount' => 196, 'amount' => 196,
            'status' => FixedYieldDailyAccrual::STATUS_PAID,
        ]);

        $this->service->runDaily(); // base_amount would be 100*10%=10.00, only 4.00 of cap room remains

        $today = FixedYieldDailyAccrual::where('investment_id', $investment->id)->whereDate('accrued_on', now()->toDateString())->sole();
        $this->assertEquals(10.00, $today->base_amount);
        $this->assertEquals(4.00, $today->amount); // truncated to the remaining cap room

        $this->assertSame(FixedYieldInvestment::STATUS_CAPPED_OUT, $investment->fresh()->status);

        // Capped out: a further run (even a new day) pays nothing more.
        $this->travelTo(now()->addDay());
        $this->service->runDaily();
        $this->assertSame(2, FixedYieldDailyAccrual::where('investment_id', $investment->id)->count());
    }

    public function test_multiple_concurrent_investments_are_tracked_and_capped_independently(): void
    {
        SystemSetting::set('fixed_yield_investment_enabled', 'true');
        SystemSetting::set('fixed_yield_investment_cap_multiplier', '100');

        $rank = $this->makeRank('Bronze', 30); // 1%/day
        $user = User::factory()->create(['rank_id' => $rank->id]);

        $first = FixedYieldInvestment::create(['user_id' => $user->id, 'invested_amount' => 1000, 'invested_at' => now(), 'status' => 'active']);
        $second = FixedYieldInvestment::create(['user_id' => $user->id, 'invested_amount' => 500, 'invested_at' => now(), 'status' => 'active']);

        $result = $this->service->runDaily();

        $this->assertCount(2, $result);
        $this->assertEquals(10.00, FixedYieldDailyAccrual::where('investment_id', $first->id)->sole()->amount);
        $this->assertEquals(5.00, FixedYieldDailyAccrual::where('investment_id', $second->id)->sole()->amount);
        $this->assertEquals(15.00, $user->fresh()->total_earnings);
    }

    public function test_inactive_investments_are_skipped(): void
    {
        SystemSetting::set('fixed_yield_investment_enabled', 'true');

        $rank = $this->makeRank('Bronze', 30);
        $user = User::factory()->create(['rank_id' => $rank->id]);
        FixedYieldInvestment::create(['user_id' => $user->id, 'invested_amount' => 1000, 'invested_at' => now(), 'status' => FixedYieldInvestment::STATUS_CANCELLED]);
        FixedYieldInvestment::create(['user_id' => $user->id, 'invested_amount' => 1000, 'invested_at' => now(), 'status' => FixedYieldInvestment::STATUS_CAPPED_OUT]);

        $result = $this->service->runDaily();

        $this->assertCount(0, $result);
    }

    public function test_does_not_write_to_the_commissions_table(): void
    {
        SystemSetting::set('fixed_yield_investment_enabled', 'true');

        $rank = $this->makeRank('Bronze', 30);
        $user = User::factory()->create(['rank_id' => $rank->id]);
        FixedYieldInvestment::create(['user_id' => $user->id, 'invested_amount' => 1000, 'invested_at' => now(), 'status' => 'active']);

        $this->service->runDaily();

        $this->assertSame(0, Commission::count());
        $this->assertSame(1, FixedYieldDailyAccrual::count());
    }
}
