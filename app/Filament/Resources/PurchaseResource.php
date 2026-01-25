<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PurchaseResource\Pages;
use App\Models\ProductVariant;
use App\Models\Purchase;
use App\Models\Store;
use App\Models\Supplier;
use App\Services\OrganizationContext;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
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
                            ->default(now())
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->maxDate(now())
                            ->helperText('Date of purchase order'),

                        Forms\Components\TextInput::make('supplier_invoice_number')
                            ->label('Supplier Invoice #')
                            ->maxLength(100)
                            ->helperText('Supplier\'s invoice reference'),

                        Forms\Components\DatePicker::make('expected_delivery_date')
                            ->label('Expected Delivery')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->minDate(now())
                            ->helperText('When do you expect delivery?'),

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
                                                $v->id => "{$v->product->name} - {$v->pack_size}{$v->unit} ({$v->sku})",
                                            ]);
                                    })
                                    ->searchable()
                                    ->required()
                                    ->reactive()
                                    ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                        if ($state) {
                                            $variant = ProductVariant::with(['storePricing', 'product'])->find($state);
                                            if ($variant) {
                                                $storeId = $get('../../store_id');
                                                $storePricing = $variant->storePricing->firstWhere('store_id', $storeId);
                                                $costPrice = $storePricing?->cost_price ?? $variant->cost_price ?? 0;
                                                
                                                $set('unit_cost', $costPrice);
                                                $set('unit', $variant->unit);
                                                $set('product_name', $variant->product->name . ' - ' . $variant->pack_size . $variant->unit);
                                                $set('product_sku', $variant->sku);
                                            }
                                        }
                                    })
                                    ->columnSpan(3),

                                Forms\Components\TextInput::make('quantity_ordered')
                                    ->label('Qty')
                                    ->numeric()
                                    ->required()
                                    ->default(1)
                                    ->minValue(0.01)
                                    ->reactive()
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
                                    ->minValue(0.01)
                                    ->prefix('₹')
                                    ->reactive()
                                    ->columnSpan(2),

                                Forms\Components\TextInput::make('tax_rate')
                                    ->label('Tax %')
                                    ->numeric()
                                    ->default(0)
                                    ->minValue(0)
                                    ->maxValue(100)
                                    ->suffix('%')
                                    ->reactive()
                                    ->columnSpan(2),

                                Forms\Components\TextInput::make('discount_amount')
                                    ->label('Discount')
                                    ->numeric()
                                    ->default(0)
                                    ->minValue(0)
                                    ->prefix('₹')
                                    ->reactive()
                                    ->columnSpan(2),

                                Forms\Components\Placeholder::make('line_total')
                                    ->label('Line Total')
                                    ->content(function (callable $get, callable $set) {
                                        $qty = floatval($get('quantity_ordered') ?? 0);
                                        $cost = floatval($get('unit_cost') ?? 0);
                                        $tax = floatval($get('tax_rate') ?? 0);
                                        $discount = floatval($get('discount_amount') ?? 0);
                                        
                                        $subtotal = $qty * $cost;
                                        $taxAmount = $subtotal * ($tax / 100);
                                        $total = $subtotal + $taxAmount - $discount;
                                        
                                        $set('tax_amount', round($taxAmount, 2));
                                        $set('line_total', round($total, 2));
                                        
                                        return '₹' . number_format($total, 2);
                                    })
                                    ->columnSpan(2),

                                Forms\Components\DatePicker::make('expiry_date')
                                    ->label('Expiry')
                                    ->columnSpan(2),

                                Forms\Components\Textarea::make('notes')
                                    ->label('Notes')
                                    ->rows(1)
                                    ->columnSpan(4),

                                Forms\Components\Hidden::make('tax_amount'),
                                Forms\Components\Hidden::make('line_total'),
                                Forms\Components\Hidden::make('product_name'),
                                Forms\Components\Hidden::make('product_sku'),
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
                            ->prefix('₹')
                            ->reactive(),

                        Forms\Components\Placeholder::make('calculated_subtotal')
                            ->label('Subtotal')
                            ->content(function (callable $get) {
                                $items = $get('items') ?? [];
                                $subtotal = collect($items)->sum(fn($item) => floatval($item['line_total'] ?? 0));
                                return '₹'.number_format($subtotal, 2);
                            }),

                        Forms\Components\Placeholder::make('calculated_tax')
                            ->label('Tax')
                            ->content(function (callable $get) {
                                $items = $get('items') ?? [];
                                $tax = collect($items)->sum(fn($item) => floatval($item['tax_amount'] ?? 0));
                                return '₹'.number_format($tax, 2);
                            }),

                        Forms\Components\Placeholder::make('calculated_total')
                            ->label('Total')
                            ->content(function (callable $get) {
                                $items = $get('items') ?? [];
                                $subtotal = collect($items)->sum(fn($item) => floatval($item['line_total'] ?? 0));
                                $shipping = floatval($get('shipping_cost') ?? 0);
                                $total = $subtotal + $shipping;
                                return '₹'.number_format($total, 2);
                            }),

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
            ->modifyQueryUsing(function ($query) {
                $orgId = OrganizationContext::getCurrentOrganizationId() 
                    ?? auth()->user()?->organization_id ?? 1;
                return $query->where('organization_id', $orgId);
            })
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
                    ->form([
                        Forms\Components\Placeholder::make('summary')
                            ->label('Items to Receive')
                            ->content(function ($record) {
                                $items = $record->items->map(function ($item) {
                                    $remaining = $item->quantity_ordered - $item->quantity_received;
                                    return "• {$item->product_name} ({$item->product_sku}): {$remaining} {$item->unit}";
                                })->implode('<br>');
                                return new \Illuminate\Support\HtmlString($items);
                            }),
                        Forms\Components\Placeholder::make('total')
                            ->label('Purchase Total')
                            ->content(fn ($record) => '₹'.number_format($record->total, 2)),
                    ])
                    ->modalHeading('Receive Purchase')
                    ->modalDescription('This will add all outstanding items to stock.')
                    ->action(function ($record) {
                        try {
                            $record->receive();
                            Notification::make()
                                ->success()
                                ->title('Purchase Received')
                                ->body("Purchase {$record->purchase_number} has been received and stock updated.")
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->danger()
                                ->title('Error Receiving Purchase')
                                ->body($e->getMessage())
                                ->send();
                        }
                    }),

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
        return [
            PurchaseResource\RelationManagers\BatchesRelationManager::class,
        ];
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
