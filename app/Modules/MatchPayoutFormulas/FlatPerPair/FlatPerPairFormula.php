<?php

namespace App\Modules\MatchPayoutFormulas\FlatPerPair;

use App\Models\Order;
use App\Models\SystemSetting;
use App\Services\Modules\MatchPayoutFormula;
use Filament\Forms\Components\TextInput;

/** A fixed dollar amount per matched pair, regardless of what triggered it — basis-agnostic. */
class FlatPerPairFormula implements MatchPayoutFormula
{
    public static function key(): string
    {
        return 'flat_per_pair';
    }

    public function label(): string
    {
        return 'Flat amount per pair';
    }

    public function baseAmount(Order $order, int $pairs, float $pairUnitSize): float
    {
        return round($pairs * (float) SystemSetting::get('flat_per_pair_amount', 5), 2);
    }

    public function displayPercentage(): float
    {
        return 0.0;
    }

    public function settingsSchema(): array
    {
        return [
            TextInput::make('flat_per_pair_amount')
                ->label('Amount per pair')
                ->numeric()
                ->minValue(0)
                ->prefix('$')
                ->required(),
        ];
    }

    public function settingsData(): array
    {
        return [
            'flat_per_pair_amount' => (float) SystemSetting::get('flat_per_pair_amount', 5),
        ];
    }

    public function saveSettings(array $state): void
    {
        SystemSetting::set('flat_per_pair_amount', (string) ($state['flat_per_pair_amount'] ?? SystemSetting::get('flat_per_pair_amount', 5)), 'commission', 'decimal');
    }

    public function dedicatedSettingsPage(): ?string
    {
        return null;
    }
}
