<?php

namespace App\Services\Commission;

use App\Models\Commission;
use App\Models\CommissionConfiguration;
use App\Models\Order;
use App\Models\User;
use App\Services\TreeService;
use Illuminate\Support\Collection;

/**
 * Pays every ancestor of $anchor, up to whichever level has an active
 * CommissionConfiguration under a given plan_type, who qualifies — the
 * walk/cap/rank-multiplier engine shared by every level-based income
 * module. $anchor is usually $order->user (the buyer, for Unilevel
 * Level Commission, Matrix Level Commission, Multi-Tier Referral
 * Bonus), but doesn't have to be — Hybrid Binary Matching's pool
 * distribution walks the *matched person's own* upline instead, which
 * is a different person from whoever triggered the order. $baseValue
 * is the amount each level's percentage is taken from (usually
 * $order->commission_value, but a pool amount for the case above).
 * $planType is used both to look up the CommissionConfiguration rows
 * and to tag/track the Commission rows this produces, so two modules'
 * caps never conflate even when both pay the same upline from the
 * same order.
 */
class LevelLadderPayer
{
    public function __construct(
        private readonly TreeService $tree,
        private readonly CommissionPayoutRecorder $payouts,
    ) {}

    /**
     * @param  callable(User, CommissionConfiguration): bool  $qualifies
     * @return Collection<int, Commission>
     */
    public function pay(Order $order, User $anchor, string $planType, float $baseValue, callable $qualifies): Collection
    {
        $configs = CommissionConfiguration::query()
            ->where('plan_type', $planType)
            ->where('is_active', true)
            ->orderBy('level')
            ->get()
            ->keyBy('level');

        $maxLevel = (int) $configs->keys()->max();

        $created = new Collection;

        if ($maxLevel > 0) {
            $ancestorsByLevel = $this->tree->getAncestorsByLevel($anchor, $maxLevel);

            foreach ($ancestorsByLevel as $level => $upline) {
                $commission = $this->payUpline($order, $upline, $level, $configs->get($level), $planType, $baseValue, $qualifies);

                if ($commission !== null) {
                    $created->push($commission);
                }
            }
        }

        return $created;
    }

    /** @param  callable(User, CommissionConfiguration): bool  $qualifies */
    private function payUpline(Order $order, User $upline, int $level, ?CommissionConfiguration $config, string $planType, float $baseValue, callable $qualifies): ?Commission
    {
        if (! $config || $upline->status !== User::STATUS_ACTIVE) {
            return null;
        }

        if (! $qualifies($upline, $config)) {
            return null;
        }

        $baseAmount = round($baseValue * ((float) $config->percentage / 100), 2);

        if ($baseAmount <= 0) {
            return null;
        }

        $rankMultiplier = (float) ($upline->rank?->commission_multiplier ?? 1.0);
        $amount = round($baseAmount * $rankMultiplier, 2);

        if ($config->cap !== null) {
            $period = $config->settings['cap_period'] ?? 'monthly';
            $alreadyEarned = $this->periodCommissionSum($upline, $planType, $period);
            $remainingCap = max(0.0, (float) $config->cap - $alreadyEarned);

            if ($remainingCap <= 0) {
                return null;
            }

            $amount = min($amount, $remainingCap);
        }

        if ($amount <= 0) {
            return null;
        }

        return $this->payouts->record(
            order: $order,
            upline: $upline,
            level: $level,
            baseAmount: $baseAmount,
            amount: $amount,
            percentage: (float) $config->percentage,
            rankMultiplier: $rankMultiplier,
            planType: $planType,
            description: "level {$level} {$planType} commission",
        );
    }

    private function periodCommissionSum(User $user, string $planType, string $period): float
    {
        $start = match ($period) {
            'daily' => now()->startOfDay(),
            'weekly' => now()->startOfWeek(),
            default => now()->startOfMonth(),
        };

        return (float) Commission::query()
            ->where('user_id', $user->id)
            ->where('plan_type', $planType)
            ->where('calculated_at', '>=', $start)
            ->whereIn('status', [Commission::STATUS_PENDING, Commission::STATUS_PAID])
            ->sum('amount');
    }
}
