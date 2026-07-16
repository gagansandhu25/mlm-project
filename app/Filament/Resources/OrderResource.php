<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OrderResource\Pages;
use App\Models\Order;
use App\Models\Product;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';

    protected static ?string $navigationGroup = 'Sales';

    public static function form(Form $form): Form
    {
        return $form
            ->columns(2)
            ->schema([
                Forms\Components\Select::make('user_id')
                    ->label('Buyer')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->required(),
                Forms\Components\Select::make('product_id')
                    ->relationship('product', 'name')
                    ->searchable()
                    ->required()
                    ->reactive()
                    ->afterStateUpdated(function ($state, callable $set) {
                        $product = Product::find($state);
                        if ($product) {
                            $set('amount', $product->price);
                            $set('commission_value', $product->commission_value);
                        }
                    }),
                Forms\Components\TextInput::make('order_number')
                    ->default(fn () => 'ORD-'.Str::upper(Str::random(10)))
                    ->required(),
                Forms\Components\TextInput::make('amount')->required()->numeric(),
                Forms\Components\TextInput::make('commission_value')->required()->numeric(),
                Forms\Components\Select::make('status')
                    ->options([
                        Order::STATUS_PENDING => 'Pending',
                        Order::STATUS_COMPLETED => 'Completed',
                        Order::STATUS_CANCELLED => 'Cancelled',
                        Order::STATUS_REFUNDED => 'Refunded',
                    ])
                    ->helperText('Setting this to Completed triggers the commission engine.')
                    ->default(Order::STATUS_PENDING)
                    ->required(),
                Forms\Components\DateTimePicker::make('order_date')->required()->default(now()),
                Forms\Components\TextInput::make('payment_method'),
                Forms\Components\Select::make('payment_status')
                    ->options([
                        Order::PAYMENT_STATUS_PENDING => 'Pending',
                        Order::PAYMENT_STATUS_COMPLETED => 'Completed',
                        Order::PAYMENT_STATUS_FAILED => 'Failed',
                        Order::PAYMENT_STATUS_REFUNDED => 'Refunded',
                    ])
                    ->default(Order::PAYMENT_STATUS_PENDING)
                    ->required(),
                Forms\Components\Toggle::make('commission_processed')->disabled(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('product.name')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('order_number')
                    ->searchable(),
                Tables\Columns\TextColumn::make('amount')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('commission_value')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        Order::STATUS_COMPLETED => 'success',
                        Order::STATUS_CANCELLED, Order::STATUS_REFUNDED => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('order_date')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('payment_method')
                    ->searchable(),
                Tables\Columns\TextColumn::make('payment_status')
                    ->searchable(),
                Tables\Columns\IconColumn::make('commission_processed')
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
                Tables\Filters\SelectFilter::make('status')->options([
                    Order::STATUS_PENDING => 'Pending',
                    Order::STATUS_COMPLETED => 'Completed',
                    Order::STATUS_CANCELLED => 'Cancelled',
                    Order::STATUS_REFUNDED => 'Refunded',
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
            'index' => Pages\ListOrders::route('/'),
            'create' => Pages\CreateOrder::route('/create'),
            'edit' => Pages\EditOrder::route('/{record}/edit'),
        ];
    }
}
