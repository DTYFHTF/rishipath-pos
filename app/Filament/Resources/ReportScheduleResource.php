<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ReportScheduleResource\Pages;
use App\Filament\Resources\ReportScheduleResource\RelationManagers;
use App\Models\ReportSchedule;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ReportScheduleResource extends Resource
{
    protected static ?string $model = ReportSchedule::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-chart-bar';
    protected static ?string $navigationGroup = 'Reports & Alerts';
    protected static ?int $navigationSort = 1;
    protected static ?string $navigationLabel = 'Scheduled Reports';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Schedule Information')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('e.g., Daily Sales Summary'),
                        
                        Forms\Components\Select::make('report_type')
                            ->options([
                                'sales' => 'Sales Report',
                                'inventory' => 'Inventory Report',
                                'customer_analytics' => 'Customer Analytics',
                                'cashier_performance' => 'Cashier Performance',
                            ])
                            ->required()
                            ->reactive(),
                        
                        Forms\Components\Select::make('frequency')
                            ->options([
                                'daily' => 'Daily',
                                'weekly' => 'Weekly',
                                'monthly' => 'Monthly',
                                'custom' => 'Custom (Cron)',
                            ])
                            ->required()
                            ->default('daily')
                            ->reactive(),
                        
                        Forms\Components\TextInput::make('cron_expression')
                            ->visible(fn ($get) => $get('frequency') === 'custom')
                            ->placeholder('0 8 * * *')
                            ->helperText('Cron expression for custom scheduling'),
                        
                        Forms\Components\Select::make('format')
                            ->options([
                                'pdf' => 'PDF',
                                'excel' => 'Excel',
                                'both' => 'Both PDF and Excel',
                            ])
                            ->required()
                            ->default('pdf'),
                        
                        Forms\Components\Toggle::make('active')
                            ->required()
                            ->default(true)
                            ->helperText('Enable or disable this schedule'),
                    ])
                    ->columns(2),
                
                Forms\Components\Section::make('Report Parameters')
                    ->schema([
                        Forms\Components\KeyValue::make('parameters')
                            ->addActionLabel('Add Parameter')
                            ->keyLabel('Parameter Name')
                            ->valueLabel('Value')
                            ->helperText('Add report-specific parameters like store_id, date_range, etc.'),
                    ]),
                
                Forms\Components\Section::make('Recipients')
                    ->schema([
                        Forms\Components\TagsInput::make('recipients')
                            ->required()
                            ->placeholder('Enter email addresses')
                            ->helperText('Enter email addresses who will receive this report'),
                    ]),
                
                Forms\Components\Section::make('Schedule Status')
                    ->schema([
                        Forms\Components\Placeholder::make('last_run_at')
                            ->content(fn ($record) => $record?->last_run_at?->format('M d, Y h:i A') ?? 'Never'),
                        
                        Forms\Components\Placeholder::make('next_run_at')
                            ->content(fn ($record) => $record?->next_run_at?->format('M d, Y h:i A') ?? 'Not scheduled'),
                    ])
                    ->columns(2)
                    ->hidden(fn ($record) => $record === null),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('report_type')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match($state) {
                        'sales' => 'Sales Report',
                        'inventory' => 'Inventory Report',
                        'customer_analytics' => 'Customer Analytics',
                        'cashier_performance' => 'Cashier Performance',
                        default => $state,
                    })
                    ->searchable(),
                
                Tables\Columns\TextColumn::make('frequency')
                    ->badge()
                    ->color('success')
                    ->searchable(),
                
                Tables\Columns\TextColumn::make('format')
                    ->badge()
                    ->color('info')
                    ->searchable(),
                
                Tables\Columns\IconColumn::make('active')
                    ->boolean()
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('next_run_at')
                    ->label('Next Run')
                    ->dateTime()
                    ->sortable()
                    ->color(fn ($state) => $state && $state->isPast() ? 'danger' : 'success'),
                
                Tables\Columns\TextColumn::make('last_run_at')
                    ->label('Last Run')
                    ->dateTime()
                    ->sortable()
                    ->since()
                    ->toggleable(),
                
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('report_type')
                    ->options([
                        'sales' => 'Sales Report',
                        'inventory' => 'Inventory Report',
                        'customer_analytics' => 'Customer Analytics',
                        'cashier_performance' => 'Cashier Performance',
                    ]),
                
                Tables\Filters\SelectFilter::make('frequency')
                    ->options([
                        'daily' => 'Daily',
                        'weekly' => 'Weekly',
                        'monthly' => 'Monthly',
                        'custom' => 'Custom',
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
            ->defaultSort('next_run_at', 'asc');
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
            'index' => Pages\ListReportSchedules::route('/'),
            'create' => Pages\CreateReportSchedule::route('/create'),
            'edit' => Pages\EditReportSchedule::route('/{record}/edit'),
        ];
    }
}
