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
                Forms\Components\TextInput::make('phone')
                    ->tel()
                    ->unique(ignoreRecord: true, modifyRuleUsing: fn ($rule, $get) => $rule->where('organization_id', $get('organization_id') ?? OrganizationContext::getCurrentOrganizationId()))
                    ->maxLength(20),
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
                Tables\Columns\TextColumn::make('phone')->searchable(),
                Tables\Columns\TextColumn::make('email')->searchable(),
                Tables\Columns\TextColumn::make('total_purchases')->label('Purchases'),
                Tables\Columns\TextColumn::make('total_spent')->money('INR'),
                Tables\Columns\IconColumn::make('active')->boolean(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('active'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
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
            'index' => Pages\ListCustomers::route('/'),
            'create' => Pages\CreateCustomer::route('/create'),
            'edit' => Pages\EditCustomer::route('/{record}/edit'),
        ];
    }
}
