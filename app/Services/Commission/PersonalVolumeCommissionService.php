<?php

namespace App\Services\Commission;

use App\Models\ActivityLog;
use App\Models\Commission;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\RankService;
use App\Services\WalletService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Daily personal-volume commission: every active member with personal
 * volume earns a flat percentage of their *cumulative* sales_volume,
 * paid out once per day by the scheduled `commission:personal-volume-daily`
 * command — not per order. This runs alongside whichever network plan
 * (unilevel/binary/matrix) is active. Gated by the
 * `personal_volume_commission_enabled` system setting. The percentage
 * is paid on the running total each day with no cap, by design — a
 * member's daily payout grows as their personal volume grows and
 * never stops while the feature is enabled.
 */
class PersonalVolumeCommissionService
{
    public function __construct(
        private readonly WalletService $wallet,
        private readonly RankService $ranks,
    ) {}

    /** @return Collection<int, Commission> */
    public function runDaily(): Collection
    {
        if (! filter_var(SystemSetting::get('personal_volume_commission_enabled', false), FILTER_VALIDATE_BOOLEAN)) {
            return new Collection;
        }

        $percentage = (float) SystemSetting::get('personal_volume_percentage', 0);

        if ($percentage <= 0) {
            return new Collection;
        }

        $created = new Collection;

        User::query()
            ->where('status', User::STATUS_ACTIVE)
            ->where('sales_volume', '>', 0)
            ->chunkById(200, function (Collection $users) use ($percentage, &$created) {
                foreach ($users as $user) {
                    $commission = $this->payUser($user, $percentage);

                    if ($commission !== null) {
                        $created->push($commission);
                    }
                }
            });

        return $created;
    }

    private function payUser(User $user, float $percentage): ?Commission
    {
        return DB::transaction(function () use ($user, $percentage) {
            $baseAmount = round((float) $user->sales_volume * ($percentage / 100), 2);
            if ($baseAmount <= 0) {
                return null;
            }

            $rankMultiplier = (float) ($user->rank?->commission_multiplier ?? 1.0);
            $amount = round($baseAmount * $rankMultiplier, 2);

            if ($amount <= 0) {
                return null;
            }

            $commission = Commission::create([
                'user_id' => $user->id,
                'from_user_id' => $user->id,
                'order_id' => null,
                'plan_type' => Commission::TYPE_PERSONAL,
                'base_amount' => $baseAmount,
                'amount' => $amount,
                'percentage' => $percentage,
                'rank_multiplier' => $rankMultiplier,
                'level' => 1,
                'status' => Commission::STATUS_PENDING,
                'description' => 'Daily personal volume commission for '.now()->toDateString(),
                'calculated_at' => now(),
            ]);

            $this->wallet->credit(
                user: $user,
                amount: $amount,
                transactionType: 'commission',
                referenceId: $commission->id,
                referenceType: Commission::class,
                description: $commission->description,
            );

            $user->forceFill(['total_earnings' => (float) $user->total_earnings + $amount])->save();

            ActivityLog::log(
                action: 'commission.earned',
                description: "User #{$user->id} earned {$amount} (daily personal volume commission).",
                userId: $user->id,
                new: ['commission_id' => $commission->id, 'amount' => $amount],
            );

            $this->ranks->evaluate($user);

            return $commission;
        });
    }
}
