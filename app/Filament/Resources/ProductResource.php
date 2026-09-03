<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\CostRepricer;
use App\Services\OrganizationContext;
use App\Services\PackPricing;
use App\Services\PackVariantGenerator;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static ?string $navigationIcon = 'heroicon-o-cube';

    protected static ?string $navigationGroup = 'Product Catalog';

    protected static ?int $navigationSort = 1;

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasPermission('view_products') ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->hasPermission('create_products') ?? false;
    }

    public static function canEdit($record): bool
    {
        return auth()->user()?->hasPermission('edit_products') ?? false;
    }

    public static function canDelete($record): bool
    {
        return auth()->user()?->hasPermission('delete_products') ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Tabs::make('Product')
                    ->columnSpanFull()
                    ->persistTabInQueryString()
                    ->tabs([
                        Forms\Components\Tabs\Tab::make('Basic Info')
                            ->icon('heroicon-o-identification')
                            ->columns(2)
                            ->schema([
                                Forms\Components\Placeholder::make('sku_info')
                                    ->label('SKU')
                                    ->content(fn ($record) => $record?->sku ?? 'Will be auto-generated')
                                    ->visible(fn ($record) => $record !== null),

                                Forms\Components\Select::make('category_id')
                                    ->relationship('category', 'name')
                                    ->required()
                                    ->searchable()
                                    ->preload(),
                                Forms\Components\TextInput::make('name')
                                    ->required()
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('name_sanskrit')
                                    ->label('Sanskrit Name')
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('name_hindi')
                                    ->label('Hindi Name')
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('name_romanized')
                                    ->label('Transliteration (Romanized)')
                                    ->helperText('Example: Nariwal for नरिवल')
                                    ->maxLength(255),
                                Forms\Components\Select::make('product_type')
                                    ->required()
                                    ->options([
                                        'choorna' => '🌾 Choorna (Powder)',
                                        'tailam' => '🪧 Tailam (Oil)',
                                        'ghritam' => '🧈 Ghritam (Ghee)',
                                        'rasayana' => '💊 Rasayana',
                                        'capsules' => '💊 Capsules/Tablets',
                                        'tea' => '🍵 Tea',
                                        'honey' => '🍯 Honey',
                                        'others' => '🧾 Others',
                                    ])
                                    ->searchable()
                                    ->helperText('Traditional Ayurvedic product classification'),
                                Forms\Components\Select::make('unit_type')
                                    ->required()
                                    ->options([
                                        'weight' => '⚖️ Weight (GMS/KG)',
                                        'volume' => '🧪 Volume (ML/L)',
                                        'piece' => '📦 Piece',
                                    ])
                                    ->reactive()
                                    ->helperText('Base measurement unit for this product'),
                                Forms\Components\RichEditor::make('description')
                                    ->toolbarButtons(['bold', 'italic', 'bulletList', 'orderedList', 'link'])
                                    ->helperText('Product description for online catalog and labels')
                                    ->columnSpanFull(),
                            ]),

                        Forms\Components\Tabs\Tab::make('Details')
                            ->icon('heroicon-o-adjustments-horizontal')
                            ->columns(2)
                            ->schema([
                                Forms\Components\Select::make('tax_category')
                                    ->required()
                                    ->options([
                                        'essential' => 'Essential',
                                        'standard' => 'Standard',
                                        'luxury' => 'Luxury',
                                    ])
                                    ->default('standard'),
                                Forms\Components\Toggle::make('has_variants')
                                    ->label('Has Multiple Variants')
                                    ->live(),

                                // Creating a product used to mean creating its six
                                // pack variants one at a time afterwards. Nothing in
                                // those rows is a decision except the packs and the
                                // kilo cost, so they are asked for here and the
                                // variants are built on save. Create only: on an
                                // existing product the same job is the "Generate pack
                                // variants" action, which can report what it changed.
                                Forms\Components\Section::make('Pack Variants')
                                    ->description('Tick the packs this product is sold in and give the cost of one kilo. Each pack is created with its share of that cost; SKUs and prices follow the usual pricing rule.')
                                    ->visible(fn (Forms\Get $get, string $operation) => $operation === 'create' && $get('has_variants'))
                                    ->columnSpanFull()
                                    ->columns(2)
                                    ->schema([
                                        Forms\Components\CheckboxList::make('generate_pack_sizes')
                                            ->label('Pack sizes')
                                            ->options(PackVariantGenerator::options())
                                            ->default(PackVariantGenerator::STANDARD_PACKS)
                                            ->columns(3)
                                            ->dehydrated(false)
                                            ->helperText('The six ticked by default are the catalogue standard.'),
                                        Forms\Components\TextInput::make('generate_cost_per_kg')
                                            ->label('Cost per kilo (Rs)')
                                            ->numeric()
                                            ->minValue(0)
                                            ->dehydrated(false)
                                            ->helperText('What one kilo costs you. Leave blank to create the packs without a cost — prices stay empty until you set one.'),
                                    ]),
                                Forms\Components\Toggle::make('requires_batch')
                                    ->label('Batch Tracking Required')
                                    ->default(true),
                                Forms\Components\Toggle::make('requires_expiry')
                                    ->label('Expiry Tracking Required')
                                    ->default(true),
                                Forms\Components\TextInput::make('shelf_life_months')
                                    ->label('Shelf Life (Months)')
                                    ->numeric()
                                    ->minValue(1),
                                Forms\Components\Toggle::make('is_prescription_required')
                                    ->label('Prescription Required'),
                                Forms\Components\TagsInput::make('ingredients')
                                    ->placeholder('Add ingredient (press Enter)')
                                    ->helperText('List all ingredients')
                                    ->columnSpanFull(),
                                Forms\Components\RichEditor::make('usage_instructions')
                                    ->toolbarButtons(['bold', 'italic', 'bulletList', 'orderedList'])
                                    ->helperText('Dosage, timing, and usage guidelines')
                                    ->columnSpanFull(),
                            ]),

                        Forms\Components\Tabs\Tab::make('Images')
                            ->icon('heroicon-o-photo')
                            ->schema([
                                Forms\Components\Section::make('Price List Image')
                                    ->description('This image is shown in the Price List PDF and the POS screen.')
                                    ->schema([
                                        Forms\Components\Placeholder::make('image_url_preview')
                                            ->label('Current Image')
                                            ->content(function ($record) {
                                                if (! $record?->image_url) {
                                                    return new HtmlString('<span class="text-sm text-gray-400">No image set yet. Upload one below.</span>');
                                                }
                                                $src = str_starts_with($record->image_url, '/')
                                                    ? asset(ltrim($record->image_url, '/'))
                                                    : Storage::disk('public')->url($record->image_url);

                                                return new HtmlString(
                                                    '<img src="'.e($src).'" style="max-height:200px;border-radius:10px;border:1px solid #e5e7eb;box-shadow:0 1px 4px rgba(0,0,0,.12)">'
                                                );
                                            })
                                            ->visible(fn ($record) => $record !== null),
                                        Forms\Components\FileUpload::make('image_url')
                                            ->label('Upload New Image')
                                            ->image()
                                            ->disk('public')
                                            ->directory('product-images')
                                            ->maxSize(4096)
                                            ->helperText('Uploading a new file will replace the current image. Square images (1:1) recommended.')
                                            ->afterStateHydrated(function (\Filament\Forms\Components\FileUpload $component, $state) {
                                                // Defining this callback REPLACES the one BaseFileUpload sets up for
                                                // itself, which is what normally turns the stored path into the
                                                // keyed array the component works in. So both cases have to be
                                                // handled here or the raw string reaches getUploadedFiles() and it
                                                // fails on foreach() at the next round trip — a 500 on the tab.
                                                //
                                                // Paths beginning with / (…/images/productv2-webp/…) are served
                                                // straight out of public/ rather than the 'public' disk this upload
                                                // writes to, so the component cannot load them: leave it empty and
                                                // let the preview above show the image. dehydrated() below keeps the
                                                // stored value from being overwritten on save.
                                                if (blank($state) || (is_string($state) && str_starts_with($state, '/'))) {
                                                    $component->state([]);

                                                    return;
                                                }

                                                if (is_string($state)) {
                                                    $component->state([(string) Str::uuid() => $state]);
                                                }
                                            })
                                            ->dehydrated(fn ($state) => $state !== null),
                                    ])
                                    ->columns(1),

                                Forms\Components\Section::make('Gallery Images')
                                    ->description('Additional product photos for catalog display (not used in price list).')
                                    ->schema([
                                        Forms\Components\FileUpload::make('image_1')
                                            ->label('Gallery Image 1')
                                            ->image()
                                            ->directory('product-images')
                                            ->imageEditor()
                                            ->imageEditorAspectRatios(['1:1', '4:3'])
                                            ->maxSize(2048),
                                        Forms\Components\FileUpload::make('image_2')
                                            ->label('Gallery Image 2')
                                            ->image()
                                            ->directory('product-images')
                                            ->imageEditor()
                                            ->imageEditorAspectRatios(['1:1', '4:3'])
                                            ->maxSize(2048),
                                        Forms\Components\FileUpload::make('image_3')
                                            ->label('Gallery Image 3')
                                            ->image()
                                            ->directory('product-images')
                                            ->imageEditor()
                                            ->imageEditorAspectRatios(['1:1', '4:3'])
                                            ->maxSize(2048),
                                    ])
                                    ->columns(3)
                                    ->collapsed(),
                            ]),

                        Forms\Components\Tabs\Tab::make('Status')
                            ->icon('heroicon-o-check-circle')
                            ->schema([
                                Forms\Components\Toggle::make('active')
                                    ->label('Active')
                                    ->default(true)
                                    ->helperText('Switching a product off hides it from the POS and the price list. Stock and past sales are untouched.'),

                                // A product can be active while the packs people
                                // actually buy are not, which looks identical from
                                // the product list. Showing the packs here - and
                                // letting them be switched back on in place - is
                                // the difference between spotting that and hunting
                                // through the Product Variants resource for it.
                                Forms\Components\Placeholder::make('pack_status_summary')
                                    ->label('Packs')
                                    ->content(function (?Product $record) {
                                        if (! $record) {
                                            return new HtmlString('<span class="text-sm text-gray-500">Packs appear here once the product is saved.</span>');
                                        }

                                        $total = $record->variants()->count();

                                        if ($total === 0) {
                                            return new HtmlString('<span class="text-sm text-gray-500">No packs yet — use <strong>Generate pack variants</strong> above.</span>');
                                        }

                                        $active = $record->variants()->where('active', true)->count();
                                        $colour = match (true) {
                                            $active === 0 => '#b91c1c',
                                            $active < $total => '#b45309',
                                            default => '#15803d',
                                        };
                                        $note = $active < $total
                                            ? ' — the rest are hidden from the POS and price list'
                                            : '';

                                        return new HtmlString(
                                            '<span style="font-weight:600;color:'.$colour.'">'
                                            .$active.' of '.$total.' packs active</span>'
                                            .'<span style="color:#6b7280">'.$note.'</span>'
                                        );
                                    })
                                    ->visibleOn('edit'),

                                Forms\Components\Repeater::make('variants')
                                    ->relationship()
                                    ->label('')
                                    ->schema([
                                        Forms\Components\Placeholder::make('pack')
                                            ->label('Pack')
                                            ->content(fn (?ProductVariant $record) => $record?->pack_label ?? '—'),
                                        Forms\Components\Placeholder::make('price')
                                            ->label('Selling price')
                                            ->content(fn (?ProductVariant $record) => $record?->selling_price_nepal
                                                ? 'NPR '.number_format((float) $record->selling_price_nepal)
                                                : '—'),
                                        Forms\Components\Toggle::make('active')
                                            ->label('Active')
                                            ->inline(false),
                                    ])
                                    ->columns(3)
                                    // Packs are created by the generator or the
                                    // Product Variants resource. Adding or removing
                                    // one here would cascade to stock levels and
                                    // batches, so this view only switches them.
                                    ->addable(false)
                                    ->deletable(false)
                                    ->reorderable(false)
                                    ->visibleOn('edit'),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function ($query) {
                $orgId = OrganizationContext::getCurrentOrganizationId()
                    ?? auth()->user()?->organization_id ?? 1;

                return $query
                    ->where('organization_id', $orgId)
                    ->withCount([
                        'variants',
                        'variants as active_variants_count' => fn ($q) => $q->where('active', true),
                    ]);
            })
            ->columns([
                Tables\Columns\ImageColumn::make('image_url')
                    ->label('')
                    ->size(52)
                    ->square()
                    ->extraImgAttributes(['class' => 'rounded-md object-cover'])
                    ->getStateUsing(fn ($record) => $record->image_url
                        ? (str_starts_with($record->image_url, '/')
                            ? asset(ltrim($record->image_url, '/'))
                            : Storage::disk('public')->url($record->image_url))
                        : null
                    ),
                Tables\Columns\TextColumn::make('sku')
                    ->label('SKU')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->searchable(['name', 'name_nepali', 'name_hindi', 'name_romanized'])
                    ->sortable(),
                Tables\Columns\TextColumn::make('category.name')
                    ->sortable()
                    ->badge(),
                Tables\Columns\TextColumn::make('product_type')
                    ->badge()
                    ->colors([
                        'success' => 'choorna',
                        'warning' => 'tailam',
                        'info' => 'ghritam',
                        'primary' => 'capsules',
                        'secondary' => 'others',
                    ]),
                Tables\Columns\TextColumn::make('variants_count')
                    ->label('Packs')
                    ->badge()
                    // A bare total hid the case that matters: a live product
                    // whose packs are all switched off sells nothing, and looked
                    // no different from a healthy one.
                    ->getStateUsing(fn ($record) => $record->variants_count === 0
                        ? '—'
                        : $record->active_variants_count.'/'.$record->variants_count)
                    ->color(fn ($record) => match (true) {
                        $record->variants_count === 0 => 'gray',
                        $record->active_variants_count === 0 => 'danger',
                        $record->active_variants_count < $record->variants_count => 'warning',
                        default => 'success',
                    })
                    ->tooltip(fn ($record) => $record->variants_count === 0
                        ? 'No packs yet'
                        : $record->active_variants_count.' of '.$record->variants_count.' packs active'),
                Tables\Columns\IconColumn::make('active')
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category')
                    ->relationship('category', 'name'),
                Tables\Filters\SelectFilter::make('product_type')
                    ->options([
                        'choorna' => 'Choorna',
                        'tailam' => 'Tailam',
                        'ghritam' => 'Ghritam',
                        'capsules' => 'Capsules',
                        'tea' => 'Tea',
                        'honey' => 'Honey',
                        'others' => 'Others',
                    ]),
                Tables\Filters\TernaryFilter::make('active'),
            ])
            ->actions([
                Tables\Actions\Action::make('details')
                    ->label('Details')
                    ->icon('heroicon-o-information-circle')
                    ->color('info')
                    ->modalHeading(fn ($record) => $record->name.' - Inventory Details')
                    ->modalWidth('7xl')
                    ->modalContent(fn ($record) => view('filament.pages.product-detail-modal', ['product' => $record]))
                    ->slideOver(),
                self::setCostAction(),
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('mark_inactive')
                        ->label('Mark Inactive')
                        ->icon('heroicon-o-eye-slash')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->action(function (Collection $records): void {
                            $updated = 0;
                            $records->each(function (Product $record) use (&$updated): void {
                                if ($record->active) {
                                    $record->update(['active' => false]);
                                    $updated++;
                                }
                            });

                            Notification::make()
                                ->success()
                                ->title('Products marked inactive')
                                ->body("{$updated} product(s) updated.")
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),

                    Tables\Actions\BulkAction::make('mark_active')
                        ->label('Mark Active')
                        ->icon('heroicon-o-eye')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function (Collection $records): void {
                            $updated = 0;
                            $records->each(function (Product $record) use (&$updated): void {
                                if (! $record->active) {
                                    $record->update(['active' => true]);
                                    $updated++;
                                }
                            });

                            Notification::make()
                                ->success()
                                ->title('Products marked active')
                                ->body("{$updated} product(s) updated.")
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),

                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->persistFiltersInSession();
    }

    /**
     * Set one cost per kilo and reprice every pack from it.
     *
     * The rate a spice is bought at is a single number the buyer already knows
     * ("coriander came in at Rs180 this week"), but it used to have to be
     * entered pack by pack and then turned into six selling prices by hand.
     * This takes the one number and does the rest, showing exactly what will
     * change before anything is written.
     */
    protected static function setCostAction(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('setCost')
            ->label('Set Cost')
            ->icon('heroicon-o-banknotes')
            ->color('warning')
            ->visible(fn () => auth()->user()?->hasPermission('edit_product_variants') ?? false)
            ->modalHeading(fn (Product $record) => 'Set cost — '.$record->name)
            ->modalSubmitActionLabel('Apply to all packs')
            ->modalWidth('2xl')
            ->fillForm(fn (Product $record) => [
                'cost_per_kg' => PackPricing::costPerKg($record->loadMissing('variants')),
            ])
            ->form([
                Forms\Components\TextInput::make('cost_per_kg')
                    ->label('Cost per kg (₹)')
                    ->helperText('What you pay for a kilo. Every pack price is worked out from this.')
                    ->numeric()
                    ->required()
                    ->minValue(0.01)
                    ->prefix('₹')
                    ->live(onBlur: true),

                Forms\Components\Placeholder::make('preview')
                    ->label('What this changes')
                    ->content(function (Forms\Get $get, Product $record) {
                        $cost = (float) $get('cost_per_kg');

                        if ($cost <= 0) {
                            return 'Enter a cost per kilo to see the new pack prices.';
                        }

                        return view('filament.tables.actions.set-cost-preview', [
                            'rows' => CostRepricer::preview($record->loadMissing('variants'), $cost),
                        ]);
                    }),
            ])
            ->action(function (Product $record, array $data) {
                $result = CostRepricer::apply(
                    $record->loadMissing('variants'),
                    (float) $data['cost_per_kg'],
                );

                Notification::make()
                    ->success()
                    ->title($record->name.' repriced')
                    ->body(sprintf(
                        '%d pack %s updated, %d %s repriced.',
                        $result['costs'],
                        str('cost')->plural($result['costs']),
                        $result['prices'],
                        str('price')->plural($result['prices']),
                    ))
                    ->send();
            });
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
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }
}
