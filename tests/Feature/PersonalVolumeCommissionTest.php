<?php

namespace Tests\Feature;

use App\Models\Commission;
use App\Models\PersonalVolumeAccrual;
use App\Models\Rank;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\Wallet;
use App\Services\Commission\PersonalVolumeCommissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PersonalVolumeCommissionTest extends TestCase
{
    use RefreshDatabase;

    private PersonalVolumeCommissionService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(PersonalVolumeCommissionService::class);
    }

    public function test_disabled_flag_pays_nothing(): void
    {
        SystemSetting::set('personal_volume_commission_enabled', 'false');
        SystemSetting::set('personal_volume_percentage', '2');

        User::factory()->create(['sales_volume' => 100]);

        $result = $this->service->runDaily();

        $this->assertCount(0, $result);
        $this->assertSame(0, PersonalVolumeAccrual::count());
    }

    public function test_zero_percentage_pays_nothing(): void
    {
        SystemSetting::set('personal_volume_commission_enabled', 'true');
        SystemSetting::set('personal_volume_percentage', '0');

        User::factory()->create(['sales_volume' => 100]);

        $result = $this->service->runDaily();

        $this->assertCount(0, $result);
    }

    public function test_pays_a_percentage_of_cumulative_sales_volume(): void
    {
        SystemSetting::set('personal_volume_commission_enabled', 'true');
        SystemSetting::set('personal_volume_percentage', '2');

        $user = User::factory()->create(['sales_volume' => 100]);

        $result = $this->service->runDaily();

        $this->assertCount(1, $result);

        $accrual = PersonalVolumeAccrual::where('user_id', $user->id)->first();
        $this->assertNotNull($accrual);
        $this->assertEquals(100.00, $accrual->sales_volume_snapshot);
        $this->assertEquals(2.00, $accrual->base_amount); // 100 * 2%
        $this->assertEquals(2.00, $accrual->amount); // no rank multiplier
        $this->assertSame(PersonalVolumeAccrual::STATUS_PENDING, $accrual->status);
        $this->assertSame(now()->toDateString(), $accrual->accrued_on->toDateString());

        $wallet = Wallet::where('user_id', $user->id)->first();
        $this->assertNotNull($wallet);
        $this->assertEquals(2.00, $wallet->balance);

        $this->assertEquals(2.00, $user->fresh()->total_earnings);
    }

    public function test_inactive_users_are_skipped(): void
    {
        SystemSetting::set('personal_volume_commission_enabled', 'true');
        SystemSetting::set('personal_volume_percentage', '2');

        User::factory()->create(['sales_volume' => 100, 'status' => User::STATUS_SUSPENDED]);

        $result = $this->service->runDaily();

        $this->assertCount(0, $result);
    }

    public function test_users_with_no_volume_are_skipped(): void
    {
        SystemSetting::set('personal_volume_commission_enabled', 'true');
        SystemSetting::set('personal_volume_percentage', '2');

        User::factory()->create(['sales_volume' => 0]);

        $result = $this->service->runDaily();

        $this->assertCount(0, $result);
    }

    public function test_rank_multiplier_scales_the_amount(): void
    {
        SystemSetting::set('personal_volume_commission_enabled', 'true');
        SystemSetting::set('personal_volume_percentage', '2');

        $gold = Rank::create([
            'name' => 'Gold', 'level' => 1, 'min_sales_volume' => 0, 'min_downline' => 0, 'commission_multiplier' => 1.5,
        ]);

        $user = User::factory()->create(['sales_volume' => 100, 'rank_id' => $gold->id]);

        $this->service->runDaily();

        $accrual = PersonalVolumeAccrual::where('user_id', $user->id)->first();
        $this->assertEquals(2.00, $accrual->base_amount); // 100 * 2%
        $this->assertEquals(3.00, $accrual->amount); // 2.00 * 1.5x
    }

    public function test_running_twice_the_same_day_does_not_double_pay(): void
    {
        SystemSetting::set('personal_volume_commission_enabled', 'true');
        SystemSetting::set('personal_volume_percentage', '2');

        $user = User::factory()->create(['sales_volume' => 100]);

        $first = $this->service->runDaily();
        $second = $this->service->runDaily();

        $this->assertCount(1, $first);
        $this->assertCount(0, $second);
        $this->assertSame(1, PersonalVolumeAccrual::where('user_id', $user->id)->count());
        $this->assertEquals(2.00, $user->fresh()->total_earnings);
    }

    public function test_does_not_write_to_the_commissions_table(): void
    {
        SystemSetting::set('personal_volume_commission_enabled', 'true');
        SystemSetting::set('personal_volume_percentage', '2');

        User::factory()->create(['sales_volume' => 100]);

        $this->service->runDaily();

        $this->assertSame(0, Commission::count());
        $this->assertSame(1, PersonalVolumeAccrual::count());
    }
}
