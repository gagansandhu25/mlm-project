<?php

namespace App\Services\Placement;

use App\Models\User;

/**
 * Unilevel trees have unlimited width: a recruit is always placed
 * directly under their sponsor.
 */
class UnilevelPlacementStrategy implements PlacementStrategyInterface
{
    public function findPlacement(User $sponsor): PlacementResult
    {
        return new PlacementResult(parent: $sponsor, position: null);
    }
}
