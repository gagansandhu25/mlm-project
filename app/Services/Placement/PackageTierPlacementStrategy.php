<?php

namespace App\Services\Placement;

use App\Models\Commission;

/**
 * Package Tier uses the same placement rule as Unilevel — a recruit
 * always goes directly under their actual sponsor — which is what
 * makes tree-ancestor-level-1 reliably equal the real referring
 * sponsor for PackageTierCommissionCalculator's direct reward.
 */
class PackageTierPlacementStrategy extends UnilevelPlacementStrategy
{
    public function planType(): string
    {
        return Commission::TYPE_PACKAGE_TIER;
    }
}
