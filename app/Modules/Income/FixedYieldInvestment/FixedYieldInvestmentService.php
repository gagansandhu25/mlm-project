<?php

namespace App\Modules\Income\FixedYieldInvestment;

use App\Models\ActivityLog;
use App\Models\FixedYieldDailyAccrual;
use App\Models\Order;
use App\Models\SystemSetting;
use App\Services\RankService;
use App\Services\WalletService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Daily fixed-yield commission: every completed package order is itself
 * a yield-earning principal — no separate investment record to create,
 * an order already is one. Each order earns a cash payout once per day,
 * computed from the buyer's *current* rank's monthly rate
 * (ranks.rank_commission_rate) — re-looked-up fresh every run, never
 * snapshotted at purchase time, so a rank-up immediately raises the
 * rate for all remaining days. Paid out once per day by the generic
 * `income:run-scheduled` command (via FixedYieldInvestmentModule::run()) —
 * not per order-completion event, same as Personal Volume. Logged to its
 * own `fixed_yield_daily_accruals` table rather than `commissions`: this
 * isn't a network-tree payout (no from_user/level/position, and the
 * buyer is paid for their own capital, not anyone else's purchase), and
 * a dedicated table lets us enforce one accrual per order per day at the
 * database level. Each order has its own independent 2x cap (order.amount
 * * cap multiplier) — once total paid reaches it, remainingCap recomputes
 * to zero and payOrder() simply stops creating rows for that order; there
 * is no persisted "capped_out" flag anywhere, matching every other cap in
 * this codebase (VolumeBasis/CountBasis/HybridBinaryMatching all
 * recompute remaining room from a sum rather than a stored status).
 * Gated by the `fixed_yield_investment_enabled` system setting.
 */
class FixedYieldInvestmentService
{
    public function __construct(
        private readonly WalletService $wallet,
        private readonly RankService $ranks,
    ) {}

    /** @return Collection<int, FixedYieldDailyAccrual> */
    public function runDaily(): Collection
    {
        if (! filter_var(SystemSetting::get('fixed_yield_investment_enabled', false), FILTER_VALIDATE_BOOLEAN)) {
            return new Collection;
        }

        $created = new Collection;

        Order::query()
            ->where('status', Order::STATUS_COMPLETED)
            ->whereHas('product', fn ($query) => $query->where('is_package', true))
            ->chunkById(200, function (Collection $orders) use (&$created) {
                foreach ($orders as $order) {
                    $accrual = $this->payOrder($order);

                    if ($accrual !== null) {
                        $created->push($accrual);
                    }
                }
            });

        return $created;
    }

    private function payOrder(Order $order): ?FixedYieldDailyAccrual
    {
        return DB::transaction(function () use ($order) {
            $today = now()->toDateString();

            // whereDate(), not where(): the `date` cast serializes accrued_on
            // as "Y-m-d 00:00:00" on write, which a plain string-equality
            // where() against "Y-m-d" would silently never match — same
            // gotcha as Personal Volume's own daily guard.
            $alreadyAccrued = FixedYieldDailyAccrual::query()
                ->where('order_id', $order->id)
                ->whereDate('accrued_on', $today)
                ->exists();

            if ($alreadyAccrued) {
                return null;
            }

            $user = $order->user;
            $investedAmount = (float) $order->amount; // the package's sale price — same figure every ActivePackageResolver reads for "package value"

            // Always the buyer's *current* rank — deliberately not
            // snapshotted at purchase time, so a rank-up mid-stream
            // raises the rate starting the very next day.
            $monthlyRate = (float) ($user->rank?->rank_commission_rate ?? 0);
            $dailyRate = $monthlyRate / 30;

            $baseAmount = round($investedAmount * ($dailyRate / 100), 2);

            if ($baseAmount <= 0) {
                return null;
            }

            $capMultiplier = (float) SystemSetting::get('fixed_yield_investment_cap_multiplier', 2);
            $capTotal = $investedAmount * $capMultiplier;

            $alreadyPaid = (float) FixedYieldDailyAccrual::query()
                ->where('order_id', $order->id)
                ->whereIn('status', [FixedYieldDailyAccrual::STATUS_PENDING, FixedYieldDailyAccrual::STATUS_PAID])
                ->sum('amount');

            $remainingCap = max(0.0, $capTotal - $alreadyPaid);
            $amount = min($baseAmount, $remainingCap);

            if ($amount <= 0) {
                return null;
            }

            $accrual = FixedYieldDailyAccrual::create([
                'order_id' => $order->id,
                'accrued_on' => $today,
                'monthly_rate' => $monthlyRate,
                'base_amount' => $baseAmount,
                'amount' => $amount,
                'status' => FixedYieldDailyAccrual::STATUS_PENDING,
                'description' => 'Daily fixed yield for '.$today,
            ]);

            $this->wallet->credit(
                user: $user,
                amount: $amount,
                transactionType: 'commission',
                referenceId: $accrual->id,
                referenceType: FixedYieldDailyAccrual::class,
                description: $accrual->description,
            );

            $user->forceFill(['total_earnings' => (float) $user->total_earnings + $amount])->save();

            ActivityLog::log(
                action: 'fixed_yield_investment.accrued',
                description: "User #{$user->id} earned {$amount} (daily fixed yield) from order #{$order->id}.",
                userId: $user->id,
                new: ['accrual_id' => $accrual->id, 'order_id' => $order->id, 'amount' => $amount],
            );

            $this->ranks->evaluate($user);

            return $accrual;
        });
    }
}
