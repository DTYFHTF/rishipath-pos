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
                Forms\Components\TextInput::make('supplier_code')
                    ->required()
                    ->unique(ignoreRecord: true, modifyRuleUsing: fn ($rule, $get) => $rule->where('organization_id', $get('organization_id') ?? OrganizationContext::getCurrentOrganizationId())),
                Forms\Components\TextInput::make('name')
                    ->required(),
                Forms\Components\TextInput::make('contact_person'),
                Forms\Components\TextInput::make('phone')
                    ->tel(),
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
                        'Maharashtra' => 'Maharashtra',
                        'Delhi' => 'Delhi',
                        'Karnataka' => 'Karnataka',
                        'Tamil Nadu' => 'Tamil Nadu',
                        'West Bengal' => 'West Bengal',
                        'Gujarat' => 'Gujarat',
                        'Rajasthan' => 'Rajasthan',
                        'Uttar Pradesh' => 'Uttar Pradesh',
                    ])
                    ->searchable()
                    ->helperText('State for GST compliance'),
                Forms\Components\Select::make('country_code')
                    ->options([
                        'IN' => '🇮🇳 India',
                        'US' => '🇺🇸 United States',
                        'GB' => '🇬🇧 United Kingdom',
                        'CN' => '🇨🇳 China',
                        'NP' => '🇳🇵 Nepal',
                    ])
                    ->default('IN')
                    ->searchable(),
                Forms\Components\TextInput::make('tax_number')
                    ->helperText('GST Number for Indian suppliers'),
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
            ->modifyQueryUsing(fn ($query) => $query->where('organization_id', OrganizationContext::getCurrentOrganizationId() ?? auth()->user()->organization_id))
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
            'index' => Pages\ListSuppliers::route('/'),
            'create' => Pages\CreateSupplier::route('/create'),
            'edit' => Pages\EditSupplier::route('/{record}/edit'),
        ];
    }
}
