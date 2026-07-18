<?php

namespace App\Services\Placement;

/**
 * Resolves the PlacementStrategyInterface for a given `active_plan_type`
 * value. Registered strategies are bound in PlacementServiceProvider —
 * adding support for a new compensation plan's tree shape means creating
 * a class that implements PlacementStrategyInterface and adding it
 * there; nothing in TreeService or this registry needs to change.
 */
class PlacementStrategyRegistry
{
    /** @var array<string, PlacementStrategyInterface> */
    private array $strategies = [];

    /**
     * @param  iterable<PlacementStrategyInterface>  $strategies
     */
    public function __construct(iterable $strategies)
    {
        foreach ($strategies as $strategy) {
            $this->strategies[$strategy->planType()] = $strategy;
        }
    }

    public function for(string $planType): PlacementStrategyInterface
    {
        return $this->strategies[$planType]
            ?? throw new \InvalidArgumentException("Unknown plan type [{$planType}].");
    }
}
