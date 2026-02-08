<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SaleResource\Pages;
use App\Models\Sale;
use App\Services\OrganizationContext;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Support\Colors\Color;
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
                            ->unique(ignoreRecord: true, modifyRuleUsing: fn ($rule, $get) => $rule->where('organization_id', $get('organization_id') ?? OrganizationContext::getCurrentOrganizationId()))
                            ->maxLength(100),
                        Forms\Components\Select::make('store_id')
                            ->relationship('store', 'name')
                            ->required(),
                        Forms\Components\Select::make('terminal_id')
                            ->relationship('terminal', 'name')
                            ->required(),
                        Forms\Components\DatePicker::make('date')
                            ->required()
                            ->default(now())
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->maxDate(now())
                            ->helperText('Sale transaction date'),
                        Forms\Components\TimePicker::make('time')
                            ->required()
                            ->default(now())
                            ->native(false)
                            ->seconds(false)
                            ->helperText('Sale transaction time'),
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
                            ->preload()
                            ->live()
                            ->afterStateUpdated(function ($state, Forms\Set $set) {
                                if ($state) {
                                    $customer = \App\Models\Customer::find($state);
                                    if ($customer) {
                                        $set('customer_phone', $customer->phone);
                                        $set('customer_email', $customer->email);
                                    }
                                }
                            })
                            ->helperText('Select an existing customer or enter details manually'),
                        Forms\Components\TextInput::make('customer_phone')
                            ->tel()
                            ->maxLength(20)
                            ->helperText('Phone number for receipt'),
                        Forms\Components\TextInput::make('customer_email')
                            ->email()
                            ->maxLength(255)
                            ->helperText('Email for receipt'),
                    ])
                    ->columns(3),

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
                                'upi' => 'QR',
                                'esewa' => 'eSewa',
                                'khalti' => 'Khalti',
                                'split' => 'Split Payment',
                                'other' => 'Other',
                            ])
                            ->live()
                            ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                                if ($state === 'upi' || $state === 'esewa' || $state === 'khalti') {
                                    $totalAmount = $get('total_amount');
                                    $set('amount_paid', $totalAmount);
                                    $set('amount_change', 0);
                                }
                            }),
                        Forms\Components\TextInput::make('amount_paid')
                            ->label('Amount Paid')
                            ->numeric()
                            ->prefix('₹')
                            ->step(0.01)
                            ->live(debounce: 500)
                            ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                                $totalAmount = (float) ($get('total_amount') ?? 0);
                                $amountPaid = (float) ($state ?? 0);
                                $change = $amountPaid - $totalAmount;
                                $set('amount_change', $change >= 0 ? $change : 0);
                            })
                            ->helperText('Amount received from customer')
                            ->required(),
                        Forms\Components\TextInput::make('amount_change')
                            ->label('Change to Return')
                            ->numeric()
                            ->prefix('₹')
                            ->step(0.01)
                            ->readOnly()
                            ->helperText('Change to be given back to customer')
                            ->extraAttributes(['class' => 'font-bold'])
                            ->suffix(fn (Forms\Get $get) => (float)($get('amount_change') ?? 0) > 0 ? '💰 Return this amount' : ''),
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
            ->modifyQueryUsing(function ($query) {
                $orgId = OrganizationContext::getCurrentOrganizationId() 
                    ?? auth()->user()?->organization_id ?? 1;
                return $query->where('organization_id', $orgId);
            })
            ->columns([
                Tables\Columns\TextColumn::make('receipt_number')
                    ->searchable()
                    ->sortable()
                    ->label('Receipt #'),
                Tables\Columns\TextColumn::make('date')
                    ->date('d-M-Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('customer.name')
                    ->label('Customer')
                    ->searchable()
                    ->sortable()
                    ->default('Walk-in')
                    ->description(fn (Sale $record): ?string => $record->customer_phone ?? $record->customer?->phone),
                Tables\Columns\TextColumn::make('items_count')
                    ->label('Items')
                    ->counts('items')
                    ->alignCenter()
                    ->badge()
                    ->color('info'),
                Tables\Columns\TextColumn::make('total_amount')
                    ->money('INR')
                    ->sortable(),
                Tables\Columns\TextColumn::make('payment_method')
                    ->badge()
                    ->colors([
                        'success' => 'cash',
                        'warning' => 'upi',
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
                Tables\Filters\SelectFilter::make('customer_id')
                    ->label('Customer')
                    ->relationship('customer', 'name')
                    ->searchable()
                    ->preload(),
                    
                Tables\Filters\SelectFilter::make('store')
                    ->relationship('store', 'name'),
                Tables\Filters\SelectFilter::make('payment_method')
                    ->options([
                        'cash' => 'Cash',
                        'upi' => 'QR',
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
                ->defaultSort('created_at', 'desc');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Sale Information')
                    ->schema([
                        Infolists\Components\TextEntry::make('receipt_number')
                            ->label('Receipt #')
                            ->copyable()
                            ->icon('heroicon-m-document-text'),
                        Infolists\Components\TextEntry::make('invoice_number')
                            ->label('Invoice #')
                            ->copyable()
                            ->badge()
                            ->color('info')
                            ->icon('heroicon-m-document-duplicate'),
                        Infolists\Components\TextEntry::make('date')
                            ->date('d M Y')
                            ->icon('heroicon-m-calendar'),
                        Infolists\Components\TextEntry::make('time')
                            ->time('h:i A')
                            ->icon('heroicon-m-clock'),
                        Infolists\Components\TextEntry::make('store.name')
                            ->icon('heroicon-m-building-storefront'),
                        Infolists\Components\TextEntry::make('terminal.name')
                            ->icon('heroicon-m-computer-desktop'),
                        Infolists\Components\TextEntry::make('cashier.name')
                            ->icon('heroicon-m-user'),
                    ])
                    ->columns(5)
                    ->compact(),

                Infolists\Components\Section::make('Customer Information')
                    ->schema([
                        Infolists\Components\TextEntry::make('customer.name')
                            ->default('Walk-in Customer')
                            ->icon('heroicon-m-user-circle'),
                        Infolists\Components\TextEntry::make('customer.customer_code')
                            ->label('Customer Code')
                            ->badge()
                            ->color('gray')
                            ->icon('heroicon-m-identification')
                            ->default('N/A'),
                        Infolists\Components\TextEntry::make('customer_phone')
                            ->icon('heroicon-m-phone')
                            ->default('N/A'),
                        Infolists\Components\TextEntry::make('customer_email')
                            ->icon('heroicon-m-envelope')
                            ->default('N/A'),
                        Infolists\Components\TextEntry::make('customer.loyalty_points')
                            ->label('Loyalty Points')
                            ->badge()
                            ->color('success')
                            ->default('N/A'),
                    ])
                    ->columns(3)
                    ->compact(),

                Infolists\Components\Section::make('Products Purchased')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('items')
                            ->label('')
                            ->schema([
                                Infolists\Components\TextEntry::make('product_name')
                                    ->label('Product')
                                    ->weight('semibold')
                                    ->size('sm'),
                                Infolists\Components\TextEntry::make('product_sku')
                                    ->label('SKU')
                                    ->badge()
                                    ->color('gray')
                                    ->size('xs'),
                                Infolists\Components\TextEntry::make('quantity')
                                    ->label('Qty')
                                    ->badge()
                                    ->color('info'),
                                Infolists\Components\TextEntry::make('price_per_unit')
                                    ->label('Unit Price')
                                    ->money('INR')
                                    ->size('sm'),
                                Infolists\Components\TextEntry::make('tax_amount')
                                    ->label('Tax')
                                    ->money('INR')
                                    ->size('sm')
                                    ->color('warning'),
                                Infolists\Components\TextEntry::make('subtotal')
                                    ->label('Total')
                                    ->money('INR')
                                    ->weight('bold')
                                    ->color('success'),
                            ])
                            ->columns(6)
                            ->columnSpanFull(),
                    ])
                    ->compact(),

                Infolists\Components\Section::make('Payment Summary')
                    ->schema([
                        Infolists\Components\TextEntry::make('subtotal')
                            ->money('INR'),
                        Infolists\Components\TextEntry::make('discount_amount')
                            ->money('INR')
                            ->color('danger'),
                        Infolists\Components\TextEntry::make('tax_amount')
                            ->money('INR')
                            ->color('warning'),
                        Infolists\Components\TextEntry::make('total_amount')
                            ->money('INR')
                            ->weight('bold')
                            ->size('lg')
                            ->color('success'),
                    ])
                    ->columns(4)
                    ->compact(),

                Infolists\Components\Section::make('Payment Details')
                    ->schema([
                        Infolists\Components\TextEntry::make('payment_method')
                            ->badge()
                            ->colors([
                                'success' => 'cash',
                                'warning' => 'upi',
                                'info' => 'card',
                            ]),
                        Infolists\Components\TextEntry::make('status')
                            ->badge()
                            ->colors([
                                'success' => 'completed',
                                'danger' => 'cancelled',
                                'warning' => 'refunded',
                            ]),
                        Infolists\Components\TextEntry::make('amount_paid')
                            ->label('Amount Paid')
                            ->money('INR')
                            ->default('N/A'),
                        Infolists\Components\TextEntry::make('amount_change')
                            ->label('Change Returned')
                            ->money('INR')
                            ->badge()
                            ->color(fn ($state) => $state > 0 ? 'success' : 'gray')
                            ->icon(fn ($state) => $state > 0 ? 'heroicon-m-banknotes' : null)
                            ->default('₹0.00'),
                    ])
                    ->columns(4)
                    ->compact(),
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
            'index' => Pages\ListSales::route('/'),
            'create' => Pages\CreateSale::route('/create'),
            'view' => Pages\ViewSale::route('/{record}'),
            'edit' => Pages\EditSale::route('/{record}/edit'),
        ];
    }
}
