<?php

namespace App\Services\Commission;

use App\Models\ActivityLog;
use App\Models\Commission;
use App\Models\CommissionConfiguration;
use App\Models\Order;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\RankService;
use App\Services\TreeService;
use App\Services\WalletService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Binary commissions: a pairing/matching bonus. Every completed order
 * credits the buyer's sale volume to the correct left/right leg of
 * each upline (whichever side the buyer descends from). Whenever an
 * upline's two legs both carry volume, the smaller side is "matched"
 * and the upline is paid a percentage of the matched volume. Volume
 * that goes unpaid because of a per-period cap carries forward and is
 * eligible to match again on a future order.
 */
class BinaryCommissionCalculator implements CommissionCalculatorInterface
{
    public function __construct(
        private readonly TreeService $tree,
        private readonly WalletService $wallet,
        private readonly RankService $ranks,
    ) {}

    public function planType(): string
    {
        return Commission::TYPE_BINARY;
    }

    public function calculate(Order $order): Collection
    {
        return DB::transaction(function () use ($order) {
            if ($order->commission_processed) {
                return new Collection;
            }

            $created = new Collection;

            $config = CommissionConfiguration::query()
                ->where('plan_type', 'binary')
                ->where('level', 1)
                ->where('is_active', true)
                ->first();

            $percentage = (float) ($config?->percentage ?? SystemSetting::get('binary_pair_percentage', 10));

            foreach ($this->creditedUplines($order->user) as [$upline, $side]) {
                $commission = $this->payUpline($order, $upline, $side, $config, $percentage);

                if ($commission !== null) {
                    $created->push($commission);
                }
            }

            $order->forceFill(['commission_processed' => true])->save();

            return $created;
        });
    }

    /**
     * Ancestors of the buyer paired with which leg (left/right) of that
     * ancestor the buyer's branch descends through, closest ancestor first.
     *
     * @return list<array{0: User, 1: string}>
     */
    private function creditedUplines(User $buyer): array
    {
        $chain = $this->tree->getAncestors($buyer)->reverse()->values(); // closest-first
        $child = $buyer;

        $pairs = [];
        foreach ($chain as $ancestor) {
            if ($child->position === User::POSITION_LEFT || $child->position === User::POSITION_RIGHT) {
                $pairs[] = [$ancestor, $child->position];
            }

            $child = $ancestor;
        }

        return $pairs;
    }

    private function payUpline(Order $order, User $upline, string $side, ?CommissionConfiguration $config, float $percentage): ?Commission
    {
        if ($upline->status !== User::STATUS_ACTIVE) {
            return null;
        }

        $column = $side === User::POSITION_LEFT ? 'left_volume' : 'right_volume';
        $upline->forceFill([$column => (float) $upline->{$column} + (float) $order->commission_value])->save();

        $matchedVolume = min((float) $upline->left_volume, (float) $upline->right_volume);

        if ($matchedVolume <= 0 || $percentage <= 0) {
            return null;
        }

        $baseAmount = round($matchedVolume * ($percentage / 100), 2);

        if ($baseAmount <= 0) {
            return null;
        }

        $rankMultiplier = (float) ($upline->rank?->commission_multiplier ?? 1.0);
        $originalAmount = round($baseAmount * $rankMultiplier, 2);
        $amount = $originalAmount;

        if ($config?->cap !== null) {
            $period = $config->settings['cap_period'] ?? 'monthly';
            $alreadyEarned = $this->periodCommissionSum($upline, $period);
            $remainingCap = max(0.0, (float) $config->cap - $alreadyEarned);
            $amount = min($amount, $remainingCap);
        }

        if ($amount <= 0) {
            return null;
        }

        // Only the volume proportional to what was actually paid is
        // consumed; whatever a cap withheld carries forward unmatched.
        $volumeConsumed = min($matchedVolume, $matchedVolume * ($amount / $originalAmount));

        $upline->forceFill([
            'left_volume' => (float) $upline->left_volume - $volumeConsumed,
            'right_volume' => (float) $upline->right_volume - $volumeConsumed,
        ])->save();

        $commission = Commission::create([
            'user_id' => $upline->id,
            'from_user_id' => $order->user_id,
            'order_id' => $order->id,
            'plan_type' => Commission::TYPE_BINARY,
            'base_amount' => $baseAmount,
            'amount' => $amount,
            'percentage' => $percentage,
            'rank_multiplier' => $rankMultiplier,
            'level' => 1,
            'position' => $side,
            'status' => Commission::STATUS_PENDING,
            'description' => "Binary pairing bonus from order {$order->order_number}",
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
            description: "User #{$upline->id} earned {$amount} (binary pairing bonus) from order #{$order->id}.",
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
            ->where('plan_type', Commission::TYPE_BINARY)
            ->where('calculated_at', '>=', $start)
            ->whereIn('status', [Commission::STATUS_PENDING, Commission::STATUS_PAID])
            ->sum('amount');
    }
}
