<?php

namespace Database\Seeders;

use App\Models\CommissionConfiguration;
use Illuminate\Database\Seeder;

class CommissionConfigurationSeeder extends Seeder
{
    /** Default unilevel payout schedule: 10% on level 1, tapering down to 1% by level 10. */
    public function run(): void
    {
        $percentages = [10, 5, 3, 2, 2, 1, 1, 1, 1, 1];

        foreach ($percentages as $level => $percentage) {
            CommissionConfiguration::query()->updateOrCreate(
                ['plan_type' => 'unilevel', 'level' => $level + 1],
                [
                    'percentage' => $percentage,
                    'cap' => 5000,
                    'is_active' => true,
                    'settings' => ['cap_period' => 'monthly'],
                ]
            );
        }

        // Matrix uses the same level-taper structure as unilevel; clients
        // running a matrix plan are expected to tune this via Admin.
        foreach ($percentages as $level => $percentage) {
            CommissionConfiguration::query()->updateOrCreate(
                ['plan_type' => 'matrix', 'level' => $level + 1],
                [
                    'percentage' => $percentage,
                    'cap' => 5000,
                    'is_active' => true,
                    'settings' => ['cap_period' => 'monthly'],
                ]
            );
        }

        // Binary has a single pairing-bonus "level": the % of matched
        // left/right volume paid out per pair.
        CommissionConfiguration::query()->updateOrCreate(
            ['plan_type' => 'binary', 'level' => 1],
            [
                'percentage' => 10,
                'cap' => 5000,
                'is_active' => true,
                'settings' => ['cap_period' => 'monthly'],
            ]
        );
    }
}
