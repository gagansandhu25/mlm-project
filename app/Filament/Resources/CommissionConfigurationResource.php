<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CommissionConfigurationResource\Pages;
use App\Models\Commission;
use App\Models\CommissionConfiguration;
use App\Models\SystemSetting;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CommissionConfigurationResource extends Resource
{
    protected static ?string $model = CommissionConfiguration::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'Commission Engine';

    protected static ?string $navigationLabel = 'Commission Plans';

    public static function form(Form $form): Form
    {
        return $form
            ->columns(2)
            ->schema([
                Forms\Components\Select::make('plan_type')
                    ->options([
                        'unilevel' => 'Unilevel',
                        'binary' => 'Binary',
                        'matrix' => 'Matrix',
                        Commission::TYPE_PACKAGE_TIER => 'Package Tier',
                    ])
                    ->live()
                    ->required(),
                Forms\Components\TextInput::make('level')
                    ->helperText('Depth from the earner: 1 = direct upline.')
                    ->required()
                    ->numeric()
                    ->minValue(1),
                Forms\Components\TextInput::make('percentage')
                    ->label('Percentage (%)')
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(100)
                    ->suffix('%'),
                Forms\Components\TextInput::make('cap')
                    ->helperText('Maximum commission payable per period for this level. Leave blank for uncapped.')
                    ->numeric(),
                Forms\Components\Select::make('settings.cap_period')
                    ->label('Cap period')
                    ->options(['daily' => 'Daily', 'weekly' => 'Weekly', 'monthly' => 'Monthly'])
                    ->default('monthly'),
                Forms\Components\TextInput::make('settings.qualifying_amount')
                    ->label('Qualifying amount')
                    ->numeric()
                    ->minValue(0)
                    ->visible(fn (Get $get) => $get('plan_type') === Commission::TYPE_PACKAGE_TIER)
                    ->helperText(fn () => 'Minimum amount an upline must meet to earn this tier. Leave blank/0 for no condition. Compared against: '.match (SystemSetting::get('package_tier_condition_type', 'own_package')) {
                        'team_volume' => "the upline's team volume",
                        'buyer_package' => "the buyer's package value",
                        default => "the upline's own highest package purchase",
                    }.' (set on the Settings page).'),
                Forms\Components\Toggle::make('is_active')
                    ->default(true)
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('plan_type')
                    ->searchable(),
                Tables\Columns\TextColumn::make('level')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('percentage')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('cap')
                    ->numeric()
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
            'index' => Pages\ListCommissionConfigurations::route('/'),
            'create' => Pages\CreateCommissionConfiguration::route('/create'),
            'edit' => Pages\EditCommissionConfiguration::route('/{record}/edit'),
        ];
    }
}
