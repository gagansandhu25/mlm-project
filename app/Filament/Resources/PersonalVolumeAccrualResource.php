<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PersonalVolumeAccrualResource\Pages;
use App\Models\PersonalVolumeAccrual;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PersonalVolumeAccrualResource extends Resource
{
    protected static ?string $model = PersonalVolumeAccrual::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationLabel = 'Personal Volume Accruals';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('user_id')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->required()
                    // Who earned it and which day it's for are the
                    // record's identity (and the unique constraint) —
                    // not reassignable after the fact.
                    ->disabled(fn (string $operation): bool => $operation === 'edit'),
                Forms\Components\DatePicker::make('accrued_on')
                    ->required()
                    ->disabled(fn (string $operation): bool => $operation === 'edit'),
                Forms\Components\TextInput::make('sales_volume_snapshot')
                    ->label('Sales volume snapshot')
                    ->required()
                    ->numeric(),
                Forms\Components\TextInput::make('percentage')
                    ->required()
                    ->numeric()
                    ->suffix('%'),
                Forms\Components\TextInput::make('rank_multiplier')
                    ->required()
                    ->numeric()
                    ->default(1),
                Forms\Components\TextInput::make('base_amount')
                    ->required()
                    ->numeric(),
                Forms\Components\TextInput::make('amount')
                    ->required()
                    ->numeric(),
                Forms\Components\Select::make('status')
                    ->options([
                        PersonalVolumeAccrual::STATUS_PENDING => 'Pending',
                        PersonalVolumeAccrual::STATUS_PAID => 'Paid',
                        PersonalVolumeAccrual::STATUS_CANCELLED => 'Cancelled',
                    ])
                    ->required(),
                Forms\Components\DateTimePicker::make('paid_at'),
                Forms\Components\Textarea::make('description')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('accrued_on')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('sales_volume_snapshot')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('percentage')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('rank_multiplier')
                    ->numeric()
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
                        PersonalVolumeAccrual::STATUS_PAID => 'success',
                        PersonalVolumeAccrual::STATUS_CANCELLED => 'danger',
                        default => 'gray',
                    }),
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
            ->defaultSort('accrued_on', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options([
                    PersonalVolumeAccrual::STATUS_PENDING => 'Pending',
                    PersonalVolumeAccrual::STATUS_PAID => 'Paid',
                    PersonalVolumeAccrual::STATUS_CANCELLED => 'Cancelled',
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
            'index' => Pages\ListPersonalVolumeAccruals::route('/'),
            'create' => Pages\CreatePersonalVolumeAccrual::route('/create'),
            'edit' => Pages\EditPersonalVolumeAccrual::route('/{record}/edit'),
        ];
    }
}
