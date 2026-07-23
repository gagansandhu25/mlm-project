<?php

namespace App\Services\Modules;

/**
 * Any way this business pays a member — any number can be enabled
 * simultaneously, independent of `active_plan_type` (which is now
 * purely a placement/tree-shape selector). This covers both what used
 * to be "the active plan's own" commission math (Unilevel Level
 * Commission, Binary Pairing Commission, Matrix Level Commission) and
 * every stacked bonus (Personal Volume, Direct Referral Bonus, etc.) —
 * there's no longer a structural distinction between "the base plan"
 * and "a bonus," just a flat list of income modules an admin picks
 * from. A module that only makes sense under a particular tree shape
 * (e.g. Binary Pairing Commission needs left/right leg volume) simply
 * pays nobody if enabled under an incompatible placement — see its
 * own description() for the caveat, rather than being hard-restricted.
 *
 * This interface alone isn't directly implementable — a module implements
 * one of its two triggers: ScheduledIncomeModule (runs on a schedule, e.g.
 * daily) or OrderTriggeredIncomeModule (runs on every completed order).
 */
interface IncomeModule extends HasSettingsSchema
{
    /** Static for the same reason as PlanModule::key() — see its docblock. */
    public static function key(): string;

    public function label(): string;

    /** Helper text shown under this module's heading on the Settings page. */
    public function description(): string;

    /** Whether this module is currently switched on (reads its own SystemSetting). */
    public function isEnabled(): bool;

    /**
     * Persist the on/off toggle. Kept separate from saveSettings() (which
     * only ever runs while enabled, per Settings.php's dehydratedWhenHidden
     * pattern) so the Settings page can flip this without needing to know
     * the module's underlying SystemSetting key.
     */
    public function setEnabled(bool $enabled): void;
}
