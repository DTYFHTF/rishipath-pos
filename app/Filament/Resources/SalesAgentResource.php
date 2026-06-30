<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SalesAgentResource\Pages;
use App\Models\SalesAgent;
use App\Services\OrganizationContext;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SalesAgentResource extends Resource
{
    protected static ?string $model = SalesAgent::class;

    protected static ?string $navigationIcon = 'heroicon-o-identification';

    protected static ?string $navigationGroup = 'Sales';

    protected static ?int $navigationSort = 5;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('organization_id')
                    ->relationship('organization', 'name')
                    ->default(fn () => OrganizationContext::getCurrentOrganizationId() ?? auth()->user()?->organization_id)
                    ->required()
                    ->hidden(),
                Forms\Components\TextInput::make('agent_code')
                    ->required()
                    ->maxLength(32)
                    ->unique(ignoreRecord: true, modifyRuleUsing: fn ($rule, $get) => $rule->where('organization_id', $get('organization_id') ?? OrganizationContext::getCurrentOrganizationId()))
                    ->helperText('Unique code used for field tracking and business cards.'),
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('phone')
                    ->required()
                    ->tel()
                    ->maxLength(30)
                    ->unique(ignoreRecord: true, modifyRuleUsing: fn ($rule, $get) => $rule->where('organization_id', $get('organization_id') ?? OrganizationContext::getCurrentOrganizationId())),
                Forms\Components\TextInput::make('email')
                    ->email()
                    ->maxLength(255),
                Forms\Components\TextInput::make('territory')
                    ->maxLength(255),
                Forms\Components\Textarea::make('address')
                    ->columnSpanFull(),
                Forms\Components\Grid::make(3)
                    ->schema([
                        Forms\Components\TextInput::make('commission_retail_pct')
                            ->label('Retail Commission %')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->default(0)
                            ->required(),
                        Forms\Components\TextInput::make('commission_wholesale_profit_pct')
                            ->label('Wholesale Profit Share %')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->default(30)
                            ->required(),
                        Forms\Components\TextInput::make('min_wholesale_amount')
                            ->label('Min Wholesale Amount (NPR)')
                            ->numeric()
                            ->minValue(0)
                            ->default(10000)
                            ->required(),
                    ]),
                Forms\Components\Textarea::make('notes')
                    ->columnSpanFull(),
                Forms\Components\Toggle::make('active')
                    ->default(true)
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function ($query) {
                $orgId = OrganizationContext::getCurrentOrganizationId() ?? auth()->user()?->organization_id ?? 1;

                return $query->where('organization_id', $orgId);
            })
            ->columns([
                Tables\Columns\TextColumn::make('agent_code')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('phone')->searchable(),
                Tables\Columns\TextColumn::make('territory')->searchable()->toggleable(),
                Tables\Columns\TextColumn::make('commission_retail_pct')->label('Retail %')->suffix('%'),
                Tables\Columns\TextColumn::make('commission_wholesale_profit_pct')->label('Wholesale %')->suffix('%'),
                Tables\Columns\TextColumn::make('min_wholesale_amount')->label('Min Wholesale')->money('NPR'),
                Tables\Columns\TextColumn::make('current_balance')
                    ->label('Current Balance')
                    ->formatStateUsing(fn ($record) => 'NPR '.number_format((float) $record->current_balance, 2)),
                Tables\Columns\IconColumn::make('active')->boolean(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSalesAgents::route('/'),
            'create' => Pages\CreateSalesAgent::route('/create'),
            'edit' => Pages\EditSalesAgent::route('/{record}/edit'),
        ];
    }
}
