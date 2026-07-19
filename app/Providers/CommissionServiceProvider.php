<?php

namespace App\Providers;

use App\Services\Commission\BinaryCommissionCalculator;
use App\Services\Commission\CommissionCalculatorRegistry;
use App\Services\Commission\MatrixCommissionCalculator;
use App\Services\Commission\PackageTierCommissionCalculator;
use App\Services\Commission\UnilevelCommissionCalculator;
use Illuminate\Support\ServiceProvider;

/**
 * Registers every network-plan commission calculator. To add support
 * for a new compensation plan: create a class implementing
 * CommissionCalculatorInterface (it self-reports its `planType()`),
 * and add it to the list below. CommissionService and
 * CommissionCalculatorRegistry never need to change.
 */
class CommissionServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CommissionCalculatorRegistry::class, fn ($app) => new CommissionCalculatorRegistry([
            $app->make(UnilevelCommissionCalculator::class),
            $app->make(BinaryCommissionCalculator::class),
            $app->make(MatrixCommissionCalculator::class),
            $app->make(PackageTierCommissionCalculator::class),
        ]));
    }
}
