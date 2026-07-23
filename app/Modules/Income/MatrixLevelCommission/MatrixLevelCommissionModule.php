<?php

namespace App\Modules\Income\MatrixLevelCommission;

use App\Models\Commission;
use App\Models\CommissionConfiguration;
use App\Models\Order;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\Commission\LevelLadderPayer;
use App\Services\Modules\OrderTriggeredIncomeModule;
use Illuminate\Support\Collection;

/**
 * Matrix's base commission, now an ordinary income module rather than
 * something baked into the Matrix plan itself: identical level-percentage
 * math to Unilevel Level Commission — only the tree shape (handled
 * upstream by MatrixPlacementStrategy/TreeService) differs, and that's
 * irrelevant to an ancestor-by-level payout. Kept as its own module
 * (rather than merged with Unilevel Level Commission) so each can have
 * its own independently-configured level/percentage/cap table via
 * CommissionConfiguration. Defaults to enabled only while Matrix is the
 * active plan, so a fresh install still pays out without extra setup,
 * without also defaulting on for a client running a different plan.
 */
class MatrixLevelCommissionModule implements OrderTriggeredIncomeModule
{
    public function __construct(
        private readonly LevelLadderPayer $ladder,
    ) {}

    public static function key(): string
    {
        return 'matrix_level_commission';
    }

    public function label(): string
    {
        return 'Matrix Level Commission';
    }

    public function description(): string
    {
        return 'Same level-percentage-of-sale math as Unilevel Level Commission — its own level table, meant for a Matrix-shaped tree.';
    }

    public function isEnabled(): bool
    {
        // See UnilevelLevelCommissionModule::isEnabled() for why the
        // active-plan-derived default isn't passed as SystemSetting::get()'s
        // own $default — that gets cached forever keyed only by the
        // setting name, ignoring that the right default here changes
        // whenever active_plan_type does.
        $stored = SystemSetting::get('matrix_level_commission_enabled');

        if ($stored === null) {
            return SystemSetting::get('active_plan_type', 'unilevel') === Commission::TYPE_MATRIX;
        }

        return filter_var($stored, FILTER_VALIDATE_BOOLEAN);
    }

    public function setEnabled(bool $enabled): void
    {
        SystemSetting::set('matrix_level_commission_enabled', $enabled ? 'true' : 'false', 'commission', 'boolean');
    }

    /** @return Collection<int, Commission> */
    public function handle(Order $order): Collection
    {
        return $this->ladder->pay(
            $order,
            $order->user,
            Commission::TYPE_MATRIX,
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
