<?php

namespace Tests\Feature\Filament;

use App\Filament\Pages\PackageTierPlan;
use App\Models\Commission;
use App\Models\CommissionConfiguration;
use App\Models\SystemSetting;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PackageTierPlanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    }

    public function test_page_loads_prefilled_with_existing_tiers_in_order(): void
    {
        CommissionConfiguration::create([
            'plan_type' => Commission::TYPE_PACKAGE_TIER, 'level' => 1, 'percentage' => 10,
            'is_active' => true, 'settings' => ['qualifying_amount' => 500, 'cap_period' => 'monthly'],
        ]);
        CommissionConfiguration::create([
            'plan_type' => Commission::TYPE_PACKAGE_TIER, 'level' => 2, 'percentage' => 11,
            'cap' => 200, 'is_active' => true, 'settings' => ['qualifying_amount' => 1000, 'cap_period' => 'weekly'],
        ]);
        SystemSetting::set('package_tier_direct_reward_percentage', '7', 'commission', 'decimal');

        $component = Livewire::actingAs($this->admin())
            ->test(PackageTierPlan::class)
            ->assertFormSet(['package_tier_direct_reward_percentage' => 7]);

        // Repeater items are keyed by generated UUIDs, not sequential
        // indexes, so compare values only, in insertion order.
        $tiers = array_values($component->get('data.tiers'));

        $this->assertEquals(10.0, $tiers[0]['percentage']);
        $this->assertEquals(500.0, $tiers[0]['qualifying_amount']);
        $this->assertEquals(11.0, $tiers[1]['percentage']);
        $this->assertEquals(1000.0, $tiers[1]['qualifying_amount']);
        $this->assertEquals(200.0, $tiers[1]['cap']);
    }

    public function test_saving_creates_tiers_from_the_repeater_in_order(): void
    {
        Livewire::actingAs($this->admin())
            ->test(PackageTierPlan::class)
            ->fillForm([
                'package_tier_direct_reward_enabled' => true,
                'package_tier_direct_reward_percentage' => 5,
                'package_tier_condition_type' => 'own_package',
                'tiers' => [
                    ['percentage' => 10, 'qualifying_amount' => 500, 'cap' => null, 'cap_period' => 'monthly'],
                    ['percentage' => 11, 'qualifying_amount' => 1000, 'cap' => null, 'cap_period' => 'monthly'],
                ],
            ])
            ->call('save');

        $this->assertSame(2, CommissionConfiguration::where('plan_type', Commission::TYPE_PACKAGE_TIER)->count());

        $tier1 = CommissionConfiguration::where('plan_type', Commission::TYPE_PACKAGE_TIER)->where('level', 1)->first();
        $tier2 = CommissionConfiguration::where('plan_type', Commission::TYPE_PACKAGE_TIER)->where('level', 2)->first();

        $this->assertEquals(10, $tier1->percentage);
        $this->assertEquals(500, $tier1->settings['qualifying_amount']);
        $this->assertEquals(11, $tier2->percentage);
        $this->assertEquals(1000, $tier2->settings['qualifying_amount']);
    }

    public function test_saving_fewer_tiers_removes_the_extra_ones(): void
    {
        CommissionConfiguration::create([
            'plan_type' => Commission::TYPE_PACKAGE_TIER, 'level' => 1, 'percentage' => 10, 'is_active' => true, 'settings' => [],
        ]);
        CommissionConfiguration::create([
            'plan_type' => Commission::TYPE_PACKAGE_TIER, 'level' => 2, 'percentage' => 11, 'is_active' => true, 'settings' => [],
        ]);
        CommissionConfiguration::create([
            'plan_type' => Commission::TYPE_PACKAGE_TIER, 'level' => 3, 'percentage' => 12, 'is_active' => true, 'settings' => [],
        ]);

        Livewire::actingAs($this->admin())
            ->test(PackageTierPlan::class)
            ->fillForm([
                'tiers' => [
                    ['percentage' => 10, 'qualifying_amount' => 0, 'cap' => null, 'cap_period' => 'monthly'],
                ],
            ])
            ->call('save');

        $this->assertSame(1, CommissionConfiguration::where('plan_type', Commission::TYPE_PACKAGE_TIER)->count());
    }

    public function test_saving_does_not_touch_unilevel_binary_or_matrix_configurations(): void
    {
        CommissionConfiguration::create([
            'plan_type' => 'unilevel', 'level' => 1, 'percentage' => 10, 'is_active' => true, 'settings' => [],
        ]);

        Livewire::actingAs($this->admin())
            ->test(PackageTierPlan::class)
            ->fillForm(['tiers' => [['percentage' => 10, 'qualifying_amount' => 0, 'cap' => null, 'cap_period' => 'monthly']]])
            ->call('save');

        $this->assertSame(1, CommissionConfiguration::where('plan_type', 'unilevel')->count());
    }

    public function test_saving_persists_direct_reward_and_condition_settings(): void
    {
        Livewire::actingAs($this->admin())
            ->test(PackageTierPlan::class)
            ->fillForm([
                'package_tier_direct_reward_enabled' => false,
                'package_tier_direct_reward_percentage' => 8,
                'package_tier_condition_type' => 'team_volume',
                'tiers' => [],
            ])
            ->call('save');

        $this->assertSame('false', SystemSetting::get('package_tier_direct_reward_enabled'));
        $this->assertEquals(8, SystemSetting::get('package_tier_direct_reward_percentage'));
        $this->assertSame('team_volume', SystemSetting::get('package_tier_condition_type'));
    }
}
