<?php

namespace App\Services;

use App\Models\SystemSetting;
use App\Models\User;
use App\Models\WithdrawalRequest;
use Illuminate\Support\Facades\DB;

/**
 * Handles member withdrawal requests. The requested amount is debited
 * from the wallet immediately (so it can't be double-spent while the
 * request is pending) and held until an admin approves, rejects
 * (refunding the wallet), or completes the payout.
 */
class WithdrawalService
{
    public function __construct(private readonly WalletService $wallet) {}

    public function request(User $user, float $amount, string $method, array $accountDetails): WithdrawalRequest
    {
        $minimum = (float) SystemSetting::get('minimum_payout_threshold', 50);

        if ($amount < $minimum) {
            throw new \InvalidArgumentException("Minimum withdrawal amount is {$minimum}.");
        }

        $balance = (float) ($user->wallet?->balance ?? 0);

        if ($amount > $balance) {
            throw new \RuntimeException('Withdrawal amount exceeds available wallet balance.');
        }

        $feePercentage = (float) SystemSetting::get('withdrawal_fee_percentage', 0);
        $fee = round($amount * ($feePercentage / 100), 2);

        return DB::transaction(function () use ($user, $amount, $fee, $method, $accountDetails) {
            $withdrawal = WithdrawalRequest::create([
                'user_id' => $user->id,
                'amount' => $amount,
                'fee' => $fee,
                'method' => $method,
                'account_details' => $accountDetails,
                'status' => WithdrawalRequest::STATUS_PENDING,
            ]);

            $this->wallet->debit(
                user: $user,
                amount: $amount,
                transactionType: 'withdrawal',
                referenceId: $withdrawal->id,
                referenceType: WithdrawalRequest::class,
                description: "Withdrawal request #{$withdrawal->id} ({$method})",
            );

            return $withdrawal;
        });
    }
}
