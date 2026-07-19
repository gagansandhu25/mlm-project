<?php

namespace Database\Seeders;

use App\Models\SystemSetting;
use Illuminate\Database\Seeder;

class SystemSettingSeeder extends Seeder
{
    public function run(): void
    {
        SystemSetting::set('active_plan_type', 'unilevel', 'commission', 'string');
        SystemSetting::set('matrix_width', '3', 'commission', 'integer');
        SystemSetting::set('binary_pair_percentage', '10', 'commission', 'integer');
        SystemSetting::set('personal_volume_commission_enabled', 'false', 'commission', 'boolean');
        SystemSetting::set('personal_volume_percentage', '2', 'commission', 'decimal');
        SystemSetting::set('minimum_payout_threshold', '50', 'payout', 'decimal');
        SystemSetting::set('withdrawal_fee_percentage', '2', 'payout', 'decimal');
        SystemSetting::set('package_tier_direct_reward_enabled', 'true', 'commission', 'boolean');
        SystemSetting::set('package_tier_direct_reward_percentage', '5', 'commission', 'decimal');
        SystemSetting::set('package_tier_condition_type', 'own_package', 'commission', 'string');
    }
}
