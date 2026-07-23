<?php

namespace App\Modules\Income\BinaryPairingCommission;

use App\Models\Commission;
use App\Models\CommissionConfiguration;
use App\Models\Order;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\Commission\CommissionPayoutRecorder;
use App\Services\Modules\OrderTriggeredIncomeModule;
use App\Services\TreeService;
use Filament\Forms\Components\TextInput;
use Illuminate\Support\Collection;

/**
 * Binary's base commission, now an ordinary income module rather than
 * something baked into the Binary plan itself: a pairing/matching
 * bonus. Every completed order credits the buyer's sale volume to the
 * correct left/right leg of each upline (whichever side the buyer
 * descends from). Whenever an upline's two legs both carry volume, the
 * smaller side is "matched" and the upline is paid a percentage of the
 * matched volume. Volume that goes unpaid because of a per-period cap
 * carries forward and is eligible to match again on a future order.
 * Defaults to enabled only while Binary is the active plan, so a fresh
 * install still pays out without extra setup, without also defaulting
 * on for a client running a different plan. Only meaningful under
 * Binary placement anyway — left/right leg volume never accumulates
 * under Unilevel or Matrix, since neither ever sets a user's
 * `position` to "left"/"right".
 */
class BinaryPairingCommissionModule implements OrderTriggeredIncomeModule
{
    public function __construct(
        private readonly TreeService $tree,
        private readonly CommissionPayoutRecorder $payouts,
    ) {}

    public static function key(): string
    {
        return 'binary_pairing_commission';
    }

    public function label(): string
    {
        return 'Binary Pairing Commission';
    }

    public function description(): string
    {
        return 'Two legs (left/right) per member. Ancestors earn a pairing bonus once both legs carry matching volume. Only meaningful under Binary placement.';
    }

    public function isEnabled(): bool
    {
        // See UnilevelLevelCommissionModule::isEnabled() for why the
        // active-plan-derived default isn't passed as SystemSetting::get()'s
        // own $default — that gets cached forever keyed only by the
        // setting name, ignoring that the right default here changes
        // whenever active_plan_type does.
        $stored = SystemSetting::get('binary_pairing_commission_enabled');

        if ($stored === null) {
            return SystemSetting::get('active_plan_type', 'unilevel') === Commission::TYPE_BINARY;
        }

        return filter_var($stored, FILTER_VALIDATE_BOOLEAN);
    }

    public function setEnabled(bool $enabled): void
    {
        SystemSetting::set('binary_pairing_commission_enabled', $enabled ? 'true' : 'false', 'commission', 'boolean');
    }

    /** @return Collection<int, Commission> */
    public function handle(Order $order): Collection
    {
        $created = new Collection;

        $config = CommissionConfiguration::query()
            ->where('plan_type', Commission::TYPE_BINARY)
            ->where('level', 1)
            ->where('is_active', true)
            ->first();

        $percentage = (float) ($config?->percentage ?? SystemSetting::get('binary_pair_percentage', 10));

        foreach ($this->creditedUplines($order->user) as [$upline, $side]) {
            $commission = $this->payUpline($order, $upline, $side, $config, $percentage);

            if ($commission !== null) {
                $created->push($commission);
            }
        }

        return $created;
    }

    /**
     * Ancestors of the buyer paired with which leg (left/right) of that
     * ancestor the buyer's branch descends through, closest ancestor first.
     *
     * @return list<array{0: User, 1: string}>
     */
    private function creditedUplines(User $buyer): array
    {
        $chain = $this->tree->getAncestors($buyer)->reverse()->values(); // closest-first
        $child = $buyer;

        $pairs = [];
        foreach ($chain as $ancestor) {
            if ($child->position === User::POSITION_LEFT || $child->position === User::POSITION_RIGHT) {
                $pairs[] = [$ancestor, $child->position];
            }

            $child = $ancestor;
        }

        return $pairs;
    }

    private function payUpline(Order $order, User $upline, string $side, ?CommissionConfiguration $config, float $percentage): ?Commission
    {
        if ($upline->status !== User::STATUS_ACTIVE) {
            return null;
        }

        $column = $side === User::POSITION_LEFT ? 'left_volume' : 'right_volume';
        $upline->forceFill([$column => (float) $upline->{$column} + (float) $order->commission_value])->save();

        $matchedVolume = min((float) $upline->left_volume, (float) $upline->right_volume);

        if ($matchedVolume <= 0 || $percentage <= 0) {
            return null;
        }

        $baseAmount = round($matchedVolume * ($percentage / 100), 2);

        if ($baseAmount <= 0) {
            return null;
        }

        $rankMultiplier = (float) ($upline->rank?->commission_multiplier ?? 1.0);
        $originalAmount = round($baseAmount * $rankMultiplier, 2);
        $amount = $originalAmount;

        if ($config?->cap !== null) {
            $period = $config->settings['cap_period'] ?? 'monthly';
            $alreadyEarned = $this->periodCommissionSum($upline, $period);
            $remainingCap = max(0.0, (float) $config->cap - $alreadyEarned);
            $amount = min($amount, $remainingCap);
        }

        if ($amount <= 0) {
            return null;
        }

        // Only the volume proportional to what was actually paid is
        // consumed; whatever a cap withheld carries forward unmatched.
        $volumeConsumed = min($matchedVolume, $matchedVolume * ($amount / $originalAmount));

        $upline->forceFill([
            'left_volume' => (float) $upline->left_volume - $volumeConsumed,
            'right_volume' => (float) $upline->right_volume - $volumeConsumed,
        ])->save();

        return $this->payouts->record(
            order: $order,
            upline: $upline,
            level: 1,
            baseAmount: $baseAmount,
            amount: $amount,
            percentage: $percentage,
            rankMultiplier: $rankMultiplier,
            planType: Commission::TYPE_BINARY,
            description: 'binary pairing bonus',
            position: $side,
        );
    }

    private function periodCommissionSum(User $user, string $period): float
    {
        $start = match ($period) {
            'daily' => now()->startOfDay(),
            'weekly' => now()->startOfWeek(),
            default => now()->startOfMonth(),
        };

        return (float) Commission::query()
            ->where('user_id', $user->id)
            ->where('plan_type', Commission::TYPE_BINARY)
            ->where('calculated_at', '>=', $start)
            ->whereIn('status', [Commission::STATUS_PENDING, Commission::STATUS_PAID])
            ->sum('amount');
    }

    public function settingsSchema(): array
    {
        return [
            TextInput::make('binary_pair_percentage')
                ->label('Binary pairing percentage')
                ->numeric()
                ->minValue(0)
                ->maxValue(100)
                ->suffix('%')
                ->required(),
        ];
    }

    public function settingsData(): array
    {
        return [
            'binary_pair_percentage' => (float) SystemSetting::get('binary_pair_percentage', 10),
        ];
    }

    public function saveSettings(array $state): void
    {
        $value = $state['binary_pair_percentage'] ?? SystemSetting::get('binary_pair_percentage', 10);

        SystemSetting::set('binary_pair_percentage', (string) $value, 'commission', 'integer');
    }

    public function dedicatedSettingsPage(): ?string
    {
        return null;
    }
}
