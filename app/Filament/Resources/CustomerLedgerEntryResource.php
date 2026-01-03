<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CustomerLedgerEntryResource\Pages;
use App\Filament\Resources\CustomerLedgerEntryResource\RelationManagers;
use App\Models\CustomerLedgerEntry;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class CustomerLedgerEntryResource extends Resource
{
    protected static ?string $model = CustomerLedgerEntry::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationGroup = 'Customers';
    protected static ?string $navigationLabel = 'Ledger Entries';
    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('customer_id')
                    ->relationship('customer', 'name')
                    ->required()
                    ->searchable(),
                
                Forms\Components\Select::make('entry_type')
                    ->options([
                        'sale' => 'Sale',
                        'payment' => 'Payment',
                        'credit_note' => 'Credit Note',
                        'opening_balance' => 'Opening Balance',
                        'adjustment' => 'Adjustment',
                    ])
                    ->required(),
                
                Forms\Components\DatePicker::make('transaction_date')
                    ->required()
                    ->default(now()),
                
                Forms\Components\TextInput::make('debit_amount')
                    ->numeric()
                    ->default(0)
                    ->prefix('₹'),
                
                Forms\Components\TextInput::make('credit_amount')
                    ->numeric()
                    ->default(0)
                    ->prefix('₹'),
                
                Forms\Components\Textarea::make('description')
                    ->required()
                    ->columnSpanFull(),
                
                Forms\Components\Select::make('payment_method')
                    ->options([
                        'cash' => 'Cash',
                        'card' => 'Card',
                        'upi' => 'UPI',
                        'bank_transfer' => 'Bank Transfer',
                        'cheque' => 'Cheque',
                        'credit' => 'Credit',
                    ])
                    ->visible(fn ($get) => $get('entry_type') === 'payment'),
                
                Forms\Components\TextInput::make('reference_number')
                    ->label('Reference/Invoice Number'),
                
                Forms\Components\Textarea::make('notes')
                    ->columnSpanFull(),
            ])
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('transaction_date')
                    ->date('d M Y')
                    ->sortable()
                    ->label('Date'),
                
                Tables\Columns\TextColumn::make('customer.name')
                    ->searchable()
                    ->sortable()
                    ->label('Customer'),
                
                Tables\Columns\TextColumn::make('reference_number')
                    ->searchable()
                    ->label('Reference'),
                
                Tables\Columns\TextColumn::make('description')
                    ->limit(40)
                    ->searchable(),
                
                Tables\Columns\TextColumn::make('entry_type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'sale' => 'info',
                        'payment' => 'success',
                        'credit_note' => 'warning',
                        'adjustment' => 'gray',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => ucwords(str_replace('_', ' ', $state))),
                
                Tables\Columns\TextColumn::make('debit_amount')
                    ->money('INR')
                    ->color('danger')
                    ->label('Debit')
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('credit_amount')
                    ->money('INR')
                    ->color('success')
                    ->label('Credit')
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('balance')
                    ->money('INR')
                    ->color(fn ($record) => $record->balance > 0 ? 'danger' : 'success')
                    ->sortable()
                    ->label('Balance'),
                
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'completed' => 'success',
                        'pending' => 'warning',
                        'overdue' => 'danger',
                        'cancelled' => 'gray',
                        default => 'gray',
                    }),
                
                Tables\Columns\TextColumn::make('store.name')
                    ->sortable()
                    ->toggleable(),
                
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('entry_type')
                    ->options([
                        'sale' => 'Sale',
                        'payment' => 'Payment',
                        'credit_note' => 'Credit Note',
                        'opening_balance' => 'Opening Balance',
                        'adjustment' => 'Adjustment',
                    ]),
                
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'completed' => 'Completed',
                        'cancelled' => 'Cancelled',
                        'overdue' => 'Overdue',
                    ]),
                
                Tables\Filters\SelectFilter::make('customer')
                    ->relationship('customer', 'name')
                    ->searchable(),
                
                Tables\Filters\Filter::make('transaction_date')
                    ->form([
                        Forms\Components\DatePicker::make('from'),
                        Forms\Components\DatePicker::make('until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('transaction_date', '>=', $date),
                            )
                            ->when(
                                $data['until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('transaction_date', '<=', $date),
                            );
                    }),
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
            ->defaultSort('transaction_date', 'desc');
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
            'index' => Pages\ListCustomerLedgerEntries::route('/'),
            'create' => Pages\CreateCustomerLedgerEntry::route('/create'),
            'edit' => Pages\EditCustomerLedgerEntry::route('/{record}/edit'),
        ];
    }
}
