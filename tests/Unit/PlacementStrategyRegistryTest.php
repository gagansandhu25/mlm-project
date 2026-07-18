<?php

namespace Tests\Unit;

use App\Services\Placement\BinaryPlacementStrategy;
use App\Services\Placement\MatrixPlacementStrategy;
use App\Services\Placement\PlacementStrategyRegistry;
use App\Services\Placement\UnilevelPlacementStrategy;
use Tests\TestCase;

class PlacementStrategyRegistryTest extends TestCase
{
    public function test_resolves_each_registered_plan_type_to_its_strategy(): void
    {
        $registry = app(PlacementStrategyRegistry::class);

        $this->assertInstanceOf(UnilevelPlacementStrategy::class, $registry->for('unilevel'));
        $this->assertInstanceOf(BinaryPlacementStrategy::class, $registry->for('binary'));
        $this->assertInstanceOf(MatrixPlacementStrategy::class, $registry->for('matrix'));
    }

    public function test_throws_for_an_unregistered_plan_type(): void
    {
        $registry = app(PlacementStrategyRegistry::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown plan type [stairstep].');

        $registry->for('stairstep');
    }

    public function test_each_strategy_self_reports_a_plan_type_matching_the_registry_key(): void
    {
        $registry = new PlacementStrategyRegistry([
            $binary = app(BinaryPlacementStrategy::class),
        ]);

        $this->assertSame($binary, $registry->for($binary->planType()));
    }
}
