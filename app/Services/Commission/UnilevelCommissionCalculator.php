<?php

namespace App\Services\Commission;

use App\Models\Commission;

/**
 * Unilevel commissions: every qualifying upline (up to the configured
 * max depth) earns a fixed percentage of the sale, scaled by their
 * rank multiplier and capped per the configured period. Implements
 * STEP 3 through STEP 9 of the commission workflow for the unilevel plan.
 */
class UnilevelCommissionCalculator extends LevelBasedCommissionCalculator
{
    protected function planType(): string
    {
        return 'unilevel';
    }

    protected function commissionType(): string
    {
        return Commission::TYPE_UNILEVEL;
    }
}
