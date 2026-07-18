<?php

namespace App\Services\Placement;

use App\Models\User;

interface PlacementStrategyInterface
{
    /**
     * The `active_plan_type` value this strategy handles, e.g. "unilevel".
     * Used by PlacementStrategyRegistry to resolve the right strategy
     * without TreeService needing to know every plan type that exists.
     */
    public function planType(): string;

    /**
     * Determine where a new recruit should be placed under the given sponsor.
     */
    public function findPlacement(User $sponsor): PlacementResult;
}
