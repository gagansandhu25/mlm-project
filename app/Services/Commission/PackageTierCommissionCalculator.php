<?php

namespace App\Services\Commission;

use App\Models\Commission;
use App\Models\CommissionConfiguration;
use App\Models\Order;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Package Tier: members join via a priced package, their direct
 * sponsor gets a flat, unconditional referral reward, and a separate
 * 7-level tier ladder pays ancestors up to 7 levels deep — each tier
 * gated by an admin-configured qualifying amount, checked against
 * whichever condition type the admin selects (an upline's own highest
 * package purchase, their team volume, or the buyer's own package
 * value). The tier ladder reuses LevelBasedCommissionCalculator's
 * level-walk/cap/rank-multiplier machinery via meetsQualifyingCondition();
 * the direct reward is a separate additionalPayouts() step so it can
 * be independently toggled and never counts toward a tier's cap (see
 * Commission::TYPE_PACKAGE_TIER_DIRECT).
 */
class PackageTierCommissionCalculator extends LevelBasedCommissionCalculator
{
    public function planType(): string
    {
        return Commission::TYPE_PACKAGE_TIER;
    }

    protected function commissionType(): string
    {
        return Commission::TYPE_PACKAGE_TIER;
    }

    protected function meetsQualifyingCondition(User $upline, Order $order, CommissionConfiguration $config): bool
    {
        $requiredAmount = (float) ($config->settings['qualifying_amount'] ?? 0);

        if ($requiredAmount <= 0) {
            return true;
        }

        return $this->qualifyingAmountFor($upline, $order) >= $requiredAmount;
    }

    protected function additionalPayouts(Order $order): Collection
    {
        $commission = $this->payDirectReward($order);

        return $commission ? new Collection([$commission]) : new Collection;
    }

    /**
     * Qualification is checked against package *price* (Order::amount),
     * not commission_value — the admin's tier thresholds are meant as
     * package-price tiers within the catalog's $1-$1000 range, distinct
     * from commission_value, which stays the commissionable base for
     * every payout percentage calculation, unchanged.
     */
    private function qualifyingAmountFor(User $upline, Order $order): float
    {
        return match (SystemSetting::get('package_tier_condition_type', 'own_package')) {
            'team_volume' => $this->tree->getTeamVolume($upline) + (float) $upline->sales_volume,
            'buyer_package' => (float) $order->amount,
            default => $this->highestPackagePurchase($upline), // 'own_package'
        };
    }

    /** The upline's own highest completed package purchase, if any. */
    private function highestPackagePurchase(User $user): float
    {
        return (float) Order::query()
            ->where('user_id', $user->id)
            ->where('status', Order::STATUS_COMPLETED)
            ->whereHas('product', fn ($query) => $query->where('is_package', true))
            ->max('amount');
    }

    private function payDirectReward(Order $order): ?Commission
    {
        if (! filter_var(SystemSetting::get('package_tier_direct_reward_enabled', true), FILTER_VALIDATE_BOOLEAN)) {
            return null;
        }

        $sponsor = $this->tree->getAncestorsByLevel($order->user, 1)[1] ?? null;

        if (! $sponsor || $sponsor->status !== User::STATUS_ACTIVE) {
            return null;
        }

        $percentage = (float) SystemSetting::get('package_tier_direct_reward_percentage', 5);
        $baseAmount = round((float) $order->commission_value * ($percentage / 100), 2);

        if ($baseAmount <= 0) {
            return null;
        }

        $rankMultiplier = (float) ($sponsor->rank?->commission_multiplier ?? 1.0);
        $amount = round($baseAmount * $rankMultiplier, 2);

        if ($amount <= 0) {
            return null;
        }

        // Unconditional by design — no cap check, unlike payUpline().
        return $this->recordPayout(
            order: $order,
            upline: $sponsor,
            level: 0,
            baseAmount: $baseAmount,
            amount: $amount,
            percentage: $percentage,
            rankMultiplier: $rankMultiplier,
            planType: Commission::TYPE_PACKAGE_TIER_DIRECT,
            description: 'direct referral reward',
        );
    }
}
