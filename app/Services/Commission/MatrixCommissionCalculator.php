<?php

namespace App\Services\Commission;

use App\Models\Commission;

/**
 * Matrix commissions: same level-percentage-of-sale math as Unilevel —
 * the only difference is the fixed-width tree shape, which is already
 * handled upstream by MatrixPlacementStrategy/TreeService and is
 * irrelevant to ancestor-by-level payout calculation.
 */
class MatrixCommissionCalculator extends LevelBasedCommissionCalculator
{
    protected function planType(): string
    {
        return 'matrix';
    }

    protected function commissionType(): string
    {
        return Commission::TYPE_MATRIX;
    }
}
