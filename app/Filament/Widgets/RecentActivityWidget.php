<?php

namespace App\Filament\Widgets;

use App\Models\ActivityLog;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class RecentActivityWidget extends BaseWidget
{
    protected static ?int $sort = 5;

    protected int | string | array $columnSpan = 'full';

    protected static ?string $heading = 'Recent Activity';

    public function table(Table $table): Table
    {
        return $table
            ->query(ActivityLog::query()->latest()->limit(10))
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('User')
                    ->default('System'),
                Tables\Columns\TextColumn::make('action')
                    ->badge(),
                Tables\Columns\TextColumn::make('description')
                    ->wrap()
                    ->limit(80),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('When')
                    ->since(),
            ])
            ->paginated(false)
            ->emptyStateHeading('No activity yet');
    }
}
