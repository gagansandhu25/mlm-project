<?php

namespace App\Modules\Plans\Unilevel;

use App\Models\Commission;
use App\Services\Modules\PlanModule;
use App\Services\Placement\PlacementStrategyInterface;

class UnilevelModule implements PlanModule
{
    public function __construct(
        private readonly UnilevelPlacementStrategy $placementStrategy,
    ) {}

    public static function key(): string
    {
        return Commission::TYPE_UNILEVEL;
    }

    public function label(): string
    {
        return 'Unilevel';
    }

    public function description(): string
    {
        return 'Every recruit is placed directly under their actual sponsor — unlimited width, no spillover.';
    }

    public function placementStrategy(): PlacementStrategyInterface
    {
        return $this->placementStrategy;
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
