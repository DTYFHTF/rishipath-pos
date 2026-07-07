<?php

namespace App\Filament\Resources;

use App\Filament\Resources\IngredientResource\Pages;
use App\Models\Ingredient;
use App\Services\OrganizationContext;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class IngredientResource extends Resource
{
    protected static ?string $model = Ingredient::class;

    protected static ?string $navigationIcon = 'heroicon-o-book-open';

    protected static ?string $navigationGroup = 'Product Catalog';

    protected static ?string $navigationLabel = 'Ingredient Knowledge';

    protected static ?int $navigationSort = 5;

    protected static ?string $recordTitleAttribute = 'name';

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasPermission('view_ingredients') ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->hasPermission('manage_ingredients') ?? false;
    }

    public static function canEdit($record): bool
    {
        return auth()->user()?->hasPermission('manage_ingredients') ?? false;
    }

    public static function canDelete($record): bool
    {
        return auth()->user()?->hasPermission('manage_ingredients') ?? false;
    }

    public static function getEloquentQuery(): Builder
    {
        $orgId = OrganizationContext::getCurrentOrganizationId() ?? auth()->user()?->organization_id;

        return parent::getEloquentQuery()->where('organization_id', $orgId);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Tabs::make('Ingredient')
                    ->columnSpanFull()
                    ->tabs([
                        Forms\Components\Tabs\Tab::make('Identity')
                            ->icon('heroicon-m-identification')
                            ->schema([
                                Forms\Components\Hidden::make('organization_id')
                                    ->default(fn () => OrganizationContext::getCurrentOrganizationId() ?? auth()->user()?->organization_id),
                                Forms\Components\TextInput::make('code')
                                    ->label('IKB Code')
                                    ->placeholder('SP-CUMIN')
                                    ->required()
                                    ->maxLength(50),
                                Forms\Components\Select::make('category')
                                    ->options([
                                        'Spice' => 'Spice',
                                        'Seed' => 'Seed',
                                        'Dry Fruit & Nut' => 'Dry Fruit & Nut',
                                        'Salt / Sweetener / Other' => 'Salt / Sweetener / Other',
                                    ]),
                                Forms\Components\TextInput::make('name')->label('English Name')->required(),
                                Forms\Components\TextInput::make('name_nepali')->label('Nepali (नेपाली)'),
                                Forms\Components\TextInput::make('name_sanskrit')->label('Sanskrit'),
                                Forms\Components\TextInput::make('name_hindi')->label('Hindi'),
                                Forms\Components\TextInput::make('botanical_name'),
                                Forms\Components\TextInput::make('family'),
                                Forms\Components\TextInput::make('part_used'),
                                Forms\Components\Textarea::make('variants')->rows(2),
                                Forms\Components\Toggle::make('is_hero')->label('★ Hero ingredient'),
                                Forms\Components\Select::make('product_id')
                                    ->label('Linked POS product (for pricing)')
                                    ->relationship(
                                        'product',
                                        'name',
                                        fn (Builder $query) => $query->where('organization_id', OrganizationContext::getCurrentOrganizationId() ?? auth()->user()?->organization_id)
                                    )
                                    ->searchable()
                                    ->preload()
                                    ->nullable(),
                                Forms\Components\Toggle::make('active')->default(true),
                            ])->columns(2),

                        Forms\Components\Tabs\Tab::make('Ayurvedic Properties')
                            ->icon('heroicon-m-sparkles')
                            ->schema([
                                Forms\Components\TextInput::make('rasa')->label('Rasa (Taste)'),
                                Forms\Components\TextInput::make('guna')->label('Guna (Quality)'),
                                Forms\Components\TextInput::make('virya')->label('Virya (Potency)'),
                                Forms\Components\TextInput::make('vipaka')->label('Vipaka'),
                                Forms\Components\TextInput::make('dosha_effect')->label('Dosha Effect'),
                                Forms\Components\Textarea::make('karma')->label('Karma (Action)')->rows(2),
                            ])->columns(2),

                        Forms\Components\Tabs\Tab::make('Usage & Safety')
                            ->icon('heroicon-m-shield-check')
                            ->schema([
                                Forms\Components\Textarea::make('traditional_uses')->rows(2),
                                Forms\Components\Textarea::make('modern_research')->rows(2),
                                Forms\Components\Textarea::make('key_compounds')->rows(2),
                                Forms\Components\TextInput::make('dosage'),
                                Forms\Components\Textarea::make('best_time')->rows(2),
                                Forms\Components\Textarea::make('preparation_methods')->rows(2),
                                Forms\Components\Textarea::make('good_for')->label('Good For (Use)')->rows(2),
                                Forms\Components\Textarea::make('contraindications')->label('Avoid / Contraindications')->rows(2),
                            ])->columns(2),

                        Forms\Components\Tabs\Tab::make('Combinations')
                            ->icon('heroicon-m-squares-plus')
                            ->schema([
                                Forms\Components\Textarea::make('combines_well_with')->rows(2),
                                Forms\Components\Textarea::make('recipes_blends')->label('Recipes / Blends')->rows(2),
                                Forms\Components\Textarea::make('incompatible_caution')->label('Incompatible / Caution')->rows(2),
                                Forms\Components\Textarea::make('substitutes')->rows(2),
                                Forms\Components\Textarea::make('cross_references')->rows(2),
                                Forms\Components\Textarea::make('household_uses')->rows(2),
                                Forms\Components\Textarea::make('future_products')->label('Used In (Future Products)')->rows(2),
                            ])->columns(2),

                        Forms\Components\Tabs\Tab::make('Storage & Quality')
                            ->icon('heroicon-m-archive-box')
                            ->schema([
                                Forms\Components\TextInput::make('shelf_life'),
                                Forms\Components\TextInput::make('storage'),
                                Forms\Components\Textarea::make('quality_indicators')
                                    ->label('Quality Indicators & Adulteration Checks')
                                    ->rows(2)
                                    ->columnSpanFull(),
                                Forms\Components\TextInput::make('taste_sweet')->label('Sweet (0–5)')->numeric()->minValue(0)->maxValue(5),
                                Forms\Components\TextInput::make('taste_bitter')->label('Bitter (0–5)')->numeric()->minValue(0)->maxValue(5),
                                Forms\Components\TextInput::make('taste_pungent')->label('Pungent (0–5)')->numeric()->minValue(0)->maxValue(5),
                                Forms\Components\TextInput::make('aroma')->label('Aroma (0–5)')->numeric()->minValue(0)->maxValue(5),
                            ])->columns(2),

                        Forms\Components\Tabs\Tab::make('Search & Pipeline')
                            ->icon('heroicon-m-magnifying-glass')
                            ->schema([
                                Forms\Components\Textarea::make('search_tags')
                                    ->label('AI / Search Tags')
                                    ->rows(2)
                                    ->columnSpanFull(),
                                Forms\Components\Textarea::make('capsule_potential')->label('Capsule / Tablet Potential')->rows(2),
                                Forms\Components\Select::make('capsule_priority')
                                    ->options([
                                        '1 — Flagship' => '1 — Flagship',
                                        '2 — Strong' => '2 — Strong',
                                        '3 — Possible' => '3 — Possible',
                                        '4 — Minor / Blend' => '4 — Minor / Blend',
                                        '5 — Not for capsule' => '5 — Not for capsule',
                                    ]),
                            ])->columns(2),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Ingredient')
                    ->description(fn (Ingredient $record) => $record->name_nepali)
                    ->searchable(['name', 'name_nepali', 'name_hindi', 'name_sanskrit', 'search_tags'])
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\IconColumn::make('is_hero')
                    ->label('★')
                    ->boolean()
                    ->trueIcon('heroicon-s-star')
                    ->falseIcon('heroicon-o-minus')
                    ->trueColor('warning')
                    ->falseColor('gray'),
                Tables\Columns\TextColumn::make('category')
                    ->badge()
                    ->color(fn (?string $state) => match ($state) {
                        'Spice' => 'warning',
                        'Seed' => 'success',
                        'Dry Fruit & Nut' => 'info',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('virya')
                    ->label('Virya')
                    ->badge()
                    ->color(fn (?string $state) => str_contains((string) $state, 'Heat') ? 'danger' : 'info')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('dosha_effect')
                    ->label('Dosha')
                    ->limit(28)
                    ->toggleable(),
                Tables\Columns\TextColumn::make('good_for')
                    ->label('Good For')
                    ->limit(40)
                    ->wrap()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('product.name')
                    ->label('POS Product')
                    ->placeholder('— not linked —')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('capsule_priority')
                    ->label('Capsule Pipeline')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category')
                    ->options([
                        'Spice' => 'Spice',
                        'Seed' => 'Seed',
                        'Dry Fruit & Nut' => 'Dry Fruit & Nut',
                        'Salt / Sweetener / Other' => 'Salt / Sweetener / Other',
                    ]),
                Tables\Filters\TernaryFilter::make('is_hero')->label('Hero ingredients'),
                Tables\Filters\SelectFilter::make('virya')
                    ->options([
                        'Heating' => 'Heating',
                        'Cooling' => 'Cooling',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('name');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListIngredients::route('/'),
            'create' => Pages\CreateIngredient::route('/create'),
            'view' => Pages\ViewIngredient::route('/{record}'),
            'edit' => Pages\EditIngredient::route('/{record}/edit'),
        ];
    }
}
