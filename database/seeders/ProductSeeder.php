<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $products = [
            ['name' => 'Starter Pack', 'price' => 99.00, 'commission_value' => 99.00, 'category' => 'Packages'],
            ['name' => 'Pro Pack', 'price' => 299.00, 'commission_value' => 299.00, 'category' => 'Packages'],
            ['name' => 'Elite Pack', 'price' => 999.00, 'commission_value' => 999.00, 'category' => 'Packages'],
        ];

        foreach ($products as $product) {
            Product::query()->updateOrCreate(['name' => $product['name']], $product + [
                'description' => $product['name'].' membership package.',
                'stock' => 100000,
                'status' => 'active',
            ]);
        }
    }
}
