<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SaleResource\Pages;
use App\Models\Sale;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SaleResource extends Resource
{
    protected static ?string $model = Sale::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';

    protected static ?string $navigationGroup = 'Sales';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Sale Information')
                    ->schema([
                        Forms\Components\TextInput::make('receipt_number')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(100),
                        Forms\Components\Select::make('store_id')
                            ->relationship('store', 'name')
                            ->required(),
                        Forms\Components\Select::make('terminal_id')
                            ->relationship('terminal', 'name')
                            ->required(),
                        Forms\Components\DatePicker::make('date')
                            ->required()
                            ->default(now()),
                        Forms\Components\TimePicker::make('time')
                            ->required()
                            ->default(now()),
                        Forms\Components\Select::make('cashier_id')
                            ->relationship('cashier', 'name')
                            ->required(),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('Customer Details')
                    ->schema([
                        Forms\Components\Select::make('customer_id')
                            ->relationship('customer', 'name')
                            ->searchable()
                            ->preload(),
                        Forms\Components\TextInput::make('customer_name')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('customer_phone')
                            ->tel()
                            ->maxLength(20),
                        Forms\Components\TextInput::make('customer_email')
                            ->email()
                            ->maxLength(255),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Payment Details')
                    ->schema([
                        Forms\Components\TextInput::make('subtotal')
                            ->required()
                            ->numeric()
                            ->prefix('₹')
                            ->step(0.01),
                        Forms\Components\TextInput::make('discount_amount')
                            ->numeric()
                            ->prefix('₹')
                            ->default(0)
                            ->step(0.01),
                        Forms\Components\TextInput::make('tax_amount')
                            ->required()
                            ->numeric()
                            ->prefix('₹')
                            ->step(0.01),
                        Forms\Components\TextInput::make('total_amount')
                            ->required()
                            ->numeric()
                            ->prefix('₹')
                            ->step(0.01),
                        Forms\Components\Select::make('payment_method')
                            ->required()
                            ->options([
                                'cash' => 'Cash',
                                'upi' => 'UPI',
                                'card' => 'Card',
                                'esewa' => 'eSewa',
                                'khalti' => 'Khalti',
                                'other' => 'Other',
                            ]),
                        Forms\Components\Select::make('payment_status')
                            ->required()
                            ->options([
                                'paid' => 'Paid',
                                'pending' => 'Pending',
                                'partial' => 'Partial',
                                'refunded' => 'Refunded',
                            ])
                            ->default('paid'),
                        Forms\Components\Select::make('status')
                            ->required()
                            ->options([
                                'completed' => 'Completed',
                                'cancelled' => 'Cancelled',
                                'refunded' => 'Refunded',
                            ])
                            ->default('completed'),
                    ])
                    ->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('receipt_number')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('store.name')
                    ->sortable(),
                Tables\Columns\TextColumn::make('cashier.name')
                    ->sortable(),
                Tables\Columns\TextColumn::make('customer_name')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('total_amount')
                    ->money('INR')
                    ->sortable(),
                Tables\Columns\TextColumn::make('payment_method')
                    ->badge()
                    ->colors([
                        'success' => 'cash',
                        'warning' => 'upi',
                        'info' => 'card',
                    ]),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'success' => 'completed',
                        'danger' => 'cancelled',
                        'warning' => 'refunded',
                    ]),
                Tables\Columns\IconColumn::make('is_synced')
                    ->boolean()
                    ->label('Synced'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('store')
                    ->relationship('store', 'name'),
                Tables\Filters\SelectFilter::make('payment_method')
                    ->options([
                        'cash' => 'Cash',
                        'upi' => 'UPI',
                        'card' => 'Card',
                    ]),
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'completed' => 'Completed',
                        'cancelled' => 'Cancelled',
                        'refunded' => 'Refunded',
                    ]),
                Tables\Filters\Filter::make('date')
                    ->form([
                        Forms\Components\DatePicker::make('from'),
                        Forms\Components\DatePicker::make('until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('date', '>=', $date),
                            )
                            ->when(
                                $data['until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('date', '<=', $date),
                            );
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('date', 'desc');
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
            'index' => Pages\ListSales::route('/'),
            'create' => Pages\CreateSale::route('/create'),
            'edit' => Pages\EditSale::route('/{record}/edit'),
        ];
    }
}
