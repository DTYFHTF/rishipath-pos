<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RetailStoreResource\Pages;
use App\Filament\Resources\RetailStoreResource\RelationManagers;
use App\Filament\Traits\HasPermissionCheck;
use App\Forms\Components\MapPicker;
use App\Models\RetailStore;
use App\Models\User;
use App\Services\OrganizationContext;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class RetailStoreResource extends Resource
{
    use HasPermissionCheck;

    protected static ?string $model = RetailStore::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';

    protected static ?string $navigationGroup = 'Sales';

    protected static ?int $navigationSort = 5;

    protected static ?string $recordTitleAttribute = 'store_name';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Hidden::make('organization_id')
                    ->default(fn () => OrganizationContext::getCurrentOrganizationId()),

                Forms\Components\Section::make('Store Information')
                    ->schema([
                        Forms\Components\TextInput::make('store_name')
                            ->label('Store Name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('e.g., Bajrangawoli Kold Store')
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('contact_person')
                            ->label('Contact Person')
                            ->maxLength(255)
                            ->placeholder('Name of contact person'),

                        Forms\Components\TextInput::make('contact_number')
                            ->label('Contact Number')
                            ->required()
                            ->tel()
                            ->maxLength(15)
                            ->placeholder('9841784088'),

                        Forms\Components\Select::make('status')
                            ->options([
                                'prospect' => 'Prospect',
                                'active' => 'Active',
                                'inactive' => 'Inactive',
                            ])
                            ->default('prospect')
                            ->required()
                            ->native(false),

                        Forms\Components\Select::make('assigned_to')
                            ->label('Assigned To')
                            ->options(function () {
                                $orgId = OrganizationContext::getCurrentOrganizationId();

                                return User::query()
                                    ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
                                    ->where('active', true)
                                    ->pluck('name', 'id');
                            })
                            ->searchable()
                            ->preload()
                            ->nullable(),
                    ])->columns(2),

                Forms\Components\Section::make('Location')
                    ->description('Pin the shop on the map (or paste its Google Maps link) — the address fields fill themselves.')
                    ->schema([
                        // Paste-a-link shortcut: the field team almost always has
                        // a Google Maps link before it has coordinates, and the
                        // Visit Planner needs coordinates to cluster and route.
                        Forms\Components\TextInput::make('google_location_url')
                            ->label('Google Maps link')
                            ->placeholder('Paste a maps.app.goo.gl or google.com/maps link — coordinates are read from it')
                            ->url()
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (?string $state, Forms\Set $set, Forms\Get $get) {
                                if (blank($state)) {
                                    return;
                                }

                                $coords = app(\App\Services\GoogleMapsLink::class)->coordinatesFor($state);

                                if (! $coords) {
                                    Notification::make()
                                        ->title('No coordinates in that link')
                                        ->body('Drop a pin on the map below instead.')
                                        ->warning()
                                        ->send();

                                    return;
                                }

                                $set('latitude', number_format($coords['lat'], 7, '.', ''));
                                $set('longitude', number_format($coords['lng'], 7, '.', ''));
                                $set('map_location', $coords['lat'].','.$coords['lng']);

                                if (isset($coords['expanded_url'])) {
                                    $set('google_location_url', $coords['expanded_url']);
                                }

                                Notification::make()
                                    ->title('Location found')
                                    ->body('Pin moved to '.round($coords['lat'], 5).', '.round($coords['lng'], 5).'. Confirm the address fields below.')
                                    ->success()
                                    ->send();
                            })
                            ->columnSpanFull(),

                        MapPicker::make('map_location')
                            ->label('Pin the shop')
                            ->hiddenLabel()
                            ->mapHeight('320px')
                            ->defaultLocation(['lat' => 27.7172, 'lng' => 85.3240])
                            ->defaultZoom(13)
                            ->draggable()
                            ->showGeolocationButton()
                            ->reactiveFields([
                                'latitude' => 'latitude',
                                'longitude' => 'longitude',
                                'address' => 'address',
                                'area' => 'area',
                                'landmark' => 'landmark',
                                'city' => 'city',
                                'state' => 'state',
                                'country' => 'country',
                                'pincode' => 'pincode',
                                'google_url' => 'google_location_url',
                            ])
                            // Not a real column — seed it from the saved
                            // coordinates so editing opens on the right pin.
                            ->dehydrated(false)
                            ->afterStateHydrated(function (MapPicker $component, ?RetailStore $record) {
                                if ($record?->latitude && $record?->longitude) {
                                    $component->state($record->latitude.','.$record->longitude);
                                }
                            })
                            ->columnSpanFull(),

                        Forms\Components\Textarea::make('address')
                            ->rows(2)
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('area')
                            ->maxLength(255),

                        Forms\Components\TextInput::make('landmark')
                            ->maxLength(255),

                        Forms\Components\TextInput::make('city')
                            ->maxLength(100),

                        Forms\Components\TextInput::make('state')
                            ->maxLength(100),

                        Forms\Components\TextInput::make('country')
                            ->default('Nepal')
                            ->maxLength(100),

                        Forms\Components\TextInput::make('pincode')
                            ->label('PIN Code')
                            ->maxLength(20),

                        Forms\Components\TextInput::make('latitude')
                            ->numeric()
                            ->step(0.0000001),

                        Forms\Components\TextInput::make('longitude')
                            ->numeric()
                            ->step(0.0000001),
                    ])->columns(2),

                Forms\Components\Section::make('Store Images')
                    ->schema([
                        Forms\Components\FileUpload::make('images')
                            ->label('Photos')
                            ->multiple()
                            ->image()
                            ->maxFiles(5)
                            ->directory('retail-stores')
                            ->columnSpanFull(),
                    ])->collapsible(),

                Forms\Components\Section::make('Notes')
                    ->schema([
                        Forms\Components\RichEditor::make('notes')
                            ->toolbarButtons(['bold', 'italic', 'bulletList'])
                            ->columnSpanFull(),
                    ])->collapsible(),
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
            ->columns([
                Tables\Columns\TextColumn::make('store_name')
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Bold),

                Tables\Columns\TextColumn::make('contact_person')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('contact_number')
                    ->searchable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('city')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'prospect' => 'warning',
                        'inactive' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('assignedTo.name')
                    ->label('Assigned To')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('last_visited_at')
                    ->label('Last Visit')
                    ->dateTime('d M Y')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('visits_count')
                    ->counts('visits')
                    ->label('Visits')
                    ->sortable(),

                Tables\Columns\TextColumn::make('bulk_order_inquiries_count')
                    ->counts('bulkOrderInquiries')
                    ->label('Orders')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\IconColumn::make('linkedCustomer.id')
                    ->label('Customer?')
                    ->boolean()
                    ->trueIcon('heroicon-o-user-circle')
                    ->falseIcon('heroicon-o-minus')
                    ->trueColor('success')
                    ->falseColor('gray')
                    ->tooltip(fn ($record) => $record->linkedCustomer?->customer_code ?? 'Not synced yet'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'prospect' => 'Prospect',
                        'active' => 'Active',
                        'inactive' => 'Inactive',
                    ]),

                Tables\Filters\SelectFilter::make('assigned_to')
                    ->label('Assigned To')
                    ->relationship('assignedTo', 'name'),

                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make(),

                    // Record Visit
                    Tables\Actions\Action::make('recordVisit')
                        ->label('Record Visit')
                        ->icon('heroicon-o-clipboard-document-check')
                        ->color('success')
                        ->form([
                            Forms\Components\Select::make('visit_purpose')
                                ->options([
                                    'sales' => 'Sales',
                                    'delivery' => 'Delivery',
                                    'collection' => 'Collection',
                                    'follow_up' => 'Follow Up',
                                    'new_contact' => 'New Contact',
                                    'complaint' => 'Complaint',
                                    'other' => 'Other',
                                ])
                                ->default('sales')
                                ->required(),

                            Forms\Components\Select::make('visit_outcome')
                                ->options([
                                    'successful' => 'Successful',
                                    'partially_successful' => 'Partially Successful',
                                    'unsuccessful' => 'Unsuccessful',
                                    'rescheduled' => 'Rescheduled',
                                    'store_closed' => 'Store Closed',
                                ])
                                ->required(),

                            Forms\Components\Grid::make(4)->schema([
                                Forms\Components\Toggle::make('stock_available')->label('Stock ✓'),
                                Forms\Components\Toggle::make('good_display')->label('Display ✓'),
                                Forms\Components\Toggle::make('order_placed')->label('Order ✓'),
                                Forms\Components\Toggle::make('payment_collected')->label('Payment ✓'),
                            ]),

                            Forms\Components\Textarea::make('notes')
                                ->rows(2),
                        ])
                        ->action(function (RetailStore $record, array $data) {
                            $record->visits()->create(array_merge($data, [
                                'organization_id' => $record->organization_id,
                                'visited_by' => auth()->id(),
                                'visit_date' => now()->toDateString(),
                                'visit_time' => now()->format('H:i:s'),
                            ]));

                            $record->markVisited();

                            \Filament\Notifications\Notification::make()
                                ->success()
                                ->title('Visit Recorded')
                                ->body("Visit to {$record->store_name} recorded successfully.")
                                ->send();
                        }),

                    // Mark Visited
                    Tables\Actions\Action::make('markVisited')
                        ->label('Mark Visited')
                        ->icon('heroicon-o-check-circle')
                        ->color('info')
                        ->requiresConfirmation()
                        ->action(fn (RetailStore $record) => $record->markVisited()),

                    // Open Map
                    Tables\Actions\Action::make('openMap')
                        ->label('Open Map')
                        ->icon('heroicon-o-map-pin')
                        ->color('gray')
                        ->url(fn (RetailStore $record) => $record->map_link)
                        ->openUrlInNewTab()
                        ->visible(fn (RetailStore $record) => $record->map_link !== null),

                    // Get Directions
                    Tables\Actions\Action::make('getDirections')
                        ->label('Get Directions')
                        ->icon('heroicon-o-arrow-top-right-on-square')
                        ->color('gray')
                        ->url(fn (RetailStore $record) => $record->latitude && $record->longitude
                            ? "https://www.google.com/maps/dir/?api=1&destination={$record->latitude},{$record->longitude}"
                            : null)
                        ->openUrlInNewTab()
                        ->visible(fn (RetailStore $record) => $record->latitude && $record->longitude),

                    // Create Bulk Order
                    Tables\Actions\Action::make('createBulkOrder')
                        ->label('Create Bulk Order')
                        ->icon('heroicon-o-shopping-cart')
                        ->color('warning')
                        ->url(fn (RetailStore $record) => BulkOrderInquiryResource::getUrl('create', ['store' => $record->id])),

                    // Sync as Customer
                    Tables\Actions\Action::make('syncCustomer')
                        ->label(fn (RetailStore $record) => $record->linkedCustomer ? 'Re-sync Customer' : 'Create as Customer')
                        ->icon('heroicon-o-user-plus')
                        ->color(fn (RetailStore $record) => $record->linkedCustomer ? 'gray' : 'success')
                        ->requiresConfirmation()
                        ->modalHeading(fn (RetailStore $record) => $record->linkedCustomer ? "Re-sync Customer for {$record->store_name}" : "Create Customer for {$record->store_name}")
                        ->modalDescription('Creates or updates a linked Customer account so this store can be selected in POS, Sales, and Ledger.')
                        ->action(function (RetailStore $record) {
                            $customer = $record->syncLinkedCustomer();

                            Notification::make()
                                ->success()
                                ->title('Customer Synced')
                                ->body("{$record->store_name} → Customer #{$customer->customer_code}")
                                ->send();
                        }),
                ])->icon('heroicon-o-ellipsis-vertical'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\VisitsRelationManager::class,
            RelationManagers\BulkOrderInquiriesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRetailStores::route('/'),
            'create' => Pages\CreateRetailStore::route('/create'),
            'edit' => Pages\EditRetailStore::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}
