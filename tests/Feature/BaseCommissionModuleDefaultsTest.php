<?php

namespace Tests\Feature;

use App\Models\SystemSetting;
use App\Modules\Income\BinaryPairingCommission\BinaryPairingCommissionModule;
use App\Modules\Income\MatrixLevelCommission\MatrixLevelCommissionModule;
use App\Modules\Income\UnilevelLevelCommission\UnilevelLevelCommissionModule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Unilevel Level Commission, Matrix Level Commission, and Binary
 * Pairing Commission each default to enabled only while their own
 * plan matches active_plan_type — not unconditionally true for all
 * three at once, which would leave two live commission engines beyond
 * whichever plan the admin actually picked.
 */
class BaseCommissionModuleDefaultsTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_the_active_plans_module_defaults_to_enabled(): void
    {
        SystemSetting::set('active_plan_type', 'unilevel');

        $this->assertTrue(app(UnilevelLevelCommissionModule::class)->isEnabled());
        $this->assertFalse(app(MatrixLevelCommissionModule::class)->isEnabled());
        $this->assertFalse(app(BinaryPairingCommissionModule::class)->isEnabled());
    }

    public function test_default_follows_active_plan_type_when_it_changes(): void
    {
        SystemSetting::set('active_plan_type', 'matrix');

        $this->assertFalse(app(UnilevelLevelCommissionModule::class)->isEnabled());
        $this->assertTrue(app(MatrixLevelCommissionModule::class)->isEnabled());
        $this->assertFalse(app(BinaryPairingCommissionModule::class)->isEnabled());

        SystemSetting::set('active_plan_type', 'binary');

        $this->assertFalse(app(UnilevelLevelCommissionModule::class)->isEnabled());
        $this->assertFalse(app(MatrixLevelCommissionModule::class)->isEnabled());
        $this->assertTrue(app(BinaryPairingCommissionModule::class)->isEnabled());
    }

    public function test_an_explicit_setting_overrides_the_active_plan_default(): void
    {
        SystemSetting::set('active_plan_type', 'unilevel');
        SystemSetting::set('matrix_level_commission_enabled', 'true');

        // An admin can still deliberately run a second engine alongside
        // the active plan's own — the dynamic default only governs what
        // happens when nothing has been explicitly set.
        $this->assertTrue(app(MatrixLevelCommissionModule::class)->isEnabled());
    }
}
