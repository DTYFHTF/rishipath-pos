<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BulkOrderInquiryResource\Pages;
use App\Filament\Traits\HasPermissionCheck;
use App\Models\BulkOrderInquiry;
use App\Models\Product;
use App\Models\RetailStore;
use App\Services\InvoiceService;
use App\Services\OrganizationContext;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class BulkOrderInquiryResource extends Resource
{
    use HasPermissionCheck;

    protected static ?string $model = BulkOrderInquiry::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';

    protected static ?string $navigationGroup = 'Sales';

    protected static ?int $navigationSort = 6;

    protected static ?string $navigationLabel = 'Bulk Orders';

    public static function getNavigationBadge(): ?string
    {
        $orgId = OrganizationContext::getCurrentOrganizationId()
            ?? auth()->user()?->organization_id;

        return (string) BulkOrderInquiry::query()
            ->where('organization_id', $orgId)
            ->where('status', 'new')
            ->count() ?: null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Hidden::make('organization_id')
                    ->default(fn () => OrganizationContext::getCurrentOrganizationId()),

                // ─── Retail Store Selection (auto-sync) ──────
                Forms\Components\Section::make('Retail Store')
                    ->schema([
                        Forms\Components\Select::make('retail_store_id')
                            ->label('Select Retail Store')
                            ->relationship(
                                'retailStore',
                                'store_name',
                                fn (Builder $query) => $query->where(
                                    'organization_id',
                                    OrganizationContext::getCurrentOrganizationId() ?? auth()->user()?->organization_id
                                )
                            )
                            ->searchable()
                            ->preload()
                            ->nullable()
                            ->live()
                            ->afterStateUpdated(function (Set $set, ?string $state) {
                                if (! $state) {
                                    return;
                                }

                                $store = RetailStore::find($state);
                                if (! $store) {
                                    return;
                                }

                                // Auto-sync store details into shipping fields
                                $set('name', $store->contact_person ?? $store->store_name);
                                $set('phone', $store->contact_number);
                                $set('company_name', $store->store_name);
                                $set('shipping_address', $store->address);
                                $set('shipping_area', $store->area);
                                $set('shipping_landmark', $store->landmark);
                                $set('shipping_city', $store->city);
                                $set('shipping_state', $store->state);
                                $set('shipping_pincode', $store->pincode);
                                $set('shipping_country', $store->country ?? 'Nepal');
                            })
                            ->createOptionForm([
                                Forms\Components\TextInput::make('store_name')->required()->maxLength(255),
                                Forms\Components\TextInput::make('contact_person')->maxLength(255),
                                Forms\Components\TextInput::make('contact_number')->tel()->maxLength(15),
                                Forms\Components\TextInput::make('city')->maxLength(100),
                                Forms\Components\Select::make('status')
                                    ->options(['prospect' => 'Prospect', 'active' => 'Active', 'inactive' => 'Inactive'])
                                    ->default('prospect'),
                            ])
                            ->createOptionUsing(function (array $data) {
                                $data['organization_id'] = OrganizationContext::getCurrentOrganizationId()
                                    ?? auth()->user()->organization_id;
                                $data['created_by'] = auth()->id();

                                return RetailStore::create($data)->id;
                            })
                            ->columnSpanFull(),
                    ]),

                // ─── Customer / Contact Info ─────────────────
                Forms\Components\Section::make('Customer Information')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Contact Name')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('email')
                            ->email()
                            ->maxLength(255),

                        Forms\Components\Grid::make(2)->schema([
                            Forms\Components\Select::make('phone_country_code')
                                ->label('Country Code')
                                ->options([
                                    '+977' => '🇳🇵 +977 (Nepal)',
                                    '+91' => '🇮🇳 +91 (India)',
                                    '+1' => '🇺🇸 +1 (USA)',
                                ])
                                ->default('+977')
                                ->searchable(),

                            Forms\Components\TextInput::make('phone')
                                ->tel()
                                ->maxLength(20),
                        ]),

                        Forms\Components\TextInput::make('company_name')
                            ->maxLength(255),

                        Forms\Components\TextInput::make('tax_number')
                            ->label('Tax / PAN Number')
                            ->maxLength(100),
                    ])->columns(2),

                // ─── Shipping Address ────────────────────────
                Forms\Components\Section::make('Shipping Address')
                    ->schema([
                        Forms\Components\Textarea::make('shipping_address')
                            ->label('Address')
                            ->rows(2)
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('shipping_area')
                            ->label('Area'),

                        Forms\Components\TextInput::make('shipping_landmark')
                            ->label('Landmark'),

                        Forms\Components\TextInput::make('shipping_city')
                            ->label('City'),

                        Forms\Components\TextInput::make('shipping_state')
                            ->label('State'),

                        Forms\Components\TextInput::make('shipping_pincode')
                            ->label('PIN Code')
                            ->maxLength(20),

                        Forms\Components\TextInput::make('shipping_country')
                            ->label('Country')
                            ->default('Nepal'),
                    ])->columns(2)->collapsible(),

                // ─── Products ────────────────────────────────
                Forms\Components\Section::make('Products')
                    ->schema([
                        Forms\Components\Repeater::make('products')
                            ->schema([
                                Forms\Components\Select::make('product_id')
                                    ->label('Product')
                                    ->options(function () {
                                        $orgId = OrganizationContext::getCurrentOrganizationId()
                                            ?? auth()->user()?->organization_id;

                                        return Product::query()
                                            ->where('organization_id', $orgId)
                                            ->where('active', true)
                                            ->pluck('name', 'id');
                                    })
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function (Set $set, ?string $state) {
                                        if (! $state) {
                                            return;
                                        }
                                        $product = Product::find($state);
                                        if ($product) {
                                            $set('product_name', $product->name);
                                            $set('sku', $product->sku);
                                        }
                                    })
                                    ->columnSpan(3),

                                Forms\Components\Hidden::make('product_name'),
                                Forms\Components\Hidden::make('sku'),

                                Forms\Components\TextInput::make('quantity')
                                    ->numeric()
                                    ->required()
                                    ->minValue(BulkOrderInquiry::MIN_QUANTITY)
                                    ->default(BulkOrderInquiry::MIN_QUANTITY)
                                    ->suffix('units')
                                    ->helperText('Min: ' . BulkOrderInquiry::MIN_QUANTITY)
                                    ->columnSpan(1),

                                Forms\Components\TextInput::make('unit_price')
                                    ->label('Rate')
                                    ->numeric()
                                    ->prefix('NPR')
                                    ->columnSpan(1),

                                Forms\Components\TextInput::make('notes')
                                    ->label('Notes')
                                    ->placeholder('Size, variant, etc.')
                                    ->columnSpan(1),
                            ])
                            ->columns(6)
                            ->minItems(1)
                            ->defaultItems(1)
                            ->addActionLabel('+ Add Product')
                            ->reorderable()
                            ->collapsible()
                            ->columnSpanFull(),
                    ]),

                // ─── Inquiry Details ─────────────────────────
                Forms\Components\Section::make('Inquiry Details')
                    ->schema([
                        Forms\Components\Textarea::make('message')
                            ->rows(2)
                            ->columnSpanFull(),

                        Forms\Components\Textarea::make('special_instructions')
                            ->rows(2)
                            ->columnSpanFull(),

                        Forms\Components\DatePicker::make('expected_delivery_date')
                            ->native(false),

                        Forms\Components\TextInput::make('budget_range')
                            ->placeholder('e.g., NPR 50,000 - 100,000'),
                    ])->columns(2)->collapsible(),

                // ─── Status & Admin ──────────────────────────
                Forms\Components\Section::make('Status & Admin Notes')
                    ->schema([
                        Forms\Components\Select::make('status')
                            ->options([
                                'new' => 'New',
                                'contacted' => 'Contacted',
                                'quoted' => 'Quoted',
                                'closed' => 'Closed',
                            ])
                            ->default('new')
                            ->required()
                            ->native(false),

                        Forms\Components\Textarea::make('admin_notes')
                            ->label('Admin Notes')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query) {
                $orgId = OrganizationContext::getCurrentOrganizationId()
                    ?? auth()->user()?->organization_id ?? 1;

                return $query->where('organization_id', $orgId);
            })
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('#')
                    ->sortable(),

                Tables\Columns\TextColumn::make('retailStore.store_name')
                    ->label('Store')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Contact')
                    ->searchable(),

                Tables\Columns\TextColumn::make('company_name')
                    ->label('Company')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('phone')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('product_names')
                    ->label('Products')
                    ->limit(50)
                    ->wrap()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('total_quantity')
                    ->label('Qty')
                    ->sortable(),

                Tables\Columns\TextColumn::make('shipping_city')
                    ->label('City')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'new' => 'info',
                        'contacted' => 'warning',
                        'quoted' => 'success',
                        'closed' => 'gray',
                    }),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('d M Y')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'new' => 'New',
                        'contacted' => 'Contacted',
                        'quoted' => 'Quoted',
                        'closed' => 'Closed',
                    ]),

                Tables\Filters\SelectFilter::make('retail_store_id')
                    ->label('Retail Store')
                    ->relationship('retailStore', 'store_name'),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make(),

                    // Generate Quotation
                    Tables\Actions\Action::make('generate_quotation')
                        ->label('Generate Quotation')
                        ->icon('heroicon-o-document-text')
                        ->color('success')
                        ->visible(fn (BulkOrderInquiry $record) => $record->status !== 'quoted')
                        ->form(function (BulkOrderInquiry $record): array {
                            $products = $record->products ?? [];

                            return [
                                Forms\Components\Repeater::make('products')
                                    ->schema([
                                        Forms\Components\Hidden::make('product_id'),
                                        Forms\Components\TextInput::make('product_name')
                                            ->label('Product')
                                            ->disabled()
                                            ->dehydrated(),
                                        Forms\Components\TextInput::make('quantity')
                                            ->numeric()
                                            ->required(),
                                        Forms\Components\TextInput::make('unit_price')
                                            ->label('Unit Price (NPR)')
                                            ->numeric()
                                            ->required(),
                                        Forms\Components\TextInput::make('tax_rate')
                                            ->label('Tax %')
                                            ->numeric()
                                            ->default(13),
                                        Forms\Components\TextInput::make('discount')
                                            ->label('Discount (NPR)')
                                            ->numeric()
                                            ->default(0),
                                    ])
                                    ->columns(5)
                                    ->default(collect($products)->map(fn ($p) => [
                                        'product_id' => $p['product_id'] ?? null,
                                        'product_name' => $p['product_name'] ?? 'Unknown',
                                        'quantity' => $p['quantity'] ?? 0,
                                        'unit_price' => $p['unit_price'] ?? 0,
                                        'tax_rate' => 13,
                                        'discount' => 0,
                                    ])->toArray())
                                    ->addable(false)
                                    ->deletable(false),

                                Forms\Components\Grid::make(3)->schema([
                                    Forms\Components\TextInput::make('discount_amount')
                                        ->label('Overall Discount (NPR)')
                                        ->numeric()
                                        ->default(0),

                                    Forms\Components\TextInput::make('shipping_amount')
                                        ->label('Shipping (NPR)')
                                        ->numeric()
                                        ->default(0),

                                    Forms\Components\TextInput::make('due_days')
                                        ->label('Valid for (days)')
                                        ->numeric()
                                        ->default(30),
                                ]),

                                Forms\Components\Textarea::make('terms_and_conditions')
                                    ->label('Terms & Conditions')
                                    ->rows(3)
                                    ->default('Payment due within 30 days. Prices include 13% VAT where applicable.'),

                                Forms\Components\Textarea::make('notes')
                                    ->rows(2),
                            ];
                        })
                        ->action(function (BulkOrderInquiry $record, array $data) {
                            $invoiceService = app(InvoiceService::class);
                            $invoice = $invoiceService->generateQuotationFromBulkInquiry($record, $data);

                            \Filament\Notifications\Notification::make()
                                ->success()
                                ->title('Quotation Generated')
                                ->body("Quotation #{$invoice->invoice_number} created successfully.")
                                ->send();
                        }),

                    // View Quotations
                    Tables\Actions\Action::make('view_quotations')
                        ->label('View Quotations')
                        ->icon('heroicon-o-document-magnifying-glass')
                        ->color('info')
                        ->visible(fn (BulkOrderInquiry $record) => $record->quotations()->exists())
                        ->modalHeading('Quotations')
                        ->modalContent(function (BulkOrderInquiry $record) {
                            $quotations = $record->quotations()->with('lines')->latest()->get();
                            $html = '<div class="space-y-3">';
                            foreach ($quotations as $q) {
                                $html .= '<div class="p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">';
                                $html .= '<div class="flex justify-between items-center">';
                                $html .= '<span class="font-semibold">' . e($q->invoice_number) . '</span>';
                                $html .= '<span class="text-sm px-2 py-1 rounded bg-primary-100 text-primary-700 dark:bg-primary-800 dark:text-primary-300">' . e(ucfirst($q->status)) . '</span>';
                                $html .= '</div>';
                                $html .= '<div class="text-sm text-gray-500 mt-1">Total: Rs. ' . number_format($q->total_amount, 2) . ' | Items: ' . $q->lines->count() . '</div>';
                                $html .= '<div class="text-xs text-gray-400 mt-1">Created: ' . $q->created_at->format('M d, Y') . '</div>';
                                $html .= '</div>';
                            }
                            $html .= '</div>';
                            return new \Illuminate\Support\HtmlString($html);
                        })
                        ->modalSubmitAction(false)
                        ->modalCancelActionLabel('Close'),
                ])->icon('heroicon-o-ellipsis-vertical'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBulkOrderInquiries::route('/'),
            'create' => Pages\CreateBulkOrderInquiry::route('/create'),
            'edit' => Pages\EditBulkOrderInquiry::route('/{record}/edit'),
        ];
    }
}
