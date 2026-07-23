<?php

namespace App\Services\Commission;

use App\Models\ActivityLog;
use App\Models\Commission;
use App\Models\Order;
use App\Models\User;
use App\Services\RankService;
use App\Services\WalletService;

/**
 * The payment tail shared by every commission payout, regardless of
 * which income module produced it: creates the Commission row, credits
 * the wallet, updates total earnings, logs the activity, and
 * re-evaluates rank. Used directly by any OrderTriggeredIncomeModule
 * that needs to record a payout, so this logic lives in exactly one
 * place instead of being duplicated per module.
 */
class CommissionPayoutRecorder
{
    public function __construct(
        private readonly WalletService $wallet,
        private readonly RankService $ranks,
    ) {}

    public function record(
        Order $order,
        User $upline,
        int $level,
        float $baseAmount,
        float $amount,
        float $percentage,
        float $rankMultiplier,
        string $planType,
        string $description,
        ?string $position = null,
        ?float $unitsMatched = null,
    ): Commission {
        $commission = Commission::create([
            'user_id' => $upline->id,
            'from_user_id' => $order->user_id,
            'order_id' => $order->id,
            'plan_type' => $planType,
            'base_amount' => $baseAmount,
            'amount' => $amount,
            'percentage' => $percentage,
            'rank_multiplier' => $rankMultiplier,
            'level' => $level,
            'position' => $position,
            'units_matched' => $unitsMatched,
            'status' => Commission::STATUS_PENDING,
            'description' => ucfirst($description)." from order {$order->order_number}",
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
            description: "User #{$upline->id} earned {$amount} ({$description}) from order #{$order->id}.",
            userId: $upline->id,
            new: ['commission_id' => $commission->id, 'amount' => $amount],
        );

        $this->ranks->evaluate($upline);

        return $commission;
    }
}
