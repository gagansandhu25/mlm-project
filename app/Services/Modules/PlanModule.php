<?php

namespace App\Services\Modules;

use App\Services\Placement\PlacementStrategyInterface;

/**
 * A tree placement strategy — exactly one is active at a time
 * (`active_plan_type`). Purely about where a recruit lands in the tree;
 * how uplines get paid is entirely the concern of IncomeModule now,
 * including what used to be "the plan's own" commission math (Unilevel
 * Level Commission, Binary Pairing Commission, Matrix Level Commission
 * are all income modules, same as any other bonus). Decoupling the two
 * means a client can run, say, Binary-shaped placement with a
 * completely different pairing formula, without touching this module.
 *
 * To add a new plan: create `app/Modules/Plans/{Name}/{Name}Module.php`
 * implementing this interface. ModuleDiscovery finds it automatically —
 * nothing outside that folder needs to change.
 */
interface PlanModule extends HasSettingsSchema
{
    /**
     * The `active_plan_type` value this module handles, e.g. "unilevel".
     * Static so PlanModuleRegistry can build its key-to-class map without
     * instantiating every module up front — see ModuleDiscovery's
     * docblock for why eager instantiation here would recurse.
     */
    public static function key(): string;

    /** Human-facing name for the Settings page's plan dropdown. */
    public function label(): string;

    /** Helper text shown under the dropdown when this plan is selected — describes placement only, not commission. */
    public function description(): string;

    public function placementStrategy(): PlacementStrategyInterface;
}
