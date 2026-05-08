<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SupplierResource\Pages;
use App\Models\Supplier;
use App\Services\OrganizationContext;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SupplierResource extends Resource
{
    protected static ?string $model = Supplier::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('organization_id')
                    ->relationship('organization', 'name')
                    ->required(),
                Forms\Components\Placeholder::make('supplier_code')
                    ->label('Supplier Code')
                    ->content(fn ($record) => $record?->supplier_code ?? 'Will be auto-generated')
                    ->visible(fn ($record) => $record !== null),
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->unique(
                        table: 'suppliers',
                        column: 'name',
                        ignoreRecord: true,
                        modifyRuleUsing: fn ($rule) => $rule->where(
                            'organization_id',
                            OrganizationContext::getCurrentOrganizationId() ?? auth()->user()?->organization_id
                        )
                    ),
                Forms\Components\TextInput::make('contact_person'),
                Forms\Components\Grid::make(2)
                    ->schema([
                        Forms\Components\Select::make('country_code')
                            ->options([
                                'NP' => '🇳🇵 +977 Nepal',
                                'IN' => '🇮🇳 +91 India',
                                'US' => '🇺🇸 +1 United States',
                                'GB' => '🇬🇧 +44 United Kingdom',
                                'CN' => '🇨🇳 +86 China',
                            ])
                            ->default('NP')
                            ->searchable()
                            ->columnSpan(1),
                        Forms\Components\TextInput::make('phone')
                            ->tel()
                            ->required()
                            ->unique(
                                table: 'suppliers',
                                column: 'phone',
                                ignoreRecord: true,
                                modifyRuleUsing: fn ($rule) => $rule->where(
                                    'organization_id',
                                    OrganizationContext::getCurrentOrganizationId() ?? auth()->user()?->organization_id
                                )
                            )
                            ->columnSpan(1)
                            ->helperText('Required. Must be unique within your organization.'),
                    ]),
                Forms\Components\TextInput::make('email')
                    ->email(),
                Forms\Components\Textarea::make('address')
                    ->columnSpanFull()
                    ->helperText('Address helps distinguish multiple suppliers with same name. Include street, area, and postal code.'),
                Forms\Components\TextInput::make('city')
                    ->datalist([
                        'Mumbai', 'Delhi', 'Bangalore', 'Kolkata', 'Chennai', 'Kathmandu', 'Pokhara'
                    ])
                    ->helperText('City/region where supplier is located'),
                Forms\Components\TextInput::make('email')
                    ->email(),
                Forms\Components\Textarea::make('address')
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('city')
                    ->datalist([
                        'Mumbai', 'Delhi', 'Bangalore', 'Kolkata', 'Chennai'
                    ]),
                Forms\Components\Select::make('state')
                    ->options([
                        'Bagmati' => 'Bagmati',
                        'Madhesh' => 'Madhesh',
                        'Gandaki' => 'Gandaki',
                        'Lumbini' => 'Lumbini',
                        'Koshi' => 'Koshi',
                        'Karnali' => 'Karnali',
                        'Sudurpashchim' => 'Sudurpashchim',
                    ])
                    ->searchable()
                    ->helperText('Province / state for supplier address'),
                Forms\Components\TextInput::make('tax_number')
                    ->helperText('VAT or PAN number (if available)'),
                Forms\Components\RichEditor::make('notes')
                    ->toolbarButtons(['bold', 'italic', 'bulletList', 'link'])
                    ->helperText('Payment terms, delivery notes, etc.')
                    ->columnSpanFull(),
                Forms\Components\Toggle::make('active')
                    ->required(),
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
                Tables\Columns\TextColumn::make('organization.name')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('supplier_code')
                    ->searchable(),
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('contact_person')
                    ->searchable(),
                Tables\Columns\TextColumn::make('phone')
                    ->searchable(),
                Tables\Columns\TextColumn::make('email')
                    ->searchable(),
                Tables\Columns\TextColumn::make('city')
                    ->searchable(),
                Tables\Columns\TextColumn::make('state')
                    ->searchable(),
                Tables\Columns\TextColumn::make('country_code')
                    ->searchable(),
                Tables\Columns\TextColumn::make('tax_number')
                    ->searchable(),
                Tables\Columns\IconColumn::make('active')
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
                //
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make(),
                    
                    Tables\Actions\Action::make('view_ledger')
                        ->label('View Ledger')
                        ->icon('heroicon-o-document-text')
                        ->color('info')
                        ->url(fn ($record) => route('filament.admin.pages.supplier-ledger-report', ['supplier_id' => $record->id]))
                        ->openUrlInNewTab(),
                    
                    Tables\Actions\Action::make('view_purchases')
                        ->label('View Purchases')
                        ->icon('heroicon-o-shopping-bag')
                        ->color('success')
                        ->url(fn ($record) => route('filament.admin.resources.purchases.index', ['tableFilters[supplier_id][value]' => $record->id]))
                        ->openUrlInNewTab(),
                ]),
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
            'index' => Pages\ListSuppliers::route('/'),
            'create' => Pages\CreateSupplier::route('/create'),
            'edit' => Pages\EditSupplier::route('/{record}/edit'),
        ];
    }
}
