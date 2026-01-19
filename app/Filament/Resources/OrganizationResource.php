<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OrganizationResource\Pages;
use App\Models\Organization;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class OrganizationResource extends Resource
{
    protected static ?string $model = Organization::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office';

    protected static ?string $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Basic Information')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->label('Organization Name'),
                        Forms\Components\TextInput::make('slug')
                            ->required()
                            ->maxLength(100)
                            ->unique(ignoreRecord: true)
                            ->label('Slug')
                            ->helperText('Unique identifier for the organization'),
                        Forms\Components\TextInput::make('legal_name')
                            ->maxLength(255)
                            ->label('Legal Name')
                            ->helperText('Official registered name'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Localization')
                    ->schema([
                        Forms\Components\TextInput::make('country_code')
                            ->required()
                            ->maxLength(2)
                            ->label('Country Code')
                            ->placeholder('IN, US, GB')
                            ->default('IN'),
                        Forms\Components\TextInput::make('currency')
                            ->required()
                            ->maxLength(3)
                            ->label('Currency Code')
                            ->placeholder('INR, USD, GBP')
                            ->default('INR'),
                        Forms\Components\TextInput::make('timezone')
                            ->required()
                            ->maxLength(50)
                            ->label('Timezone')
                            ->default('Asia/Kolkata'),
                        Forms\Components\TextInput::make('locale')
                            ->required()
                            ->maxLength(5)
                            ->label('Locale')
                            ->placeholder('en, ne, hi')
                            ->default('en'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Configuration')
                    ->schema([
                        Forms\Components\KeyValue::make('config')
                            ->label('Additional Configuration')
                            ->helperText('Key-value pairs for custom settings')
                            ->nullable(),
                        Forms\Components\Toggle::make('active')
                            ->label('Active')
                            ->default(true)
                            ->helperText('Inactive organizations are hidden from selection'),
                    ])
                    ->columns(1),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('slug')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('gray'),
                Tables\Columns\TextColumn::make('country_code')
                    ->label('Country')
                    ->sortable()
                    ->badge()
                    ->color('info'),
                Tables\Columns\TextColumn::make('currency')
                    ->sortable()
                    ->badge()
                    ->color('success'),
                Tables\Columns\TextColumn::make('stores_count')
                    ->counts('stores')
                    ->label('Stores')
                    ->sortable(),
                Tables\Columns\TextColumn::make('users_count')
                    ->counts('users')
                    ->label('Users')
                    ->sortable(),
                Tables\Columns\IconColumn::make('active')
                    ->boolean()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('active')
                    ->label('Status')
                    ->boolean()
                    ->trueLabel('Active only')
                    ->falseLabel('Inactive only')
                    ->native(false),
                Tables\Filters\SelectFilter::make('country_code')
                    ->label('Country')
                    ->options(fn () => Organization::distinct()->pluck('country_code', 'country_code')->toArray())
                    ->searchable(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('name');
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
            'index' => Pages\ListOrganizations::route('/'),
            'create' => Pages\CreateOrganization::route('/create'),
            'view' => Pages\ViewOrganization::route('/{record}'),
            'edit' => Pages\EditOrganization::route('/{record}/edit'),
        ];
    }
}
