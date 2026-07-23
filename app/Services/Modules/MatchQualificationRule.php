<?php

namespace App\Services\Modules;

use App\Models\Order;

/**
 * Whether a completed order counts toward the buyer's leg at all —
 * evaluated once per order, before any MatchingBasis crediting, so it
 * composes with whichever basis is active rather than being tied to
 * one. Pluggable the same way a MatchingBasis is: a new rule (e.g. "the
 * buyer's first N orders" or "orders over a minimum amount") is a new
 * app/Modules/MatchQualificationRules/{Name}/{Name}Rule.php, never an
 * edit to ConfigurableBinaryMatchingModule.
 */
interface MatchQualificationRule extends HasSettingsSchema
{
    /** Static for the same reason as PlanModule::key() — see its docblock. */
    public static function key(): string;

    /** Human-facing name for the qualification-rule dropdown. */
    public function label(): string;

    public function qualifies(Order $order): bool;
}
