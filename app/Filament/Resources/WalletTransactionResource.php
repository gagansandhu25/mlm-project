<?php

namespace App\Filament\Resources;

use App\Filament\Resources\WalletTransactionResource\Pages;
use App\Models\WalletTransaction;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class WalletTransactionResource extends Resource
{
    protected static ?string $model = WalletTransaction::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('user_id')
                    ->relationship('user', 'name')
                    ->required(),
                Forms\Components\TextInput::make('amount')
                    ->required()
                    ->numeric(),
                Forms\Components\TextInput::make('type')
                    ->required(),
                Forms\Components\TextInput::make('transaction_type')
                    ->required(),
                Forms\Components\Select::make('status')
                    ->options([
                        WalletTransaction::STATUS_PENDING => 'Pending',
                        WalletTransaction::STATUS_COMPLETED => 'Completed',
                        WalletTransaction::STATUS_FAILED => 'Failed',
                        WalletTransaction::STATUS_CANCELLED => 'Cancelled',
                    ])
                    ->default(WalletTransaction::STATUS_COMPLETED)
                    ->required(),
                Forms\Components\TextInput::make('reference_id')
                    ->numeric(),
                Forms\Components\TextInput::make('reference_type'),
                Forms\Components\Textarea::make('description')
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('balance_before')
                    ->required()
                    ->numeric(),
                Forms\Components\TextInput::make('balance_after')
                    ->required()
                    ->numeric(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('amount')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('type')
                    ->searchable(),
                Tables\Columns\TextColumn::make('transaction_type')
                    ->searchable(),
                Tables\Columns\TextColumn::make('status')
                    ->searchable(),
                Tables\Columns\TextColumn::make('reference_id')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('reference_type')
                    ->searchable(),
                Tables\Columns\TextColumn::make('balance_before')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('balance_after')
                    ->numeric()
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
            'index' => Pages\ListWalletTransactions::route('/'),
            'create' => Pages\CreateWalletTransaction::route('/create'),
            'edit' => Pages\EditWalletTransaction::route('/{record}/edit'),
        ];
    }
}
