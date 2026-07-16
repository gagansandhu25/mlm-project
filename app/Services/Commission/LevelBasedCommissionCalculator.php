<?php

namespace App\Services\Commission;

use App\Models\ActivityLog;
use App\Models\Commission;
use App\Models\CommissionConfiguration;
use App\Models\Order;
use App\Models\User;
use App\Services\RankService;
use App\Services\TreeService;
use App\Services\WalletService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Shared engine for plan types that pay every qualifying upline a
 * level-percentage of the sale, scaled by rank multiplier and capped
 * per period. Unilevel and Matrix commissions are identical math —
 * only the tree shape (handled upstream by TreeService/placement)
 * and the plan_type/Commission type differ.
 */
abstract class LevelBasedCommissionCalculator implements CommissionCalculatorInterface
{
    public function __construct(
        protected readonly TreeService $tree,
        protected readonly WalletService $wallet,
        protected readonly RankService $ranks,
    ) {}

    abstract protected function planType(): string;

    abstract protected function commissionType(): string;

    public function calculate(Order $order): Collection
    {
        return DB::transaction(function () use ($order) {
            if ($order->commission_processed) {
                return new Collection;
            }

            $configs = CommissionConfiguration::query()
                ->where('plan_type', $this->planType())
                ->where('is_active', true)
                ->orderBy('level')
                ->get()
                ->keyBy('level');

            $maxLevel = (int) $configs->keys()->max();

            $created = new Collection;

            if ($maxLevel > 0) {
                $buyer = $order->user;
                $ancestorsByLevel = $this->tree->getAncestorsByLevel($buyer, $maxLevel);

                foreach ($ancestorsByLevel as $level => $upline) {
                    $commission = $this->payUpline($order, $upline, $level, $configs->get($level));

                    if ($commission !== null) {
                        $created->push($commission);
                    }
                }
            }

            $order->forceFill(['commission_processed' => true])->save();

            return $created;
        });
    }

    private function payUpline(Order $order, User $upline, int $level, ?CommissionConfiguration $config): ?Commission
    {
        if (! $config || $upline->status !== User::STATUS_ACTIVE) {
            return null;
        }

        $baseAmount = round((float) $order->commission_value * ((float) $config->percentage / 100), 2);

        if ($baseAmount <= 0) {
            return null;
        }

        $rankMultiplier = (float) ($upline->rank?->commission_multiplier ?? 1.0);
        $amount = round($baseAmount * $rankMultiplier, 2);

        if ($config->cap !== null) {
            $period = $config->settings['cap_period'] ?? 'monthly';
            $alreadyEarned = $this->periodCommissionSum($upline, $period);
            $remainingCap = max(0.0, (float) $config->cap - $alreadyEarned);

            if ($remainingCap <= 0) {
                return null;
            }

            $amount = min($amount, $remainingCap);
        }

        if ($amount <= 0) {
            return null;
        }

        $commission = Commission::create([
            'user_id' => $upline->id,
            'from_user_id' => $order->user_id,
            'order_id' => $order->id,
            'plan_type' => $this->commissionType(),
            'base_amount' => $baseAmount,
            'amount' => $amount,
            'percentage' => $config->percentage,
            'rank_multiplier' => $rankMultiplier,
            'level' => $level,
            'status' => Commission::STATUS_PENDING,
            'description' => ucfirst($this->planType())." level {$level} commission from order {$order->order_number}",
            'calculated_at' => now(),
        ]);

        $this->wallet->credit(
            user: $upline,
            amount: $amount,
            transactionType: 'commission',
            referenceId: $commission->id,
            referenceType: Commission::class,
            description: $commission->description,
        );

        $upline->forceFill(['total_earnings' => (float) $upline->total_earnings + $amount])->save();

        ActivityLog::log(
            action: 'commission.earned',
            description: "User #{$upline->id} earned {$amount} (level {$level} {$this->planType()}) from order #{$order->id}.",
            userId: $upline->id,
            new: ['commission_id' => $commission->id, 'amount' => $amount],
        );

        $this->ranks->evaluate($upline);

        return $commission;
    }

    private function periodCommissionSum(User $user, string $period): float
    {
        $start = match ($period) {
            'daily' => now()->startOfDay(),
            'weekly' => now()->startOfWeek(),
            default => now()->startOfMonth(),
        };

        return (float) Commission::query()
            ->where('user_id', $user->id)
            ->where('plan_type', $this->commissionType())
            ->where('calculated_at', '>=', $start)
            ->whereIn('status', [Commission::STATUS_PENDING, Commission::STATUS_PAID])
            ->sum('amount');
    }
}
