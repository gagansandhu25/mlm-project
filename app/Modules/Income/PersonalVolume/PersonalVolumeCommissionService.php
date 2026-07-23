<?php

namespace App\Modules\Income\PersonalVolume;

use App\Models\ActivityLog;
use App\Models\PersonalVolumeAccrual;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\RankService;
use App\Services\WalletService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Daily personal-volume commission: every active member with personal
 * volume earns a flat percentage of their *cumulative* sales_volume,
 * paid out once per day by the generic `income:run-scheduled` command
 * (via PersonalVolumeModule::run()) — not per order. Logged to its own
 * `personal_volume_accruals` table rather than `commissions`: it isn't a
 * network-tree payout (no from_user/level/position), and a dedicated
 * table lets us enforce one accrual per user per day at the database
 * level. Runs alongside whichever network plan (unilevel/binary/matrix)
 * is active. Gated by the `personal_volume_commission_enabled` system
 * setting.
 */
class PersonalVolumeCommissionService
{
    public function __construct(
        private readonly WalletService $wallet,
        private readonly RankService $ranks,
    ) {}

    /** @return Collection<int, PersonalVolumeAccrual> */
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
                    $accrual = $this->payUser($user, $percentage);

                    if ($accrual !== null) {
                        $created->push($accrual);
                    }
                }
            });

        return $created;
    }

    private function payUser(User $user, float $percentage): ?PersonalVolumeAccrual
    {
        return DB::transaction(function () use ($user, $percentage) {
            $today = now()->toDateString();

            // whereDate(), not where(): the `date` cast serializes accrued_on
            // as "Y-m-d 00:00:00" on write, which a plain string-equality
            // where() against "Y-m-d" would silently never match.
            $alreadyAccrued = PersonalVolumeAccrual::query()
                ->where('user_id', $user->id)
                ->whereDate('accrued_on', $today)
                ->exists();

            if ($alreadyAccrued) {
                return null;
            }

            $baseAmount = round((float) $user->sales_volume * ($percentage / 100), 2);

            if ($baseAmount <= 0) {
                return null;
            }

            $rankMultiplier = (float) ($user->rank?->commission_multiplier ?? 1.0);
            $amount = round($baseAmount * $rankMultiplier, 2);

            if ($amount <= 0) {
                return null;
            }

            $accrual = PersonalVolumeAccrual::create([
                'user_id' => $user->id,
                'accrued_on' => $today,
                'sales_volume_snapshot' => $user->sales_volume,
                'percentage' => $percentage,
                'rank_multiplier' => $rankMultiplier,
                'base_amount' => $baseAmount,
                'amount' => $amount,
                'status' => PersonalVolumeAccrual::STATUS_PENDING,
                'description' => 'Daily personal volume commission for '.$today,
            ]);

            $this->wallet->credit(
                user: $user,
                amount: $amount,
                transactionType: 'commission',
                referenceId: $accrual->id,
                referenceType: PersonalVolumeAccrual::class,
                description: $accrual->description,
            );

            $user->forceFill(['total_earnings' => (float) $user->total_earnings + $amount])->save();

            ActivityLog::log(
                action: 'personal_volume.accrued',
                description: "User #{$user->id} earned {$amount} (daily personal volume commission).",
                userId: $user->id,
                new: ['accrual_id' => $accrual->id, 'amount' => $amount],
            );

            $this->ranks->evaluate($user);

            return $accrual;
        });
    }
}
