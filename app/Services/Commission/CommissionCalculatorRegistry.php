<?php

namespace App\Services\Commission;

/**
 * Resolves the CommissionCalculatorInterface for a given `active_plan_type`
 * value. Registered calculators are bound in CommissionServiceProvider —
 * adding support for a new compensation plan means creating a class that
 * implements CommissionCalculatorInterface and adding it there; nothing
 * in CommissionService or this registry needs to change.
 */
class CommissionCalculatorRegistry
{
    /** @var array<string, CommissionCalculatorInterface> */
    private array $calculators = [];

    /**
     * @param  iterable<CommissionCalculatorInterface>  $calculators
     */
    public function __construct(iterable $calculators)
    {
        foreach ($calculators as $calculator) {
            $this->calculators[$calculator->planType()] = $calculator;
        }
    }

    public function for(string $planType): CommissionCalculatorInterface
    {
        return $this->calculators[$planType]
            ?? throw new \InvalidArgumentException("Unknown plan type [{$planType}].");
    }
}
