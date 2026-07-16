<?php

namespace App\Services;

use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;

class WalletService
{
    public function credit(
        User $user,
        float $amount,
        string $transactionType,
        ?int $referenceId = null,
        ?string $referenceType = null,
        ?string $description = null,
    ): WalletTransaction {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Credit amount must be positive.');
        }

        return DB::transaction(function () use ($user, $amount, $transactionType, $referenceId, $referenceType, $description) {
            $wallet = Wallet::query()->lockForUpdate()->firstOrCreate(['user_id' => $user->id], ['balance' => 0]);

            $before = (float) $wallet->balance;
            $wallet->balance = $before + $amount;
            $wallet->save();

            return WalletTransaction::create([
                'user_id' => $user->id,
                'amount' => $amount,
                'type' => WalletTransaction::TYPE_CREDIT,
                'transaction_type' => $transactionType,
                'status' => 'completed',
                'reference_id' => $referenceId,
                'reference_type' => $referenceType,
                'description' => $description,
                'balance_before' => $before,
                'balance_after' => $wallet->balance,
            ]);
        });
    }

    public function debit(
        User $user,
        float $amount,
        string $transactionType,
        ?int $referenceId = null,
        ?string $referenceType = null,
        ?string $description = null,
    ): WalletTransaction {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Debit amount must be positive.');
        }

        return DB::transaction(function () use ($user, $amount, $transactionType, $referenceId, $referenceType, $description) {
            $wallet = Wallet::query()->lockForUpdate()->firstOrCreate(['user_id' => $user->id], ['balance' => 0]);

            $before = (float) $wallet->balance;

            if ($before < $amount) {
                throw new \RuntimeException("Insufficient wallet balance for user #{$user->id}.");
            }

            $wallet->balance = $before - $amount;
            $wallet->save();

            return WalletTransaction::create([
                'user_id' => $user->id,
                'amount' => $amount,
                'type' => WalletTransaction::TYPE_DEBIT,
                'transaction_type' => $transactionType,
                'status' => 'completed',
                'reference_id' => $referenceId,
                'reference_type' => $referenceType,
                'description' => $description,
                'balance_before' => $before,
                'balance_after' => $wallet->balance,
            ]);
        });
    }
}
