<?php

namespace App\Modules\Income\FixedYieldInvestment\Filament;

use App\Models\FixedYieldDailyAccrual;
use App\Modules\Income\FixedYieldInvestment\Filament\FixedYieldDailyAccrualResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Read-only: these rows are only ever created by
 * FixedYieldInvestmentService's daily run against a completed package
 * order, never by hand — this resource exists purely so an admin can
 * see the payout history, mirroring PersonalVolumeAccrualResource's
 * own list-only shape.
 */
class FixedYieldDailyAccrualResource extends Resource
{
    protected static ?string $model = FixedYieldDailyAccrual::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationGroup = 'Commission Engine';

    protected static ?string $navigationLabel = 'Fixed Yield Accruals';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Placeholder::make('investor')
                    ->content(fn (?FixedYieldDailyAccrual $record): ?string => $record?->order?->user?->name),
                Forms\Components\DatePicker::make('accrued_on')->disabled(),
                Forms\Components\TextInput::make('monthly_rate')->disabled(),
                Forms\Components\TextInput::make('base_amount')->disabled(),
                Forms\Components\TextInput::make('amount')->disabled(),
                Forms\Components\TextInput::make('status')->disabled(),
                Forms\Components\Textarea::make('description')->disabled()->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('order.user.name')
                    ->label('Investor')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('accrued_on')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('monthly_rate')
                    ->label('Monthly rate')
                    ->numeric()
                    ->suffix('%')
                    ->sortable(),
                Tables\Columns\TextColumn::make('base_amount')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('amount')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        FixedYieldDailyAccrual::STATUS_PAID => 'success',
                        FixedYieldDailyAccrual::STATUS_CANCELLED => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('accrued_on', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options([
                    FixedYieldDailyAccrual::STATUS_PENDING => 'Pending',
                    FixedYieldDailyAccrual::STATUS_PAID => 'Paid',
                    FixedYieldDailyAccrual::STATUS_CANCELLED => 'Cancelled',
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFixedYieldDailyAccruals::route('/'),
        ];
    }
}
