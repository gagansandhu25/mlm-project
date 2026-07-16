<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Hash;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'User Management';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Account')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('name')->required(),
                        Forms\Components\TextInput::make('email')->email()->required()->unique(ignoreRecord: true),
                        Forms\Components\TextInput::make('phone')->tel(),
                        Forms\Components\TextInput::make('password')
                            ->password()
                            ->dehydrateStateUsing(fn (string $state) => Hash::make($state))
                            ->dehydrated(fn (?string $state) => filled($state))
                            ->required(fn (string $operation) => $operation === 'create'),
                        Forms\Components\Select::make('role')
                            ->options([
                                User::ROLE_SUPER_ADMIN => 'Super Admin',
                                User::ROLE_ADMIN => 'Admin',
                                User::ROLE_USER => 'User',
                            ])
                            ->default(User::ROLE_USER)
                            ->required(),
                        Forms\Components\Select::make('status')
                            ->options([
                                User::STATUS_ACTIVE => 'Active',
                                User::STATUS_INACTIVE => 'Inactive',
                                User::STATUS_SUSPENDED => 'Suspended',
                            ])
                            ->default(User::STATUS_ACTIVE)
                            ->required(),
                    ]),
                Forms\Components\Section::make('Genealogy')
                    ->columns(2)
                    ->schema([
                        Forms\Components\Select::make('sponsor_id')
                            ->relationship('sponsor', 'name')
                            ->searchable()
                            ->helperText('Who referred this user (used to generate the referral chain).'),
                        Forms\Components\Select::make('rank_id')
                            ->relationship('rank', 'name'),
                        Forms\Components\TextInput::make('referral_code')->disabled(),
                        Forms\Components\TextInput::make('depth')->disabled()->numeric()
                            ->helperText('Managed automatically by TreeService — do not edit.'),
                        Forms\Components\TextInput::make('position')->disabled(),
                    ]),
                Forms\Components\Section::make('Performance')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('sales_volume')->numeric()->default(0)->disabled(),
                        Forms\Components\TextInput::make('total_earnings')->numeric()->default(0)->disabled(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\TextColumn::make('email')->searchable(),
                Tables\Columns\TextColumn::make('role')->badge()->searchable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        User::STATUS_ACTIVE => 'success',
                        User::STATUS_SUSPENDED => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('sponsor.name')->label('Sponsor')->sortable(),
                Tables\Columns\TextColumn::make('rank.name')->label('Rank')->sortable(),
                Tables\Columns\TextColumn::make('depth')->numeric()->sortable(),
                Tables\Columns\TextColumn::make('referral_code')->searchable()->copyable(),
                Tables\Columns\TextColumn::make('sales_volume')->numeric()->sortable(),
                Tables\Columns\TextColumn::make('total_earnings')->numeric()->sortable(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('role')->options([
                    User::ROLE_SUPER_ADMIN => 'Super Admin',
                    User::ROLE_ADMIN => 'Admin',
                    User::ROLE_USER => 'User',
                ]),
                Tables\Filters\SelectFilter::make('status')->options([
                    User::STATUS_ACTIVE => 'Active',
                    User::STATUS_INACTIVE => 'Inactive',
                    User::STATUS_SUSPENDED => 'Suspended',
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
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
