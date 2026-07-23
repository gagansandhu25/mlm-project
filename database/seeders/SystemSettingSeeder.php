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
        // Only the module matching active_plan_type above is seeded
        // enabled — the other two would otherwise sit alongside it
        // permanently on, silently double-paying if the tree or its
        // CommissionConfiguration ever gained data for more than one
        // plan. Matches what each module's own isEnabled() already
        // defaults to when active_plan_type changes later.
        SystemSetting::set('unilevel_level_commission_enabled', 'true', 'commission', 'boolean');
        SystemSetting::set('matrix_level_commission_enabled', 'false', 'commission', 'boolean');
        SystemSetting::set('binary_pairing_commission_enabled', 'false', 'commission', 'boolean');
        SystemSetting::set('binary_pair_percentage', '10', 'commission', 'integer');
        SystemSetting::set('personal_volume_commission_enabled', 'false', 'commission', 'boolean');
        SystemSetting::set('personal_volume_percentage', '2', 'commission', 'decimal');
        SystemSetting::set('minimum_payout_threshold', '50', 'payout', 'decimal');
        SystemSetting::set('withdrawal_fee_percentage', '2', 'payout', 'decimal');
        SystemSetting::set('direct_referral_bonus_enabled', 'false', 'commission', 'boolean');
        SystemSetting::set('direct_referral_bonus_percentage', '5', 'commission', 'decimal');
        SystemSetting::set('multi_tier_referral_bonus_enabled', 'false', 'commission', 'boolean');
        SystemSetting::set('multi_tier_referral_bonus_condition_type', 'own_package', 'commission', 'string');
        SystemSetting::set('sideline_growth_bonus_enabled', 'false', 'commission', 'boolean');
        SystemSetting::set('sideline_growth_bonus_percentage', '10', 'commission', 'decimal');
        SystemSetting::set('hybrid_binary_matching_enabled', 'false', 'commission', 'boolean');
        SystemSetting::set('hybrid_binary_matching_pair_value', '25', 'commission', 'decimal');
        SystemSetting::set('hybrid_binary_matching_self_percentage', '7', 'commission', 'decimal');
        SystemSetting::set('hybrid_binary_matching_active_package_resolver', 'highest_package_purchase', 'commission', 'string');
        SystemSetting::set('hybrid_binary_matching_daily_cap_multiplier', '2', 'commission', 'decimal');
        SystemSetting::set('hybrid_binary_matching_lifetime_cap_multiplier', '5', 'commission', 'decimal');
        SystemSetting::set('fixed_yield_investment_enabled', 'false', 'commission', 'boolean');
        SystemSetting::set('fixed_yield_investment_cap_multiplier', '2', 'commission', 'decimal');
        SystemSetting::set('configurable_binary_matching_enabled', 'false', 'commission', 'boolean');
        SystemSetting::set('configurable_binary_matching_basis', 'volume', 'commission', 'string');
        SystemSetting::set('configurable_binary_matching_qualification_rule', 'every_order', 'commission', 'string');
        SystemSetting::set('configurable_binary_matching_payout_formula', 'flat_per_pair', 'commission', 'string');
        SystemSetting::set('configurable_binary_matching_pool_enabled', 'true', 'commission', 'boolean');
        SystemSetting::set('volume_basis_pair_value', '25', 'commission', 'decimal');
        SystemSetting::set('volume_basis_active_package_resolver', 'highest_package_purchase', 'commission', 'string');
        SystemSetting::set('volume_basis_daily_cap_multiplier', '2', 'commission', 'decimal');
        SystemSetting::set('volume_basis_lifetime_cap_multiplier', '5', 'commission', 'decimal');
        SystemSetting::set('count_basis_members_per_pair', '1', 'commission', 'integer');
        SystemSetting::set('count_basis_daily_cap_pairs', '10', 'commission', 'integer');
        SystemSetting::set('count_basis_lifetime_cap_pairs', '1000', 'commission', 'integer');
        SystemSetting::set('flat_per_pair_amount', '5', 'commission', 'decimal');
        SystemSetting::set('percentage_of_matched_volume_percentage', '7', 'commission', 'decimal');
    }
}
