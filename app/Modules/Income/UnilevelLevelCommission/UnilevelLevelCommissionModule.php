<?php

namespace App\Modules\Income\UnilevelLevelCommission;

use App\Models\Commission;
use App\Models\CommissionConfiguration;
use App\Models\Order;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\Commission\LevelLadderPayer;
use App\Services\Modules\OrderTriggeredIncomeModule;
use Illuminate\Support\Collection;

/**
 * Unilevel's base commission, now an ordinary income module rather
 * than something baked into the Unilevel plan itself: every qualifying
 * upline, up to whichever level has an active CommissionConfiguration,
 * earns a fixed percentage of the sale, scaled by rank multiplier and
 * capped per period — no extra eligibility condition. Defaults to
 * enabled only while Unilevel is the active plan, so a fresh install
 * still pays out without any extra setup — matching what "picking
 * Unilevel" used to guarantee unconditionally — without also defaulting
 * on for a client running Binary or Matrix, who never asked for this.
 * Mechanically works under any tree shape (it just walks ancestors),
 * but its own CommissionConfiguration rows are meant to be configured
 * for a Unilevel-shaped tree specifically.
 */
class UnilevelLevelCommissionModule implements OrderTriggeredIncomeModule
{
    public function __construct(
        private readonly LevelLadderPayer $ladder,
    ) {}

    public static function key(): string
    {
        return 'unilevel_level_commission';
    }

    public function label(): string
    {
        return 'Unilevel Level Commission';
    }

    public function description(): string
    {
        return 'Every direct-line ancestor, up to a configured depth, earns a percentage of the sale — unconditionally.';
    }

    public function isEnabled(): bool
    {
        // Deliberately not passed as SystemSetting::get()'s own $default
        // — that value gets cached forever keyed only by the setting
        // name, so a dynamic default computed from active_plan_type
        // would get frozen at whatever it evaluated to the first time
        // and never re-checked after the active plan changes.
        $stored = SystemSetting::get('unilevel_level_commission_enabled');

        if ($stored === null) {
            return SystemSetting::get('active_plan_type', 'unilevel') === Commission::TYPE_UNILEVEL;
        }

        return filter_var($stored, FILTER_VALIDATE_BOOLEAN);
    }

    public function setEnabled(bool $enabled): void
    {
        SystemSetting::set('unilevel_level_commission_enabled', $enabled ? 'true' : 'false', 'commission', 'boolean');
    }

    /** @return Collection<int, Commission> */
    public function handle(Order $order): Collection
    {
        return $this->ladder->pay(
            $order,
            $order->user,
            Commission::TYPE_UNILEVEL,
            (float) $order->commission_value,
            fn (User $upline, CommissionConfiguration $config): bool => true,
        );
    }

    public function settingsSchema(): array
    {
        return [];
    }

    public function settingsData(): array
    {
        return [];
    }

    public function saveSettings(array $state): void
    {
        //
    }

    public function dedicatedSettingsPage(): ?string
    {
        return null;
    }
}
