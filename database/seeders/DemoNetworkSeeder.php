<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\TreeService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Builds a small demo unilevel network (one root + ~20 recruits placed
 * under random existing members) and runs a handful of completed
 * orders through the real commission engine, so the seeded database
 * is immediately useful for exploring the admin/user dashboards.
 */
class DemoNetworkSeeder extends Seeder
{
    public function run(): void
    {
        $tree = app(TreeService::class);

        $admin = User::query()->firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'role' => User::ROLE_SUPER_ADMIN,
                'status' => User::STATUS_ACTIVE,
                'referral_code' => Str::upper(Str::random(8)),
                'join_date' => now(),
                'depth' => 0,
            ]
        );
        if (! $admin->path) {
            $admin->path = (string) $admin->id;
            $admin->save();
        }

        $root = User::query()->firstOrCreate(
            ['email' => 'root@example.com'],
            [
                'name' => 'Root Sponsor',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'role' => User::ROLE_USER,
                'status' => User::STATUS_ACTIVE,
                'referral_code' => Str::upper(Str::random(8)),
                'join_date' => now(),
                'depth' => 0,
            ]
        );
        if (! $root->path) {
            $root->path = (string) $root->id;
            $root->save();
        }

        $members = [$root];

        for ($i = 0; $i < 20; $i++) {
            $sponsor = $members[array_rand($members)];

            $recruit = User::factory()->make();
            $recruit = $tree->placeNewUser($recruit, $sponsor, 'unilevel');

            $members[] = $recruit;
        }

        $products = Product::all();

        if ($products->isEmpty() || count($members) < 2) {
            return;
        }

        $buyerPool = array_slice($members, 1);

        foreach (range(1, 15) as $i) {
            $buyer = $buyerPool[array_rand($buyerPool)];
            $product = $products->random();

            // The OrderObserver fires CommissionService::calculateForOrder() automatically
            // once the order is saved with status=completed.
            Order::create([
                'user_id' => $buyer->id,
                'product_id' => $product->id,
                'order_number' => 'ORD-'.Str::upper(Str::random(10)),
                'amount' => $product->price,
                'commission_value' => $product->commission_value,
                'status' => Order::STATUS_COMPLETED,
                'order_date' => now(),
                'payment_method' => 'demo',
                'payment_status' => 'paid',
            ]);
        }
    }
}
