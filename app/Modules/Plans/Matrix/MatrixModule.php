<?php

namespace App\Modules\Plans\Matrix;

use App\Models\Commission;
use App\Models\SystemSetting;
use App\Services\Modules\PlanModule;
use App\Services\Placement\PlacementStrategyInterface;
use Filament\Forms\Components\TextInput;

class MatrixModule implements PlanModule
{
    public function __construct(
        private readonly MatrixPlacementStrategy $placementStrategy,
    ) {}

    public static function key(): string
    {
        return Commission::TYPE_MATRIX;
    }

    public function label(): string
    {
        return 'Matrix';
    }

    public function description(): string
    {
        return 'Like Unilevel, but placement is limited to a fixed width per node (e.g. 3 wide) before spilling over.';
    }

    public function placementStrategy(): PlacementStrategyInterface
    {
        return $this->placementStrategy;
    }

    public function settingsSchema(): array
    {
        return [
            TextInput::make('matrix_width')
                ->label('Matrix width')
                ->numeric()
                ->minValue(1)
                ->required(),
        ];
    }

    public function settingsData(): array
    {
        return [
            'matrix_width' => (int) SystemSetting::get('matrix_width', 3),
        ];
    }

    public function saveSettings(array $state): void
    {
        $value = $state['matrix_width'] ?? SystemSetting::get('matrix_width', 3);

        SystemSetting::set('matrix_width', (string) $value, 'commission', 'integer');
    }

    public function dedicatedSettingsPage(): ?string
    {
        return null;
    }
}
