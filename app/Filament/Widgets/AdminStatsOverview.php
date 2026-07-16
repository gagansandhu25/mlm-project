<?php

namespace App\Filament\Widgets;

use App\Models\Commission;
use App\Models\Order;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WithdrawalRequest;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AdminStatsOverview extends BaseWidget
{
    protected static ?int $sort = 1;

    protected int | string | array $columnSpan = 'full';

    protected function getStats(): array
    {
        $totalMembers = User::where('role', User::ROLE_USER)->count();

        $newThisMonth = User::where('role', User::ROLE_USER)
            ->where('join_date', '>=', now()->startOfMonth())
            ->count();

        $activeMembers = User::where('role', User::ROLE_USER)
            ->where('status', User::STATUS_ACTIVE)
            ->count();

        $salesThisMonth = (float) Order::where('status', Order::STATUS_COMPLETED)
            ->where('order_date', '>=', now()->startOfMonth())
            ->sum('amount');

        $commissionsThisMonth = (float) Commission::whereIn('status', [Commission::STATUS_PENDING, Commission::STATUS_PAID])
            ->where('calculated_at', '>=', now()->startOfMonth())
            ->sum('amount');

        $walletLiability = (float) Wallet::sum('balance');

        $pendingWithdrawalCount = WithdrawalRequest::where('status', WithdrawalRequest::STATUS_PENDING)->count();
        $pendingWithdrawalAmount = (float) WithdrawalRequest::where('status', WithdrawalRequest::STATUS_PENDING)->sum('amount');

        return [
            Stat::make('Total Members', number_format($totalMembers))
                ->description("+{$newThisMonth} this month")
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('primary'),

            Stat::make('Active Members', number_format($activeMembers))
                ->description($totalMembers > 0
                    ? round($activeMembers / $totalMembers * 100).'% of total'
                    : 'No members yet')
                ->color('success'),

            Stat::make('Sales This Month', '$'.number_format($salesThisMonth, 2))
                ->description('Completed orders')
                ->descriptionIcon('heroicon-m-shopping-cart')
                ->color('success'),

            Stat::make('Commissions This Month', '$'.number_format($commissionsThisMonth, 2))
                ->description('Accrued to members')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('warning'),

            Stat::make('Wallet Liability', '$'.number_format($walletLiability, 2))
                ->description('Total held across all member wallets')
                ->color('gray'),

            Stat::make('Pending Withdrawals', number_format($pendingWithdrawalCount))
                ->description('$'.number_format($pendingWithdrawalAmount, 2).' requested')
                ->descriptionIcon('heroicon-m-exclamation-circle')
                ->color($pendingWithdrawalCount > 0 ? 'danger' : 'success'),
        ];
    }
}
