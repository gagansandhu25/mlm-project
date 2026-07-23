<?php

namespace App\Modules\Income\FixedYieldInvestment;

use App\Models\ActivityLog;
use App\Models\FixedYieldDailyAccrual;
use App\Models\FixedYieldInvestment;
use App\Models\SystemSetting;
use App\Services\RankService;
use App\Services\WalletService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Daily fixed-yield commission: every active FixedYieldInvestment earns a
 * cash payout once per day, computed from the investor's *current* rank's
 * monthly rate (ranks.rank_commission_rate) — re-looked-up fresh every run,
 * never snapshotted at investment time, so a rank-up immediately raises the
 * rate for all remaining days. Paid out once per day by the generic
 * `income:run-scheduled` command (via FixedYieldInvestmentModule::run()) —
 * not per order, same as Personal Volume. Logged to its own
 * `fixed_yield_daily_accruals` table rather than `commissions`: this isn't
 * a network-tree payout (no from_user/level/position, and the investor is
 * paid for their own capital, not anyone else's purchase), and a dedicated
 * table lets us enforce one accrual per investment per day at the database
 * level. Each investment has its own independent 2x cap (invested_amount *
 * cap multiplier) — once total paid reaches it, the investment is marked
 * capped_out and stops accruing. Gated by the
 * `fixed_yield_investment_enabled` system setting.
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

        FixedYieldInvestment::query()
            ->where('status', FixedYieldInvestment::STATUS_ACTIVE)
            ->chunkById(200, function (Collection $investments) use (&$created) {
                foreach ($investments as $investment) {
                    $accrual = $this->payInvestment($investment);

                    if ($accrual !== null) {
                        $created->push($accrual);
                    }
                }
            });

        return $created;
    }

    private function payInvestment(FixedYieldInvestment $investment): ?FixedYieldDailyAccrual
    {
        return DB::transaction(function () use ($investment) {
            $today = now()->toDateString();

            // whereDate(), not where(): the `date` cast serializes accrued_on
            // as "Y-m-d 00:00:00" on write, which a plain string-equality
            // where() against "Y-m-d" would silently never match — same
            // gotcha as Personal Volume's own daily guard.
            $alreadyAccrued = FixedYieldDailyAccrual::query()
                ->where('investment_id', $investment->id)
                ->whereDate('accrued_on', $today)
                ->exists();

            if ($alreadyAccrued) {
                return null;
            }

            $user = $investment->user;
            $investedAmount = (float) $investment->invested_amount;

            // Always the investor's *current* rank — deliberately not
            // snapshotted at investment time, so a rank-up mid-investment
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
                ->where('investment_id', $investment->id)
                ->whereIn('status', [FixedYieldDailyAccrual::STATUS_PENDING, FixedYieldDailyAccrual::STATUS_PAID])
                ->sum('amount');

            $remainingCap = max(0.0, $capTotal - $alreadyPaid);
            $amount = min($baseAmount, $remainingCap);

            if ($amount <= 0) {
                $investment->forceFill(['status' => FixedYieldInvestment::STATUS_CAPPED_OUT])->save();

                return null;
            }

            $accrual = FixedYieldDailyAccrual::create([
                'investment_id' => $investment->id,
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
                description: "User #{$user->id} earned {$amount} (daily fixed yield) from investment #{$investment->id}.",
                userId: $user->id,
                new: ['accrual_id' => $accrual->id, 'investment_id' => $investment->id, 'amount' => $amount],
            );

            // The cap was hit exactly on this payout — no more room for
            // future days, so mark it capped_out now rather than waiting
            // for a subsequent run to discover zero room remains.
            if ($amount >= $remainingCap) {
                $investment->forceFill(['status' => FixedYieldInvestment::STATUS_CAPPED_OUT])->save();
            }

            $this->ranks->evaluate($user);

            return $accrual;
        });
    }
}
