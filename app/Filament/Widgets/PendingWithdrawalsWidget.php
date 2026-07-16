<?php

namespace App\Filament\Widgets;

use App\Models\WithdrawalRequest;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class PendingWithdrawalsWidget extends BaseWidget
{
    protected static ?int $sort = 4;

    protected int | string | array $columnSpan = 'full';

    protected static ?string $heading = 'Pending Withdrawal Requests';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                WithdrawalRequest::query()
                    ->where('status', WithdrawalRequest::STATUS_PENDING)
                    ->latest()
                    ->limit(10)
            )
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Member'),
                Tables\Columns\TextColumn::make('amount')
                    ->money('usd'),
                Tables\Columns\TextColumn::make('method')
                    ->label('Method'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Requested')
                    ->since(),
            ])
            ->paginated(false)
            ->emptyStateHeading('No pending withdrawal requests')
            ->emptyStateIcon('heroicon-o-check-circle');
    }
}
