<?php

namespace App\Modules\PackageResolvers\HighestPackagePurchase;

use App\Models\Order;
use App\Models\User;
use App\Services\Modules\ActivePackageResolver;

/** The user's single largest completed package purchase, ever. */
class HighestPackagePurchaseResolver implements ActivePackageResolver
{
    public static function key(): string
    {
        return 'highest_package_purchase';
    }

    public function label(): string
    {
        return 'Highest Package Purchase';
    }

    public function resolve(User $user): float
    {
        return (float) Order::query()
            ->where('user_id', $user->id)
            ->where('status', Order::STATUS_COMPLETED)
            ->whereHas('product', fn ($query) => $query->where('is_package', true))
            ->max('amount');
    }
}
