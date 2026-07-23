<?php

namespace App\Modules\PackageResolvers\TotalPackagePurchases;

use App\Models\Order;
use App\Models\User;
use App\Services\Modules\ActivePackageResolver;

/** The sum of every completed package purchase the user has ever made. */
class TotalPackagePurchasesResolver implements ActivePackageResolver
{
    public static function key(): string
    {
        return 'total_package_purchases';
    }

    public function label(): string
    {
        return 'Total of All Package Purchases';
    }

    public function resolve(User $user): float
    {
        return (float) Order::query()
            ->where('user_id', $user->id)
            ->where('status', Order::STATUS_COMPLETED)
            ->whereHas('product', fn ($query) => $query->where('is_package', true))
            ->sum('amount');
    }
}
