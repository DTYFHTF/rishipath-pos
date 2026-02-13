<?php

namespace App\Filament\Resources\RetailStoreResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class VisitsRelationManager extends RelationManager
{
    protected static string $relationship = 'visits';

    protected static ?string $title = 'Field Visits';

    protected static ?string $icon = 'heroicon-o-clipboard-document-check';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Visit Info')
                    ->schema([
                        Forms\Components\DatePicker::make('visit_date')
                            ->default(now())
                            ->required()
                            ->native(false),

                        Forms\Components\TimePicker::make('visit_time')
                            ->default(now()->format('H:i'))
                            ->native(false),

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
                            ->required()
                            ->native(false),

                        Forms\Components\Select::make('visit_outcome')
                            ->options([
                                'successful' => 'Successful',
                                'partially_successful' => 'Partially Successful',
                                'unsuccessful' => 'Unsuccessful',
                                'rescheduled' => 'Rescheduled',
                                'store_closed' => 'Store Closed',
                            ])
                            ->native(false),
                    ])->columns(2),

                Forms\Components\Section::make('Quick Feedback Checklist')
                    ->schema([
                        Forms\Components\Grid::make(4)->schema([
                            Forms\Components\Toggle::make('stock_available')->label('Stock Available'),
                            Forms\Components\Toggle::make('good_display')->label('Good Display'),
                            Forms\Components\Toggle::make('clean_store')->label('Store Clean'),
                            Forms\Components\Toggle::make('staff_trained')->label('Staff Trained'),
                            Forms\Components\Toggle::make('has_refrigeration')->label('Has Refrigeration'),
                            Forms\Components\Toggle::make('order_placed')->label('Order Placed'),
                            Forms\Components\Toggle::make('payment_collected')->label('Payment Collected'),
                            Forms\Components\Toggle::make('has_competition')->label('Has Competition'),
                        ]),
                    ])->collapsible(),

                Forms\Components\Section::make('Store Ratings')
                    ->schema([
                        Forms\Components\Select::make('store_condition_rating')
                            ->label('Store Condition')
                            ->options([1 => '1 - Poor', 2 => '2 - Fair', 3 => '3 - Good', 4 => '4 - Very Good', 5 => '5 - Excellent'])
                            ->native(false),

                        Forms\Components\Select::make('customer_footfall_rating')
                            ->label('Customer Footfall')
                            ->options([1 => '1 - Very Low', 2 => '2 - Low', 3 => '3 - Moderate', 4 => '4 - High', 5 => '5 - Very High'])
                            ->native(false),

                        Forms\Components\Select::make('cooperation_rating')
                            ->label('Cooperation Level')
                            ->options([1 => '1 - Uncooperative', 2 => '2 - Somewhat', 3 => '3 - Neutral', 4 => '4 - Cooperative', 5 => '5 - Very Cooperative'])
                            ->native(false),
                    ])->columns(3)->collapsible(),

                Forms\Components\Section::make('Detailed Notes')
                    ->schema([
                        Forms\Components\Textarea::make('issues_found')
                            ->label('Issues Found')
                            ->rows(2),

                        Forms\Components\Textarea::make('action_items')
                            ->label('Action Items')
                            ->rows(2),

                        Forms\Components\Textarea::make('notes')
                            ->label('General Notes')
                            ->rows(2),

                        Forms\Components\Textarea::make('competitor_notes')
                            ->label('Competitor Notes')
                            ->rows(2),
                    ])->columns(2)->collapsible(),

                Forms\Components\Section::make('Order & Follow-up')
                    ->schema([
                        Forms\Components\TextInput::make('order_value')
                            ->label('Order Value')
                            ->numeric()
                            ->prefix('NPR'),

                        Forms\Components\DatePicker::make('next_visit_date')
                            ->label('Next Visit Date')
                            ->native(false),

                        Forms\Components\FileUpload::make('photos')
                            ->label('Visit Photos')
                            ->multiple()
                            ->image()
                            ->maxFiles(5)
                            ->directory('retail-store-visits')
                            ->columnSpanFull(),
                    ])->columns(2)->collapsible(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('visit_date')
            ->defaultSort('visit_date', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('visit_date')
                    ->date('d M Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('visit_time')
                    ->time('h:i A'),

                Tables\Columns\TextColumn::make('visitor.name')
                    ->label('Visited By')
                    ->sortable(),

                Tables\Columns\TextColumn::make('visit_purpose')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'sales' => 'primary',
                        'delivery' => 'success',
                        'collection' => 'warning',
                        'follow_up' => 'info',
                        'complaint' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('visit_outcome')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'successful' => 'success',
                        'partially_successful' => 'warning',
                        'unsuccessful' => 'danger',
                        'rescheduled' => 'info',
                        'store_closed' => 'gray',
                        default => 'gray',
                    }),

                Tables\Columns\IconColumn::make('order_placed')
                    ->boolean()
                    ->label('Order?'),

                Tables\Columns\TextColumn::make('order_value')
                    ->money('NPR')
                    ->label('Order Val.'),

                Tables\Columns\ViewColumn::make('feedback_summary')
                    ->view('filament.tables.columns.visit-feedback-badges')
                    ->label('Feedback'),

                Tables\Columns\TextColumn::make('average_rating')
                    ->label('Rating')
                    ->formatStateUsing(fn ($state) => $state ? "⭐ {$state}" : '-'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('visit_outcome')
                    ->options([
                        'successful' => 'Successful',
                        'partially_successful' => 'Partially Successful',
                        'unsuccessful' => 'Unsuccessful',
                        'rescheduled' => 'Rescheduled',
                        'store_closed' => 'Store Closed',
                    ]),

                Tables\Filters\SelectFilter::make('visited_by')
                    ->label('Visitor')
                    ->relationship('visitor', 'name'),

                Tables\Filters\TernaryFilter::make('order_placed')
                    ->label('Order Placed'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['organization_id'] = $this->getOwnerRecord()->organization_id;
                        $data['visited_by'] = auth()->id();

                        return $data;
                    })
                    ->after(function () {
                        $this->getOwnerRecord()->markVisited();
                    }),
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
            ]);
    }
}
