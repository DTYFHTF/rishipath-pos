<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PurchaseResource\Pages;
use App\Models\Purchase;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\Supplier;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class PurchaseResource extends Resource
{
    protected static ?string $model = Purchase::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';

    protected static ?string $navigationGroup = 'Inventory';

    protected static ?string $navigationLabel = 'Purchases';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Purchase Details')
                    ->columns(3)
                    ->schema([
                        Forms\Components\Select::make('store_id')
                            ->label('Store')
                            ->options(Store::where('active', true)->pluck('name', 'id'))
                            ->required()
                            ->default(fn () => Auth::user()->stores[0] ?? 1),

                        Forms\Components\Select::make('supplier_id')
                            ->label('Supplier')
                            ->options(Supplier::where('active', true)->pluck('name', 'id'))
                            ->searchable()
                            ->preload()
                            ->required(),

                        Forms\Components\DatePicker::make('purchase_date')
                            ->label('Purchase Date')
                            ->required()
                            ->default(now()),

                        Forms\Components\TextInput::make('supplier_invoice_number')
                            ->label('Supplier Invoice #')
                            ->maxLength(100),

                        Forms\Components\DatePicker::make('expected_delivery_date')
                            ->label('Expected Delivery'),

                        Forms\Components\Select::make('status')
                            ->options([
                                'draft' => 'Draft',
                                'ordered' => 'Ordered',
                                'partial' => 'Partially Received',
                                'received' => 'Received',
                                'cancelled' => 'Cancelled',
                            ])
                            ->default('draft')
                            ->required(),
                    ]),

                Forms\Components\Section::make('Items')
                    ->schema([
                        Forms\Components\Repeater::make('items')
                            ->relationship()
                            ->schema([
                                Forms\Components\Select::make('product_variant_id')
                                    ->label('Product')
                                    ->options(function () {
                                        return ProductVariant::with('product')
                                            ->get()
                                            ->mapWithKeys(fn ($v) => [
                                                $v->id => "{$v->product->name} - {$v->pack_size}{$v->unit} ({$v->sku})"
                                            ]);
                                    })
                                    ->searchable()
                                    ->required()
                                    ->reactive()
                                    ->afterStateUpdated(function ($state, callable $set) {
                                        if ($state) {
                                            $variant = ProductVariant::find($state);
                                            if ($variant) {
                                                $set('unit_cost', $variant->cost_price ?? 0);
                                                $set('unit', $variant->unit);
                                            }
                                        }
                                    })
                                    ->columnSpan(3),

                                Forms\Components\TextInput::make('quantity_ordered')
                                    ->label('Qty')
                                    ->numeric()
                                    ->required()
                                    ->default(1)
                                    ->minValue(0.001)
                                    ->columnSpan(1),

                                Forms\Components\TextInput::make('unit')
                                    ->label('Unit')
                                    ->default('pcs')
                                    ->maxLength(20)
                                    ->columnSpan(1),

                                Forms\Components\TextInput::make('unit_cost')
                                    ->label('Unit Cost')
                                    ->numeric()
                                    ->required()
                                    ->prefix('₹')
                                    ->columnSpan(1),

                                Forms\Components\TextInput::make('tax_rate')
                                    ->label('Tax %')
                                    ->numeric()
                                    ->default(0)
                                    ->suffix('%')
                                    ->columnSpan(1),

                                Forms\Components\TextInput::make('discount_amount')
                                    ->label('Discount')
                                    ->numeric()
                                    ->default(0)
                                    ->prefix('₹')
                                    ->columnSpan(1),

                                Forms\Components\DatePicker::make('expiry_date')
                                    ->label('Expiry')
                                    ->columnSpan(1),

                                Forms\Components\Textarea::make('notes')
                                    ->label('Notes')
                                    ->rows(1)
                                    ->columnSpan(3),
                            ])
                            ->columns(12)
                            ->defaultItems(1)
                            ->addActionLabel('Add Item')
                            ->reorderable(false),
                    ]),

                Forms\Components\Section::make('Totals & Notes')
                    ->columns(4)
                    ->schema([
                        Forms\Components\TextInput::make('shipping_cost')
                            ->label('Shipping Cost')
                            ->numeric()
                            ->default(0)
                            ->prefix('₹'),

                        Forms\Components\Placeholder::make('calculated_subtotal')
                            ->label('Subtotal')
                            ->content(fn ($record) => '₹' . number_format($record?->subtotal ?? 0, 2)),

                        Forms\Components\Placeholder::make('calculated_tax')
                            ->label('Tax')
                            ->content(fn ($record) => '₹' . number_format($record?->tax_amount ?? 0, 2)),

                        Forms\Components\Placeholder::make('calculated_total')
                            ->label('Total')
                            ->content(fn ($record) => '₹' . number_format($record?->total ?? 0, 2)),

                        Forms\Components\Textarea::make('notes')
                            ->label('Notes')
                            ->rows(2)
                            ->columnSpanFull(),

                        Forms\Components\FileUpload::make('invoice_file')
                            ->label('Invoice Attachment')
                            ->directory('purchase-invoices')
                            ->acceptedFileTypes(['application/pdf', 'image/*'])
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('purchase_number')
                    ->label('Purchase #')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('supplier.name')
                    ->label('Supplier')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('store.name')
                    ->label('Store')
                    ->sortable(),

                Tables\Columns\TextColumn::make('purchase_date')
                    ->label('Date')
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('total')
                    ->label('Total')
                    ->money('INR')
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'gray' => 'draft',
                        'warning' => 'ordered',
                        'info' => 'partial',
                        'success' => 'received',
                        'danger' => 'cancelled',
                    ]),

                Tables\Columns\BadgeColumn::make('payment_status')
                    ->label('Payment')
                    ->colors([
                        'danger' => 'unpaid',
                        'warning' => 'partial',
                        'success' => 'paid',
                    ]),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'ordered' => 'Ordered',
                        'partial' => 'Partially Received',
                        'received' => 'Received',
                        'cancelled' => 'Cancelled',
                    ]),

                Tables\Filters\SelectFilter::make('payment_status')
                    ->options([
                        'unpaid' => 'Unpaid',
                        'partial' => 'Partial',
                        'paid' => 'Paid',
                    ]),

                Tables\Filters\SelectFilter::make('supplier_id')
                    ->label('Supplier')
                    ->options(Supplier::pluck('name', 'id')),

                Tables\Filters\SelectFilter::make('store_id')
                    ->label('Store')
                    ->options(Store::pluck('name', 'id')),
            ])
            ->actions([
                Tables\Actions\Action::make('receive')
                    ->label('Receive')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn ($record) => in_array($record->status, ['draft', 'ordered', 'partial']))
                    ->requiresConfirmation()
                    ->modalHeading('Receive Purchase')
                    ->modalDescription('This will add all ordered items to stock. Are you sure?')
                    ->action(fn ($record) => $record->receive()),

                Tables\Actions\Action::make('record_payment')
                    ->label('Payment')
                    ->icon('heroicon-o-banknotes')
                    ->color('warning')
                    ->visible(fn ($record) => $record->payment_status !== 'paid' && $record->status === 'received')
                    ->form([
                        Forms\Components\TextInput::make('amount')
                            ->label('Amount')
                            ->numeric()
                            ->required()
                            ->prefix('₹')
                            ->default(fn ($record) => $record->outstanding_amount),

                        Forms\Components\Select::make('payment_method')
                            ->options([
                                'cash' => 'Cash',
                                'bank_transfer' => 'Bank Transfer',
                                'cheque' => 'Cheque',
                                'upi' => 'UPI',
                            ])
                            ->required(),

                        Forms\Components\TextInput::make('reference')
                            ->label('Reference #'),

                        Forms\Components\Textarea::make('notes')
                            ->rows(2),
                    ])
                    ->action(function ($record, array $data) {
                        $record->recordPayment(
                            $data['amount'],
                            $data['payment_method'],
                            $data['reference'] ?? null,
                            $data['notes'] ?? null
                        );
                    }),

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

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPurchases::route('/'),
            'create' => Pages\CreatePurchase::route('/create'),
            'view' => Pages\ViewPurchase::route('/{record}'),
            'edit' => Pages\EditPurchase::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['supplier', 'store', 'items']);
    }
}
