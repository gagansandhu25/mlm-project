<?php

namespace App\Modules\Plans\Binary;

use App\Models\Commission;
use App\Services\Modules\PlanModule;
use App\Services\Placement\PlacementStrategyInterface;

class BinaryModule implements PlanModule
{
    public function __construct(
        private readonly BinaryPlacementStrategy $placementStrategy,
    ) {}

    public static function key(): string
    {
        return Commission::TYPE_BINARY;
    }

    public function label(): string
    {
        return 'Binary';
    }

    public function description(): string
    {
        return 'Two legs (left/right) per member — new recruits fill the left slot then the right, spilling over breadth-first once both are taken.';
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
