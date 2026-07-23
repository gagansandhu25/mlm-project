<?php

namespace Tests\Feature\Filament;

use App\Filament\Pages\Settings;
use App\Models\Commission;
use App\Models\CommissionConfiguration;
use App\Models\SystemSetting;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SettingsPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        SystemSetting::set('company_name', 'Acme Direct Selling', 'general', 'string');
        SystemSetting::set('support_email', 'support@acme.test', 'general', 'string');
        SystemSetting::set('active_plan_type', 'binary', 'commission', 'string');
        SystemSetting::set('matrix_width', '3', 'commission', 'integer');
        SystemSetting::set('binary_pair_percentage', '10', 'commission', 'integer');
        SystemSetting::set('personal_volume_commission_enabled', 'true', 'commission', 'boolean');
        SystemSetting::set('personal_volume_percentage', '2', 'commission', 'decimal');
        SystemSetting::set('multi_tier_referral_bonus_enabled', 'true', 'commission', 'boolean');
        SystemSetting::set('multi_tier_referral_bonus_condition_type', 'own_package', 'commission', 'string');
        SystemSetting::set('minimum_payout_threshold', '50', 'payout', 'decimal');
        SystemSetting::set('withdrawal_fee_percentage', '2', 'payout', 'decimal');
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    }

    public function test_page_loads_prefilled_with_current_settings(): void
    {
        Livewire::actingAs($this->admin())
            ->test(Settings::class)
            ->assertFormSet([
                'company_name' => 'Acme Direct Selling',
                'support_email' => 'support@acme.test',
                'active_plan_type' => 'binary',
                'binary_pair_percentage' => 10,
                'income_enabled_personal_volume' => true,
                'personal_volume_percentage' => 2,
                'income_enabled_multi_tier_referral_bonus' => true,
                'multi_tier_referral_bonus_condition_type' => 'own_package',
                'minimum_payout_threshold' => 50,
                'withdrawal_fee_percentage' => 2,
            ]);
    }

    public function test_saving_updates_the_underlying_settings(): void
    {
        Livewire::actingAs($this->admin())
            ->test(Settings::class)
            ->fillForm([
                'company_name' => 'New Name Inc',
                'minimum_payout_threshold' => 75,
            ])
            ->call('save');

        $this->assertSame('New Name Inc', SystemSetting::get('company_name'));
        $this->assertEquals(75, SystemSetting::get('minimum_payout_threshold'));
    }

    public function test_active_plan_type_is_editable(): void
    {
        Livewire::actingAs($this->admin())
            ->test(Settings::class)
            ->assertFormFieldIsEnabled('active_plan_type');
    }

    /**
     * Regression test: matrix_width is only visible() when the active
     * plan is matrix, so on a binary install it's hidden for the
     * entire lifetime of the form (active_plan_type is locked, so it
     * never becomes visible mid-session). A hidden field is excluded
     * from getState() by default, so without dehydratedWhenHidden()
     * on it, *every single save* — regardless of what the admin
     * actually changed — silently wiped it back to null/empty. This
     * happened for real against a live database before being caught.
     */
    public function test_saving_does_not_null_out_the_hidden_matrix_width(): void
    {
        Livewire::actingAs($this->admin())
            ->test(Settings::class)
            ->fillForm(['company_name' => 'Unrelated Change'])
            ->call('save');

        $this->assertSame('3', SystemSetting::get('matrix_width'));
    }

    public function test_saving_does_not_null_out_binary_pair_percentage_when_disabling_personal_volume(): void
    {
        // personal_volume_percentage is only visible while the toggle
        // is on — same dehydration hazard as matrix_width above, just
        // triggered by a field the admin *can* actually toggle.
        Livewire::actingAs($this->admin())
            ->test(Settings::class)
            ->fillForm(['income_enabled_personal_volume' => false])
            ->call('save');

        $this->assertSame('false', SystemSetting::get('personal_volume_commission_enabled'));
        $this->assertEquals(2, SystemSetting::get('personal_volume_percentage'));
    }

    public function test_bonuses_section_prefills_multi_tier_referral_bonus_tiers_in_order(): void
    {
        CommissionConfiguration::create([
            'plan_type' => Commission::TYPE_MULTI_TIER_REFERRAL_BONUS, 'level' => 1, 'percentage' => 10,
            'is_active' => true, 'settings' => ['qualifying_amount' => 500, 'cap_period' => 'monthly'],
        ]);
        CommissionConfiguration::create([
            'plan_type' => Commission::TYPE_MULTI_TIER_REFERRAL_BONUS, 'level' => 2, 'percentage' => 11,
            'cap' => 200, 'is_active' => true, 'settings' => ['qualifying_amount' => 1000, 'cap_period' => 'weekly'],
        ]);

        $component = Livewire::actingAs($this->admin())
            ->test(Settings::class);

        // Repeater items are keyed by generated UUIDs, not sequential
        // indexes, so compare values only, in insertion order.
        $tiers = array_values($component->get('data.multi_tier_referral_bonus_tiers'));

        $this->assertEquals(10.0, $tiers[0]['percentage']);
        $this->assertEquals(500.0, $tiers[0]['qualifying_amount']);
        $this->assertEquals(11.0, $tiers[1]['percentage']);
        $this->assertEquals(1000.0, $tiers[1]['qualifying_amount']);
        $this->assertEquals(200.0, $tiers[1]['cap']);
    }

    public function test_saving_persists_multi_tier_referral_bonus_condition_and_tiers(): void
    {
        Livewire::actingAs($this->admin())
            ->test(Settings::class)
            ->fillForm([
                'multi_tier_referral_bonus_condition_type' => 'team_volume',
                'multi_tier_referral_bonus_tiers' => [
                    ['percentage' => 10, 'qualifying_amount' => 500, 'cap' => null, 'cap_period' => 'monthly'],
                    ['percentage' => 11, 'qualifying_amount' => 1000, 'cap' => null, 'cap_period' => 'monthly'],
                ],
            ])
            ->call('save');

        $this->assertSame('team_volume', SystemSetting::get('multi_tier_referral_bonus_condition_type'));
        $this->assertSame(2, CommissionConfiguration::where('plan_type', Commission::TYPE_MULTI_TIER_REFERRAL_BONUS)->count());

        $tier1 = CommissionConfiguration::where('plan_type', Commission::TYPE_MULTI_TIER_REFERRAL_BONUS)->where('level', 1)->first();
        $this->assertEquals(10, $tier1->percentage);
        $this->assertEquals(500, $tier1->settings['qualifying_amount']);
    }

    public function test_saving_does_not_null_out_tiers_when_disabling_personal_volume(): void
    {
        // multi_tier_referral_bonus_tiers is only visible while its own
        // toggle is on — same dehydration hazard as matrix_width above,
        // just triggered here by an unrelated bonus's toggle to prove
        // one bonus's fields never get caught up in another's visibility.
        CommissionConfiguration::create([
            'plan_type' => Commission::TYPE_MULTI_TIER_REFERRAL_BONUS, 'level' => 1, 'percentage' => 10,
            'is_active' => true, 'settings' => ['qualifying_amount' => 500],
        ]);

        Livewire::actingAs($this->admin())
            ->test(Settings::class)
            ->fillForm(['income_enabled_personal_volume' => false])
            ->call('save');

        $this->assertSame(1, CommissionConfiguration::where('plan_type', Commission::TYPE_MULTI_TIER_REFERRAL_BONUS)->count());
        $this->assertSame('true', SystemSetting::get('multi_tier_referral_bonus_enabled'));
    }

    public function test_hybrid_binary_matching_defaults_to_the_specced_pool_levels_when_unconfigured(): void
    {
        $component = Livewire::actingAs($this->admin())
            ->test(Settings::class);

        // Repeater::simple() only flattens items to raw scalars at
        // save()-time dehydration; the live component state Livewire
        // testing reads here still holds each item wrapped under the
        // sub-field's own name, same as a non-simple repeater would.
        $levels = array_values($component->get('data.hybrid_binary_matching_pool_levels'));

        $this->assertEquals([33, 27, 20, 13, 7], array_column($levels, 'percentage'));
    }

    public function test_saving_hybrid_binary_matching_pool_levels_that_sum_to_100_succeeds(): void
    {
        Livewire::actingAs($this->admin())
            ->test(Settings::class)
            ->fillForm([
                'income_enabled_hybrid_binary_matching' => true,
                // fillForm() writes state directly rather than through
                // Filament's own hydration, so items need the same
                // sub-field-wrapped shape the live component actually
                // holds internally (see the note above).
                'hybrid_binary_matching_pool_levels' => [
                    ['percentage' => 60],
                    ['percentage' => 40],
                ],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(2, CommissionConfiguration::where('plan_type', Commission::TYPE_HYBRID_BINARY_POOL)->count());
        $this->assertEquals(60, CommissionConfiguration::where('plan_type', Commission::TYPE_HYBRID_BINARY_POOL)->where('level', 1)->value('percentage'));
        $this->assertEquals(40, CommissionConfiguration::where('plan_type', Commission::TYPE_HYBRID_BINARY_POOL)->where('level', 2)->value('percentage'));
    }

    public function test_saving_hybrid_binary_matching_pool_levels_that_dont_sum_to_100_fails_validation(): void
    {
        Livewire::actingAs($this->admin())
            ->test(Settings::class)
            ->fillForm([
                'income_enabled_hybrid_binary_matching' => true,
                'hybrid_binary_matching_pool_levels' => [
                    ['percentage' => 50],
                    ['percentage' => 30],
                ],
            ])
            ->call('save')
            ->assertHasFormErrors(['hybrid_binary_matching_pool_levels']);

        $this->assertSame(0, CommissionConfiguration::where('plan_type', Commission::TYPE_HYBRID_BINARY_POOL)->count());
    }

    /**
     * Configurable Binary Matching's basis/qualification-rule/payout-formula
     * fields are each contributed by a discovered strategy and wrapped in
     * their own visible() gate (see ConfigurableBinaryMatchingModule::
     * settingsSchema()) — only the currently-selected strategy's own
     * fields should ever render, the rest stay hidden (and, per
     * dehydratedWhenHidden(), still get saved without being nulled out).
     */
    public function test_only_the_selected_matching_basis_fields_are_visible(): void
    {
        SystemSetting::set('configurable_binary_matching_enabled', 'true', 'commission', 'boolean');
        SystemSetting::set('configurable_binary_matching_basis', 'volume', 'commission', 'string');

        $component = Livewire::actingAs($this->admin())->test(Settings::class);

        $component->assertFormFieldIsVisible('volume_basis_pair_value')
            ->assertFormFieldIsHidden('count_basis_members_per_pair');

        $component->set('data.configurable_binary_matching_basis', 'count')
            ->assertFormFieldIsHidden('volume_basis_pair_value')
            ->assertFormFieldIsVisible('count_basis_members_per_pair');
    }

    public function test_only_the_selected_qualification_rule_and_payout_formula_fields_are_visible(): void
    {
        SystemSetting::set('configurable_binary_matching_enabled', 'true', 'commission', 'boolean');
        SystemSetting::set('configurable_binary_matching_payout_formula', 'flat_per_pair', 'commission', 'string');

        $component = Livewire::actingAs($this->admin())->test(Settings::class);

        $component->assertFormFieldIsVisible('flat_per_pair_amount')
            ->assertFormFieldIsHidden('percentage_of_matched_volume_percentage');

        $component->set('data.configurable_binary_matching_payout_formula', 'percentage_of_matched_volume')
            ->assertFormFieldIsHidden('flat_per_pair_amount')
            ->assertFormFieldIsVisible('percentage_of_matched_volume_percentage');
    }

    public function test_pool_levels_are_only_visible_when_the_pool_toggle_is_on(): void
    {
        SystemSetting::set('configurable_binary_matching_enabled', 'true', 'commission', 'boolean');
        SystemSetting::set('configurable_binary_matching_pool_enabled', 'false', 'commission', 'boolean');

        $component = Livewire::actingAs($this->admin())->test(Settings::class);

        $component->assertFormFieldIsHidden('configurable_binary_matching_pool_levels');

        $component->set('data.configurable_binary_matching_pool_enabled', true)
            ->assertFormFieldIsVisible('configurable_binary_matching_pool_levels');
    }

    public function test_saving_does_not_null_out_the_hidden_basis_fields(): void
    {
        // volume_basis_pair_value is hidden while Count basis is
        // selected — same dehydration hazard as matrix_width above.
        SystemSetting::set('configurable_binary_matching_enabled', 'true', 'commission', 'boolean');
        SystemSetting::set('volume_basis_pair_value', '25', 'commission', 'decimal');

        Livewire::actingAs($this->admin())
            ->test(Settings::class)
            ->fillForm(['configurable_binary_matching_basis' => 'count'])
            ->call('save');

        $this->assertEquals(25, SystemSetting::get('volume_basis_pair_value'));
    }
}
