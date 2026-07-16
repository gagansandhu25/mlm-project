<?php

namespace Database\Seeders;

use App\Models\Rank;
use Illuminate\Database\Seeder;

class RankSeeder extends Seeder
{
    public function run(): void
    {
        $ranks = [
            ['name' => 'Member', 'level' => 1, 'min_sales_volume' => 0, 'min_downline' => 0, 'commission_multiplier' => 1.00],
            ['name' => 'Bronze', 'level' => 2, 'min_sales_volume' => 1000, 'min_downline' => 5, 'commission_multiplier' => 1.05],
            ['name' => 'Silver', 'level' => 3, 'min_sales_volume' => 5000, 'min_downline' => 20, 'commission_multiplier' => 1.20],
            ['name' => 'Gold', 'level' => 4, 'min_sales_volume' => 20000, 'min_downline' => 50, 'commission_multiplier' => 1.50],
            ['name' => 'Platinum', 'level' => 5, 'min_sales_volume' => 50000, 'min_downline' => 100, 'commission_multiplier' => 2.00],
        ];

        foreach ($ranks as $rank) {
            Rank::query()->updateOrCreate(['level' => $rank['level']], $rank);
        }
    }
}
