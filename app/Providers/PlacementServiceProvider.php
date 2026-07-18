<?php

namespace App\Providers;

use App\Services\Placement\BinaryPlacementStrategy;
use App\Services\Placement\MatrixPlacementStrategy;
use App\Services\Placement\PlacementStrategyRegistry;
use App\Services\Placement\UnilevelPlacementStrategy;
use Illuminate\Support\ServiceProvider;

/**
 * Registers every network-plan placement strategy. To add support for
 * a new compensation plan's tree shape: create a class implementing
 * PlacementStrategyInterface (it self-reports its `planType()`), and
 * add it to the list below. TreeService and PlacementStrategyRegistry
 * never need to change.
 */
class PlacementServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PlacementStrategyRegistry::class, fn ($app) => new PlacementStrategyRegistry([
            $app->make(UnilevelPlacementStrategy::class),
            $app->make(BinaryPlacementStrategy::class),
            $app->make(MatrixPlacementStrategy::class),
        ]));
    }
}
