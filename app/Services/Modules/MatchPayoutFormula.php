<?php

namespace App\Services\Modules;

use App\Models\Order;

/**
 * How a matched-pair count converts into a self-payout amount —
 * pluggable the same way a MatchingBasis is: a new formula (e.g. a
 * tiered per-pair rate) is a new
 * app/Modules/MatchPayoutFormulas/{Name}/{Name}Formula.php, never an
 * edit to ConfigurableBinaryMatchingModule. $pairUnitSize is whatever
 * the active MatchingBasis::pairUnitSize() reports, so a formula that
 * cares about it (e.g. percentage-of-matched-volume) stays correct
 * regardless of which basis produced $pairs.
 */
interface MatchPayoutFormula extends HasSettingsSchema
{
    /** Static for the same reason as PlanModule::key() — see its docblock. */
    public static function key(): string;

    /** Human-facing name for the payout-formula dropdown. */
    public function label(): string;

    /** Base self-payout amount (before rank multiplier / capping) for $pairs whole matched pairs. */
    public function baseAmount(Order $order, int $pairs, float $pairUnitSize): float;

    /** Percentage to record on the Commission row for reporting only — 0.0 if this formula has no percentage concept. */
    public function displayPercentage(): float;
}
