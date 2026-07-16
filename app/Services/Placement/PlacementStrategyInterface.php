<?php

namespace App\Services\Placement;

use App\Models\User;

interface PlacementStrategyInterface
{
    /**
     * Determine where a new recruit should be placed under the given sponsor.
     */
    public function findPlacement(User $sponsor): PlacementResult;
}
