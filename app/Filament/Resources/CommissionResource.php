<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CommissionResource\Pages;
use App\Models\Commission;
use App\Models\SystemSetting;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CommissionResource extends Resource
{
    protected static ?string $model = Commission::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('user_id')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->required(),
                Forms\Components\Select::make('from_user_id')
                    ->relationship('fromUser', 'name')
                    ->searchable()
                    ->required(),
                Forms\Components\Select::make('order_id')
                    ->relationship('order', 'id')
                    // Null for daily personal-volume commissions, which
                    // aren't generated from a single order.
                    ->disabled(fn (string $operation): bool => $operation === 'edit'),
                // Kept in the database (it drives cap-period sums per plan
                // and which fields below apply) but not admin-editable —
                // it's set by whichever calculator created the row.
                Forms\Components\Hidden::make('plan_type')
                    ->default(fn () => SystemSetting::get('active_plan_type', 'unilevel')),
                Forms\Components\TextInput::make('base_amount')
                    ->required()
                    ->numeric(),
                Forms\Components\TextInput::make('amount')
                    ->required()
                    ->numeric(),
                Forms\Components\TextInput::make('percentage')
                    ->required()
                    ->numeric(),
                Forms\Components\TextInput::make('rank_multiplier')
                    ->required()
                    ->numeric()
                    ->default(1)
                    ->visible(fn (callable $get) => $get('plan_type') !== Commission::TYPE_BINARY),
                Forms\Components\TextInput::make('level')
                    ->required()
                    ->numeric()
                    ->default(1)
                    // Unilevel/matrix pay per depth level; binary is a single
                    // pairing bonus and always stores level=1, so it isn't
                    // meaningful to edit here.
                    ->visible(fn (callable $get) => $get('plan_type') !== Commission::TYPE_BINARY),
                Forms\Components\TextInput::make('position')
                    // Left/right leg only exists for binary pairing.
                    ->visible(fn (callable $get) => $get('plan_type') === Commission::TYPE_BINARY),
                Forms\Components\Select::make('status')
                    ->options([
                        Commission::STATUS_PENDING => 'Pending',
                        Commission::STATUS_PAID => 'Paid',
                        Commission::STATUS_CANCELLED => 'Cancelled',
                    ])
                    ->required(),
                Forms\Components\Textarea::make('description')
                    ->columnSpanFull(),
                Forms\Components\DateTimePicker::make('calculated_at')
                    ->required(),
                Forms\Components\DateTimePicker::make('paid_at'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('fromUser.name')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('order.id')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('base_amount')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('amount')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('percentage')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('rank_multiplier')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('level')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('position')
                    ->searchable(),
                Tables\Columns\TextColumn::make('status')
                    ->searchable(),
                Tables\Columns\TextColumn::make('calculated_at')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('paid_at')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
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
            'index' => Pages\ListCommissions::route('/'),
            'create' => Pages\CreateCommission::route('/create'),
            'edit' => Pages\EditCommission::route('/{record}/edit'),
        ];
    }
}
