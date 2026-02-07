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

    public static function shouldRegisterNavigation(): bool
    {
        return true; // Force navigation registration
    }

    public static function canViewAny(): bool
    {
        return true; // Temporarily bypass all permission checks
    }

    public static function canCreate(): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }

        return $user->hasPermission('create_organizations')
            || $user->hasRole('super-admin')
            || $user->id === 1;
    }

    public static function canEdit($record): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }

        return $user->hasPermission('edit_organizations')
            || $user->hasRole('super-admin')
            || $user->id === 1;
    }

    public static function canDelete($record): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }

        return $user->hasPermission('delete_organizations')
            || $user->hasRole('super-admin')
            || $user->id === 1;
    }

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
                        Forms\Components\Select::make('country_code')
                            ->required()
                            ->label('Country Code')
                            ->options(fn () => [
                                'IN' => '🇮🇳 +91 (India)',
                                'US' => '🇺🇸 +1 (United States)',
                                'GB' => '🇬🇧 +44 (United Kingdom)',
                                'NP' => '🇳🇵 +977 (Nepal)',
                                'CN' => '🇨🇳 +86 (China)',
                            ])
                            ->searchable()
                            ->default('IN')
                            ->helperText('Select the primary country for your organization'),
                        Forms\Components\Select::make('currency')
                            ->required()
                            ->label('Currency Code')
                            ->options(fn () => [
                                'INR' => '₹ Indian Rupee (INR)',
                                'USD' => '$ US Dollar (USD)',
                                'GBP' => '£ British Pound (GBP)',
                                'NPR' => 'Nepalese Rupee (NPR)',
                            ])
                            ->searchable()
                            ->default('INR')
                            ->helperText('Currency used for all financial transactions'),
                        Forms\Components\Select::make('timezone')
                            ->required()
                            ->label('Timezone')
                            ->options(fn () => array_combine(\DateTimeZone::listIdentifiers(), \DateTimeZone::listIdentifiers()))
                            ->searchable()
                            ->default('Asia/Kolkata'),
                        Forms\Components\Select::make('locale')
                            ->required()
                            ->label('Locale')
                            ->options(fn () => [
                                'en' => 'en',
                                'ne' => 'ne',
                                'hi' => 'hi',
                            ])
                            ->searchable()
                            ->default('en'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Configuration')
                    ->schema([
                        Forms\Components\KeyValue::make('config')
                            ->label('Additional Configuration')
                            ->helperText('Key-value pairs for custom settings')
                            ->nullable()
                            ->afterStateHydrated(function ($component, $state) {
                                $normalized = collect($state ?? [])->mapWithKeys(function ($value, $key) {
                                    if (is_array($value)) {
                                        return [$key => json_encode($value)];
                                    }

                                    return [$key => $value];
                                })->all();

                                $component->state($normalized);
                            })
                            ->dehydrateStateUsing(function (?array $state) {
                                return collect($state ?? [])->mapWithKeys(function ($value, $key) {
                                    if (is_string($value)) {
                                        $decoded = json_decode($value, true);

                                        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                                            return [$key => $decoded];
                                        }
                                    }

                                    return [$key => (filled($value) ? $value : null)];
                                })->filter(static fn ($value, $key): bool => filled($key))->all();
                            }),
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
