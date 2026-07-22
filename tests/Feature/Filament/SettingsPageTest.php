<?php

namespace Tests\Feature\Filament;

use App\Filament\Pages\Settings;
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
                'personal_volume_commission_enabled' => true,
                'personal_volume_percentage' => 2,
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
            ->fillForm(['personal_volume_commission_enabled' => false])
            ->call('save');

        $this->assertSame('false', SystemSetting::get('personal_volume_commission_enabled'));
        $this->assertEquals(2, SystemSetting::get('personal_volume_percentage'));
    }
}
