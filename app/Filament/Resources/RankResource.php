<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RankResource\Pages;
use App\Models\Rank;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class RankResource extends Resource
{
    protected static ?string $model = Rank::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required(),
                Forms\Components\TextInput::make('level')
                    ->required()
                    ->numeric(),
                Forms\Components\TextInput::make('icon'),
                Forms\Components\TextInput::make('min_sales_volume')
                    ->required()
                    ->numeric()
                    ->default(0),
                Forms\Components\TextInput::make('min_downline')
                    ->required()
                    ->numeric()
                    ->default(0),
                Forms\Components\TextInput::make('commission_multiplier')
                    ->required()
                    ->numeric()
                    ->default(1),
                Forms\Components\TextInput::make('rank_commission_rate')
                    ->label('Fixed yield monthly rate')
                    ->helperText('Used by the Fixed Yield Investment bonus — the monthly rate this rank earns on invested capital.')
                    ->required()
                    ->numeric()
                    ->suffix('%')
                    ->default(0),
                Forms\Components\Toggle::make('is_active')
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('level')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('icon')
                    ->searchable(),
                Tables\Columns\TextColumn::make('min_sales_volume')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('min_downline')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('commission_multiplier')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('rank_commission_rate')
                    ->label('Fixed yield monthly rate')
                    ->numeric()
                    ->suffix('%')
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean(),
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
            'index' => Pages\ListRanks::route('/'),
            'create' => Pages\CreateRank::route('/create'),
            'edit' => Pages\EditRank::route('/{record}/edit'),
        ];
    }
}
