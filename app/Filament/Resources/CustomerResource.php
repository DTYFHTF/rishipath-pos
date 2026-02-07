<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CustomerResource\Pages;
use App\Models\Customer;
use App\Services\OrganizationContext;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CustomerResource extends Resource
{
    protected static ?string $model = Customer::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'Sales';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('customer_code')
                    ->label('Customer Code')
                    ->default(fn ($record) => $record?->customer_code ?? Customer::generateNextCustomerCode())
                    ->disabled(fn ($record) => $record === null)
                    ->dehydrated()
                    ->required()
                    ->unique(ignoreRecord: true, modifyRuleUsing: fn ($rule, $get) => $rule->where('organization_id', $get('organization_id') ?? OrganizationContext::getCurrentOrganizationId()))
                    ->maxLength(50)
                    ->helperText('Auto-generated based on current date'),

                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),

                Forms\Components\Grid::make(2)
                    ->schema([
                        Forms\Components\Select::make('country_code')
                            ->label('Country Code')
                            ->options([
                                '+91' => '🇮🇳 +91 (India)',
                                '+977' => '🇳🇵 +977 (Nepal)',
                                '+1' => '🇺🇸 +1 (USA)',
                            ])
                            ->default('+91')
                            ->searchable()
                            ->required()
                            ->columnSpan(1),

                        Forms\Components\TextInput::make('phone')
                            ->label('Phone Number')
                            ->tel()
                            ->unique(ignoreRecord: true, modifyRuleUsing: fn ($rule, $get) => $rule->where('organization_id', $get('organization_id') ?? OrganizationContext::getCurrentOrganizationId()))
                            ->maxLength(20)
                            ->placeholder('Enter phone without country code')
                            ->helperText('Enter number without country code')
                            ->columnSpan(1),
                    ]),

                Forms\Components\TextInput::make('email')
                    ->email()
                    ->unique(ignoreRecord: true, modifyRuleUsing: fn ($rule, $get) => $rule->where('organization_id', $get('organization_id') ?? OrganizationContext::getCurrentOrganizationId()))
                    ->maxLength(255),
                Forms\Components\Textarea::make('address')
                    ->rows(2),
                Forms\Components\TextInput::make('city')
                    ->maxLength(100)
                    ->datalist([
                        'Mumbai', 'Delhi', 'Bangalore', 'Kolkata', 'Chennai', 'Pune', 'Hyderabad', 'Ahmedabad', 'Jaipur', 'Lucknow'
                    ])
                    ->helperText('Start typing for suggestions'),
                Forms\Components\DatePicker::make('date_of_birth')
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->maxDate(now())
                    ->helperText('For birthday rewards and age verification'),
                Forms\Components\RichEditor::make('notes')
                    ->toolbarButtons(['bold', 'italic', 'bulletList'])
                    ->helperText('Internal notes about customer preferences'),
                Forms\Components\Toggle::make('active')
                    ->default(true),
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
                Tables\Columns\TextColumn::make('customer_code')->searchable(),
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\TextColumn::make('phone')
                    ->searchable()
                    ->formatStateUsing(fn ($record) => $record->country_code ? "{$record->country_code} {$record->phone}" : $record->phone),
                Tables\Columns\TextColumn::make('email')->searchable(),
                Tables\Columns\TextColumn::make('total_purchases')->label('Purchases'),
                Tables\Columns\TextColumn::make('total_spent')->money('INR'),
                Tables\Columns\IconColumn::make('active')->boolean(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('active'),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make(),
                    
                    Tables\Actions\Action::make('view_ledger')
                        ->label('View Ledger')
                        ->icon('heroicon-o-document-text')
                        ->color('info')
                        ->url(fn ($record) => route('filament.admin.pages.customer-ledger-report', ['customer_id' => $record->id]))
                        ->openUrlInNewTab(),
                    
                    Tables\Actions\Action::make('view_sales')
                        ->label('View Sales')
                        ->icon('heroicon-o-shopping-cart')
                        ->color('success')
                        ->url(fn ($record) => route('filament.admin.resources.sales.index', ['tableFilters[customer_id][value]' => $record->id]))
                        ->openUrlInNewTab(),
                    
                    Tables\Actions\Action::make('send_message')
                        ->label('Send SMS/WhatsApp')
                        ->icon('heroicon-o-chat-bubble-left-right')
                        ->color('warning')
                        ->visible(fn ($record) => !empty($record->phone))
                        ->form([
                            Forms\Components\Textarea::make('message')
                                ->label('Message')
                                ->required()
                                ->rows(3)
                                ->placeholder('Type your message here...'),
                            Forms\Components\Select::make('method')
                                ->label('Send via')
                                ->options([
                                    'whatsapp' => 'WhatsApp',
                                    'sms' => 'SMS',
                                ])
                                ->default('whatsapp')
                                ->required(),
                        ])
                        ->action(function ($record, array $data) {
                            // Placeholder for SMS/WhatsApp integration
                            \Filament\Notifications\Notification::make()
                                ->success()
                                ->title('Message Queued')
                                ->body("Message will be sent to {$record->name} via {$data['method']}")
                                ->send();
                        }),
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
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCustomers::route('/'),
            'create' => Pages\CreateCustomer::route('/create'),
            'edit' => Pages\EditCustomer::route('/{record}/edit'),
        ];
    }
}
