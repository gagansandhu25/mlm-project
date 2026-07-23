<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CommissionConfigurationResource\Pages;
use App\Models\CommissionConfiguration;
use App\Services\Modules\PlanModule;
use App\Services\Modules\PlanModuleRegistry;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Covers only plan modules with no dedicatedSettingsPage() of their own
 * (Unilevel/Binary/Matrix today). A module whose config doesn't fit a
 * generic level/percentage row (e.g. a reorderable tier ladder with its
 * own qualifying-condition picker) declares its own dedicated page and
 * is deliberately excluded here — editing its rows through this generic
 * CRUD too would create a second, silently-overwritten source of truth
 * for the same data.
 */
class CommissionConfigurationResource extends Resource
{
    protected static ?string $model = CommissionConfiguration::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'Commission Engine';

    protected static ?string $navigationLabel = 'Commission Plans';

    /** @return array<string, string> plan_type => label, excluding modules with their own dedicated settings page */
    private static function planTypeOptions(): array
    {
        return app(PlanModuleRegistry::class)->all()
            ->reject(fn (PlanModule $module) => $module->dedicatedSettingsPage() !== null)
            ->mapWithKeys(fn (PlanModule $module) => [$module->key() => $module->label()])
            ->all();
    }

    public static function form(Form $form): Form
    {
        return $form
            ->columns(2)
            ->schema([
                Forms\Components\Select::make('plan_type')
                    ->options(fn () => self::planTypeOptions())
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
