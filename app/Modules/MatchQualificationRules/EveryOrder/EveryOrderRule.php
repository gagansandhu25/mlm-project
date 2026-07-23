<?php

namespace App\Modules\MatchQualificationRules\EveryOrder;

use App\Models\Order;
use App\Services\Modules\MatchQualificationRule;

/** No gate at all — every completed order qualifies, same as today's Binary Pairing Commission / Hybrid Binary Matching. */
class EveryOrderRule implements MatchQualificationRule
{
    public static function key(): string
    {
        return 'every_order';
    }

    public function label(): string
    {
        return 'Every order';
    }

    public function qualifies(Order $order): bool
    {
        return true;
    }

    public function settingsSchema(): array
    {
        return [];
    }

    public function settingsData(): array
    {
        return [];
    }

    public function saveSettings(array $state): void {}

    public function dedicatedSettingsPage(): ?string
    {
        return null;
    }
}
