<?php

namespace App\Modules\MatchQualificationRules\FirstOrderOnly;

use App\Models\Order;
use App\Services\Modules\MatchQualificationRule;

/**
 * Qualifies only a buyer's first-ever completed order — a true
 * "new member" gate, so repeat purchases from someone already counted
 * don't add to their leg again. Assumes ascending `id` approximates
 * completion order, true under this app's current synchronous
 * processing model; if orders are ever processed out of creation
 * order, switch the comparison to `created_at` with `id` as tiebreaker.
 */
class FirstOrderOnlyRule implements MatchQualificationRule
{
    public static function key(): string
    {
        return 'first_order_only';
    }

    public function label(): string
    {
        return 'First order only';
    }

    public function qualifies(Order $order): bool
    {
        return Order::query()
            ->where('user_id', $order->user_id)
            ->where('status', Order::STATUS_COMPLETED)
            ->where('id', '<', $order->id)
            ->doesntExist();
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
