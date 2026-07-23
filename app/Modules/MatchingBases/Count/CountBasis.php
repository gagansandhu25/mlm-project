<?php

namespace App\Modules\MatchingBases\Count;

use App\Models\Commission;
use App\Models\Order;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\Modules\MatchingBasis;
use Filament\Forms\Components\TextInput;

/**
 * Accrues one "member" per qualifying order onto the buyer's leg,
 * regardless of what they purchased — the head-count alternative to
 * VolumeBasis. Caps ceil the number of pairs allowed to form at all
 * (see MatchingBasis's own docblock), so they're plain pair-count
 * ceilings rather than a multiple of any dollar figure — a headcount
 * cap doesn't have a natural dollar-proportional equivalent the way
 * Volume's active-package-multiplier does.
 */
class CountBasis implements MatchingBasis
{
    public static function key(): string
    {
        return 'count';
    }

    public function label(): string
    {
        return 'Count';
    }

    public function unitLabel(): string
    {
        return 'members';
    }

    public function legColumns(): array
    {
        return ['left_count', 'right_count'];
    }

    public function creditAmount(Order $order): float
    {
        // The qualification rule already decided whether this order
        // reaches here at all — every qualifying order credits exactly
        // one member.
        return 1.0;
    }

    public function pairUnitSize(): float
    {
        return (float) SystemSetting::get('count_basis_members_per_pair', 1);
    }

    public function capUnit(): string
    {
        return 'count';
    }

    public function remainingRoom(User $user, string $period): float
    {
        $default = $period === 'daily' ? 10 : 1000;
        $cap = (float) SystemSetting::get("count_basis_{$period}_cap_pairs", $default);

        return max(0.0, $cap - $this->pairsEarnedSince($user, $period));
    }

    private function pairsEarnedSince(User $user, string $period): float
    {
        $query = Commission::query()
            ->where('user_id', $user->id)
            ->where('plan_type', Commission::TYPE_CONFIGURABLE_BINARY_MATCHING)
            ->whereIn('status', [Commission::STATUS_PENDING, Commission::STATUS_PAID]);

        if ($period === 'daily') {
            $query->where('calculated_at', '>=', now()->startOfDay());
        }

        return (float) $query->sum('units_matched');
    }

    public function settingsSchema(): array
    {
        return [
            TextInput::make('count_basis_members_per_pair')
                ->label('Members per pair')
                ->helperText('How many qualifying members on the weaker leg form one matched pair.')
                ->numeric()
                ->minValue(1)
                ->required(),

            TextInput::make('count_basis_daily_cap_pairs')
                ->label('Daily cap (pairs)')
                ->helperText('Maximum matched pairs paid per day.')
                ->numeric()
                ->minValue(0)
                ->required(),

            TextInput::make('count_basis_lifetime_cap_pairs')
                ->label('Lifetime cap (pairs)')
                ->helperText('Maximum matched pairs paid, ever.')
                ->numeric()
                ->minValue(0)
                ->required(),
        ];
    }

    public function settingsData(): array
    {
        return [
            'count_basis_members_per_pair' => (float) SystemSetting::get('count_basis_members_per_pair', 1),
            'count_basis_daily_cap_pairs' => (float) SystemSetting::get('count_basis_daily_cap_pairs', 10),
            'count_basis_lifetime_cap_pairs' => (float) SystemSetting::get('count_basis_lifetime_cap_pairs', 1000),
        ];
    }

    public function saveSettings(array $state): void
    {
        SystemSetting::set('count_basis_members_per_pair', (string) ($state['count_basis_members_per_pair'] ?? SystemSetting::get('count_basis_members_per_pair', 1)), 'commission', 'integer');
        SystemSetting::set('count_basis_daily_cap_pairs', (string) ($state['count_basis_daily_cap_pairs'] ?? SystemSetting::get('count_basis_daily_cap_pairs', 10)), 'commission', 'integer');
        SystemSetting::set('count_basis_lifetime_cap_pairs', (string) ($state['count_basis_lifetime_cap_pairs'] ?? SystemSetting::get('count_basis_lifetime_cap_pairs', 1000)), 'commission', 'integer');
    }

    public function dedicatedSettingsPage(): ?string
    {
        return null;
    }
}
