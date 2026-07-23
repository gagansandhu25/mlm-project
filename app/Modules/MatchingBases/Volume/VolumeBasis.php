<?php

namespace App\Modules\MatchingBases\Volume;

use App\Models\Commission;
use App\Models\Order;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\Modules\ActivePackageResolverRegistry;
use App\Services\Modules\MatchingBasis;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;

/**
 * Accrues each order's dollar commission_value onto the buyer's leg —
 * what Binary Pairing Commission / Hybrid Binary Matching already do.
 * Caps ceil the formula's dollar *output* (see MatchingBasis's own
 * docblock for why 'currency' vs 'count' basis caps apply at different
 * pipeline stages), computed against an admin-selected
 * ActivePackageResolver, same as HybridBinaryMatchingModule.
 */
class VolumeBasis implements MatchingBasis
{
    public function __construct(private readonly ActivePackageResolverRegistry $resolvers) {}

    public static function key(): string
    {
        return 'volume';
    }

    public function label(): string
    {
        return 'Volume';
    }

    public function unitLabel(): string
    {
        return '$';
    }

    public function legColumns(): array
    {
        return ['left_volume', 'right_volume'];
    }

    public function creditAmount(Order $order): float
    {
        return (float) $order->commission_value;
    }

    public function pairUnitSize(): float
    {
        return (float) SystemSetting::get('volume_basis_pair_value', 25);
    }

    public function capUnit(): string
    {
        return 'currency';
    }

    public function remainingRoom(User $user, string $period): float
    {
        $default = $period === 'daily' ? 2 : 5;
        $multiplier = (float) SystemSetting::get("volume_basis_{$period}_cap_multiplier", $default);
        $cap = $this->resolveActivePackage($user) * $multiplier;

        return max(0.0, $cap - $this->selfEarningsSince($user, $period));
    }

    private function selfEarningsSince(User $user, string $period): float
    {
        $query = Commission::query()
            ->where('user_id', $user->id)
            ->where('plan_type', Commission::TYPE_CONFIGURABLE_BINARY_MATCHING)
            ->whereIn('status', [Commission::STATUS_PENDING, Commission::STATUS_PAID]);

        if ($period === 'daily') {
            $query->where('calculated_at', '>=', now()->startOfDay());
        }

        return (float) $query->sum('amount');
    }

    private function resolveActivePackage(User $user): float
    {
        $resolverKey = SystemSetting::get('volume_basis_active_package_resolver', 'highest_package_purchase');

        return $this->resolvers->for($resolverKey)->resolve($user);
    }

    public function settingsSchema(): array
    {
        return [
            TextInput::make('volume_basis_pair_value')
                ->label('Pair value')
                ->numeric()
                ->minValue(0.01)
                ->prefix('$')
                ->required(),

            Select::make('volume_basis_active_package_resolver')
                ->label('Active package rule')
                ->options(fn () => $this->resolvers->options())
                ->required(),

            TextInput::make('volume_basis_daily_cap_multiplier')
                ->label('Daily cap multiplier')
                ->helperText('Daily cap = active package × this.')
                ->numeric()
                ->minValue(0)
                ->required(),

            TextInput::make('volume_basis_lifetime_cap_multiplier')
                ->label('Lifetime cap multiplier')
                ->helperText('Lifetime cap = active package × this. Never resets.')
                ->numeric()
                ->minValue(0)
                ->required(),
        ];
    }

    public function settingsData(): array
    {
        return [
            'volume_basis_pair_value' => (float) SystemSetting::get('volume_basis_pair_value', 25),
            'volume_basis_active_package_resolver' => SystemSetting::get('volume_basis_active_package_resolver', 'highest_package_purchase'),
            'volume_basis_daily_cap_multiplier' => (float) SystemSetting::get('volume_basis_daily_cap_multiplier', 2),
            'volume_basis_lifetime_cap_multiplier' => (float) SystemSetting::get('volume_basis_lifetime_cap_multiplier', 5),
        ];
    }

    public function saveSettings(array $state): void
    {
        SystemSetting::set('volume_basis_pair_value', (string) ($state['volume_basis_pair_value'] ?? SystemSetting::get('volume_basis_pair_value', 25)), 'commission', 'decimal');
        SystemSetting::set('volume_basis_active_package_resolver', $state['volume_basis_active_package_resolver'] ?? SystemSetting::get('volume_basis_active_package_resolver', 'highest_package_purchase'), 'commission', 'string');
        SystemSetting::set('volume_basis_daily_cap_multiplier', (string) ($state['volume_basis_daily_cap_multiplier'] ?? SystemSetting::get('volume_basis_daily_cap_multiplier', 2)), 'commission', 'decimal');
        SystemSetting::set('volume_basis_lifetime_cap_multiplier', (string) ($state['volume_basis_lifetime_cap_multiplier'] ?? SystemSetting::get('volume_basis_lifetime_cap_multiplier', 5)), 'commission', 'decimal');
    }

    public function dedicatedSettingsPage(): ?string
    {
        return null;
    }
}
