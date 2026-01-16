<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AlertRuleResource\Pages;
use App\Models\AlertRule;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AlertRuleResource extends Resource
{
    protected static ?string $model = AlertRule::class;

    protected static ?string $navigationIcon = 'heroicon-o-bell-alert';

    protected static ?string $navigationGroup = 'Reports & Alerts';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Alert Rules';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Alert Information')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\Select::make('type')
                            ->options([
                                'low_stock' => 'Low Stock Alert',
                                'high_value_sale' => 'High Value Sale',
                                'cashier_variance' => 'Cashier Variance',
                                'inventory_discrepancy' => 'Inventory Discrepancy',
                                'sales_target' => 'Sales Target',
                            ])
                            ->required()
                            ->reactive(),

                        Forms\Components\Select::make('frequency')
                            ->options([
                                'immediate' => 'Immediate',
                                'hourly' => 'Hourly',
                                'daily' => 'Daily',
                            ])
                            ->required()
                            ->default('hourly'),

                        Forms\Components\Toggle::make('active')
                            ->required()
                            ->default(true),

                        Forms\Components\Select::make('store_id')
                            ->relationship('store', 'name')
                            ->label('Specific Store (Optional)')
                            ->placeholder('All stores'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Alert Conditions')
                    ->schema([
                        Forms\Components\KeyValue::make('conditions')
                            ->addActionLabel('Add Condition')
                            ->keyLabel('Condition Name')
                            ->valueLabel('Value')
                            ->required(),
                    ]),

                Forms\Components\Section::make('Recipients')
                    ->schema([
                        Forms\Components\TagsInput::make('recipients')
                            ->required()
                            ->placeholder('Enter email addresses')
                            ->helperText('Enter email addresses who will receive this alert'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn ($state) => ucwords(str_replace('_', ' ', $state)))
                    ->searchable(),

                Tables\Columns\IconColumn::make('active')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\TextColumn::make('frequency')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'immediate' => 'danger',
                        'hourly' => 'warning',
                        'daily' => 'success',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('trigger_count')
                    ->label('Triggered')
                    ->numeric()
                    ->sortable(),

                Tables\Columns\TextColumn::make('store.name')
                    ->label('Store')
                    ->sortable()
                    ->placeholder('All Stores'),

                Tables\Columns\TextColumn::make('last_triggered_at')
                    ->dateTime()
                    ->sortable()
                    ->since(),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'low_stock' => 'Low Stock Alert',
                        'high_value_sale' => 'High Value Sale',
                        'cashier_variance' => 'Cashier Variance',
                        'inventory_discrepancy' => 'Inventory Discrepancy',
                        'sales_target' => 'Sales Target',
                    ]),

                Tables\Filters\TernaryFilter::make('active')
                    ->label('Status')
                    ->placeholder('All')
                    ->trueLabel('Active')
                    ->falseLabel('Inactive'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
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
            'index' => Pages\ListAlertRules::route('/'),
            'create' => Pages\CreateAlertRule::route('/create'),
            'edit' => Pages\EditAlertRule::route('/{record}/edit'),
        ];
    }
}
