<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FeedbackResource\Pages;
use App\Filament\Traits\HasPermissionCheck;
use App\Models\Feedback;
use App\Models\RetailStore;
use App\Services\OrganizationContext;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class FeedbackResource extends Resource
{
    use HasPermissionCheck;

    protected static ?string $model = Feedback::class;

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?string $navigationGroup = 'Notes & Feedback';

    protected static ?int $navigationSort = 10;

    protected static ?string $navigationLabel = 'All Feedback';

    public static function getNavigationBadge(): ?string
    {
        try {
            $orgId = OrganizationContext::getCurrentOrganizationId()
                ?? auth()->user()?->organization_id;

            if (! $orgId) {
                return null;
            }

            return (string) Feedback::where('organization_id', $orgId)
                ->topLevel()
                ->whereIn('status', ['new', 'in_progress'])
                ->count();
        } catch (\Throwable $e) {
            return null;
        }
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Feedback Details')
                    ->schema([
                        Forms\Components\Select::make('feedbackable_type')
                            ->label('Related To')
                            ->options([
                                RetailStore::class => 'Retail Store',
                            ])
                            ->default(RetailStore::class)
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn (Forms\Set $set) => $set('feedbackable_id', null)),

                        Forms\Components\Select::make('feedbackable_id')
                            ->label('Select Item')
                            ->required()
                            ->searchable()
                            ->getSearchResultsUsing(function (string $search, Forms\Get $get) {
                                $type = $get('feedbackable_type');
                                if (! $type) {
                                    return [];
                                }

                                $orgId = OrganizationContext::getCurrentOrganizationId()
                                    ?? auth()->user()?->organization_id;

                                if ($type === RetailStore::class) {
                                    return RetailStore::where('organization_id', $orgId)
                                        ->where('store_name', 'like', "%{$search}%")
                                        ->limit(50)
                                        ->pluck('store_name', 'id')
                                        ->toArray();
                                }

                                return [];
                            })
                            ->getOptionLabelUsing(function ($value, Forms\Get $get) {
                                $type = $get('feedbackable_type');
                                if (! $type || ! $value) {
                                    return '';
                                }

                                if ($type === RetailStore::class) {
                                    return RetailStore::find($value)?->store_name ?? '';
                                }

                                return '';
                            }),

                        Forms\Components\Select::make('type')
                            ->label('Type')
                            ->options([
                                'note' => 'Note',
                                'feedback' => 'Feedback',
                                'complaint' => 'Complaint',
                                'suggestion' => 'Suggestion',
                                'inquiry' => 'Inquiry',
                            ])
                            ->required()
                            ->default('note'),

                        Forms\Components\Select::make('priority')
                            ->options([
                                'low' => 'Low',
                                'medium' => 'Medium',
                                'high' => 'High',
                                'urgent' => 'Urgent',
                            ])
                            ->required()
                            ->default('medium'),

                        Forms\Components\TextInput::make('subject')
                            ->maxLength(255)
                            ->columnSpanFull(),

                        Forms\Components\Textarea::make('message')
                            ->required()
                            ->rows(4)
                            ->columnSpanFull(),

                        Forms\Components\FileUpload::make('attachments')
                            ->multiple()
                            ->maxFiles(5)
                            ->directory('feedback-attachments')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Status & Assignment')
                    ->schema([
                        Forms\Components\Select::make('status')
                            ->options([
                                'new' => 'New',
                                'in_progress' => 'In Progress',
                                'resolved' => 'Resolved',
                                'closed' => 'Closed',
                            ])
                            ->required()
                            ->default('new'),

                        Forms\Components\Select::make('assigned_to')
                            ->label('Assigned To')
                            ->relationship(
                                'assignedTo',
                                'name',
                                fn (Builder $query) => $query->where(
                                    'organization_id',
                                    OrganizationContext::getCurrentOrganizationId() ?? auth()->user()?->organization_id
                                )
                            )
                            ->searchable()
                            ->preload()
                            ->nullable(),
                    ])
                    ->columns(2)
                    ->visibleOn('edit'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('feedbackable_type')
                    ->label('Type')
                    ->formatStateUsing(fn (string $state) => class_basename($state))
                    ->badge()
                    ->color(fn (string $state) => match (class_basename($state)) {
                        'RetailStore' => 'info',
                        default => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('feedbackable.store_name')
                    ->label('Store')
                    ->searchable()
                    ->toggleable()
                    ->visible(fn () => request()->get('feedbackable_type') === RetailStore::class),

                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'complaint' => 'danger',
                        'urgent' => 'danger',
                        'feedback' => 'success',
                        'suggestion' => 'info',
                        'inquiry' => 'warning',
                        default => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('subject')
                    ->limit(40)
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('message')
                    ->limit(50)
                    ->searchable()
                    ->wrap()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'new' => 'danger',
                        'in_progress' => 'warning',
                        'resolved' => 'success',
                        'closed' => 'gray',
                        default => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('priority')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'urgent' => 'danger',
                        'high' => 'warning',
                        'medium' => 'info',
                        'low' => 'gray',
                        default => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('Created By')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('assignedTo.name')
                    ->label('Assigned To')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('replies_count')
                    ->label('Replies')
                    ->counts('replies')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('resolved_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('feedbackable_type')
                    ->label('Related To')
                    ->options([
                        RetailStore::class => 'Retail Stores',
                    ]),

                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'note' => 'Note',
                        'feedback' => 'Feedback',
                        'complaint' => 'Complaint',
                        'suggestion' => 'Suggestion',
                        'inquiry' => 'Inquiry',
                    ])
                    ->multiple(),

                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'new' => 'New',
                        'in_progress' => 'In Progress',
                        'resolved' => 'Resolved',
                        'closed' => 'Closed',
                    ])
                    ->multiple(),

                Tables\Filters\SelectFilter::make('priority')
                    ->options([
                        'urgent' => 'Urgent',
                        'high' => 'High',
                        'medium' => 'Medium',
                        'low' => 'Low',
                    ])
                    ->multiple(),

                Tables\Filters\SelectFilter::make('assigned_to')
                    ->relationship('assignedTo', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\Filter::make('unresolved')
                    ->query(fn (Builder $query) => $query->whereIn('status', ['new', 'in_progress']))
                    ->toggle(),

                Tables\Filters\Filter::make('assigned_to_me')
                    ->query(fn (Builder $query) => $query->where('assigned_to', auth()->id()))
                    ->toggle(),
            ])
            ->actions([
                Tables\Actions\Action::make('reply')
                    ->icon('heroicon-o-chat-bubble-left')
                    ->color('info')
                    ->form([
                        Forms\Components\Textarea::make('message')
                            ->required()
                            ->rows(3),
                    ])
                    ->action(function (Feedback $record, array $data) {
                        $record->addReply($data['message']);

                        \Filament\Notifications\Notification::make()
                            ->success()
                            ->title('Reply Added')
                            ->send();
                    }),

                Tables\Actions\Action::make('resolve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (Feedback $record) => ! $record->is_resolved)
                    ->requiresConfirmation()
                    ->action(fn (Feedback $record) => $record->markResolved()),

                Tables\Actions\Action::make('reopen')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->visible(fn (Feedback $record) => $record->is_resolved)
                    ->requiresConfirmation()
                    ->action(fn (Feedback $record) => $record->reopen()),

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

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('organization_id', OrganizationContext::getCurrentOrganizationId() ?? auth()->user()?->organization_id)
            ->topLevel()
            ->with(['user', 'assignedTo', 'feedbackable', 'replies']);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFeedbacks::route('/'),
            'retail-stores' => Pages\ListRetailStoreFeedbacks::route('/retail-stores'),
            'create' => Pages\CreateFeedback::route('/create'),
            'edit' => Pages\EditFeedback::route('/{record}/edit'),
            'view' => Pages\ViewFeedback::route('/{record}'),
        ];
    }
}
