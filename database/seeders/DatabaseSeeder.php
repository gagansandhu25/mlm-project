<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RankSeeder::class,
            CommissionConfigurationSeeder::class,
            SystemSettingSeeder::class,
            ProductSeeder::class,
            DemoNetworkSeeder::class,
        ]);
    }
}
