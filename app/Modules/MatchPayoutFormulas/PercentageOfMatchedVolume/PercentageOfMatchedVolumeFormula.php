<?php

namespace App\Modules\MatchPayoutFormulas\PercentageOfMatchedVolume;

use App\Models\Order;
use App\Models\SystemSetting;
use App\Services\Modules\MatchPayoutFormula;
use Filament\Forms\Components\TextInput;

/**
 * A percentage of the matched pairs' total value (pairs × pairUnitSize)
 * — meaningful when the active MatchingBasis's unit is a dollar figure
 * (Volume). Not hard-restricted to that basis, just documented, same
 * pattern BinaryPairingCommissionModule already uses for its own
 * placement caveat.
 */
class PercentageOfMatchedVolumeFormula implements MatchPayoutFormula
{
    public static function key(): string
    {
        return 'percentage_of_matched_volume';
    }

    public function label(): string
    {
        return 'Percentage of matched volume';
    }

    public function baseAmount(Order $order, int $pairs, float $pairUnitSize): float
    {
        $percentage = (float) SystemSetting::get('percentage_of_matched_volume_percentage', 7);

        return round($pairs * $pairUnitSize * ($percentage / 100), 2);
    }

    public function displayPercentage(): float
    {
        return (float) SystemSetting::get('percentage_of_matched_volume_percentage', 7);
    }

    public function settingsSchema(): array
    {
        return [
            TextInput::make('percentage_of_matched_volume_percentage')
                ->label('Percentage')
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
            'percentage_of_matched_volume_percentage' => (float) SystemSetting::get('percentage_of_matched_volume_percentage', 7),
        ];
    }

    public function saveSettings(array $state): void
    {
        SystemSetting::set('percentage_of_matched_volume_percentage', (string) ($state['percentage_of_matched_volume_percentage'] ?? SystemSetting::get('percentage_of_matched_volume_percentage', 7)), 'commission', 'decimal');
    }

    public function dedicatedSettingsPage(): ?string
    {
        return null;
    }
}
