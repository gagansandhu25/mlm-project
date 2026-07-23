<?php

namespace App\Modules\Income\FixedYieldInvestment\Filament;

use App\Models\FixedYieldInvestment;
use App\Models\SystemSetting;
use App\Modules\Income\FixedYieldInvestment\Filament\FixedYieldInvestmentResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * How a fixed-yield investment actually gets created: there's no live
 * customer-facing purchase flow anywhere in this app (every Order today
 * is seeded/test data, not a real storefront), so an admin records a
 * member's investment here directly. The daily scheduled command then
 * picks up every `active` row and pays it — see FixedYieldInvestmentService.
 */
class FixedYieldInvestmentResource extends Resource
{
    protected static ?string $model = FixedYieldInvestment::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'Commission Engine';

    protected static ?string $navigationLabel = 'Fixed Yield Investments';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('user_id')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->required(),
                Forms\Components\TextInput::make('invested_amount')
                    ->label('Invested amount')
                    ->required()
                    ->numeric()
                    ->minValue(0.01)
                    ->prefix('$'),
                Forms\Components\DatePicker::make('invested_at')
                    ->required()
                    ->default(now()),
                Forms\Components\Select::make('status')
                    ->options([
                        FixedYieldInvestment::STATUS_ACTIVE => 'Active',
                        FixedYieldInvestment::STATUS_CAPPED_OUT => 'Capped out',
                        FixedYieldInvestment::STATUS_CANCELLED => 'Cancelled',
                    ])
                    ->required()
                    ->default(FixedYieldInvestment::STATUS_ACTIVE),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('invested_amount')
                    ->label('Invested')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('invested_at')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('paid_to_date')
                    ->label('Paid to date')
                    ->state(fn (FixedYieldInvestment $record): float => (float) $record->dailyAccruals()
                        ->whereIn('status', ['pending', 'paid'])
                        ->sum('amount'))
                    ->numeric(),
                Tables\Columns\TextColumn::make('cap_remaining')
                    ->label('Cap remaining')
                    ->state(function (FixedYieldInvestment $record): float {
                        $capMultiplier = (float) SystemSetting::get('fixed_yield_investment_cap_multiplier', 2);
                        $paid = (float) $record->dailyAccruals()->whereIn('status', ['pending', 'paid'])->sum('amount');

                        return max(0.0, ((float) $record->invested_amount * $capMultiplier) - $paid);
                    })
                    ->numeric(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        FixedYieldInvestment::STATUS_ACTIVE => 'success',
                        FixedYieldInvestment::STATUS_CAPPED_OUT => 'gray',
                        FixedYieldInvestment::STATUS_CANCELLED => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('invested_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options([
                    FixedYieldInvestment::STATUS_ACTIVE => 'Active',
                    FixedYieldInvestment::STATUS_CAPPED_OUT => 'Capped out',
                    FixedYieldInvestment::STATUS_CANCELLED => 'Cancelled',
                ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
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
            'index' => Pages\ListFixedYieldInvestments::route('/'),
            'create' => Pages\CreateFixedYieldInvestment::route('/create'),
            'edit' => Pages\EditFixedYieldInvestment::route('/{record}/edit'),
        ];
    }
}
