<?php

namespace App\Filament\Resources\FeedbackResource\Pages;

use App\Filament\Resources\FeedbackResource;
use Filament\Actions;
use Filament\Forms;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewFeedback extends ViewRecord
{
    protected static string $resource = FeedbackResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('reply')
                ->icon('heroicon-o-chat-bubble-left')
                ->color('info')
                ->form([
                    Forms\Components\Textarea::make('message')
                        ->required()
                        ->rows(3)
                        ->placeholder('Type your reply...'),
                ])
                ->action(function (array $data) {
                    $this->record->addReply($data['message']);

                    \Filament\Notifications\Notification::make()
                        ->success()
                        ->title('Reply Added')
                        ->send();

                    // Refresh to show new reply
                    $this->redirect(static::getResource()::getUrl('view', ['record' => $this->record]));
                }),

            Actions\Action::make('resolve')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn () => ! $this->record->is_resolved)
                ->requiresConfirmation()
                ->action(function () {
                    $this->record->markResolved();

                    \Filament\Notifications\Notification::make()
                        ->success()
                        ->title('Feedback Resolved')
                        ->send();
                }),

            Actions\Action::make('reopen')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->visible(fn () => $this->record->is_resolved)
                ->requiresConfirmation()
                ->action(function () {
                    $this->record->reopen();

                    \Filament\Notifications\Notification::make()
                        ->success()
                        ->title('Feedback Reopened')
                        ->send();
                }),

            Actions\EditAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Feedback Details')
                    ->schema([
                        Infolists\Components\TextEntry::make('feedbackable_type')
                            ->label('Related To')
                            ->formatStateUsing(fn (string $state) => class_basename($state))
                            ->badge()
                            ->color('info'),

                        Infolists\Components\TextEntry::make('feedbackable.store_name')
                            ->label('Store Name')
                            ->visible(fn () => $this->record->feedbackable_type === \App\Models\RetailStore::class),

                        Infolists\Components\TextEntry::make('feedbackable.company_name')
                            ->label('Company Name')
                            ->visible(fn () => $this->record->feedbackable_type === \App\Models\BulkOrderInquiry::class),

                        Infolists\Components\TextEntry::make('type')
                            ->badge()
                            ->color(fn (string $state) => match ($state) {
                                'complaint' => 'danger',
                                'feedback' => 'success',
                                'suggestion' => 'info',
                                'inquiry' => 'warning',
                                default => 'gray',
                            }),

                        Infolists\Components\TextEntry::make('priority')
                            ->badge()
                            ->color(fn (string $state) => match ($state) {
                                'urgent' => 'danger',
                                'high' => 'warning',
                                'medium' => 'info',
                                'low' => 'gray',
                                default => 'gray',
                            }),

                        Infolists\Components\TextEntry::make('status')
                            ->badge()
                            ->color(fn (string $state) => match ($state) {
                                'new' => 'danger',
                                'in_progress' => 'warning',
                                'resolved' => 'success',
                                'closed' => 'gray',
                                default => 'gray',
                            }),

                        Infolists\Components\TextEntry::make('subject')
                            ->columnSpanFull(),

                        Infolists\Components\TextEntry::make('message')
                            ->columnSpanFull()
                            ->prose(),
                    ])
                    ->columns(3),

                Infolists\Components\Section::make('Assignment & Tracking')
                    ->schema([
                        Infolists\Components\TextEntry::make('user.name')
                            ->label('Created By'),

                        Infolists\Components\TextEntry::make('assignedTo.name')
                            ->label('Assigned To')
                            ->placeholder('Not assigned'),

                        Infolists\Components\TextEntry::make('created_at')
                            ->dateTime(),

                        Infolists\Components\TextEntry::make('resolvedBy.name')
                            ->label('Resolved By')
                            ->visible(fn () => $this->record->is_resolved)
                            ->placeholder('—'),

                        Infolists\Components\TextEntry::make('resolved_at')
                            ->dateTime()
                            ->visible(fn () => $this->record->is_resolved)
                            ->placeholder('—'),
                    ])
                    ->columns(3),

                Infolists\Components\Section::make('Attachments')
                    ->schema([
                        Infolists\Components\TextEntry::make('attachments')
                            ->listWithLineBreaks()
                            ->formatStateUsing(function ($state) {
                                if (is_array($state)) {
                                    return collect($state)->map(function ($file) {
                                        return "<a href='/storage/{$file}' target='_blank' class='text-primary-600 hover:underline'>".basename($file).'</a>';
                                    })->join('<br>');
                                }

                                return '—';
                            })
                            ->html(),
                    ])
                    ->visible(fn () => ! empty($this->record->attachments)),

                Infolists\Components\Section::make("Replies ({$this->record->replies->count()})")
                    ->schema([
                        Infolists\Components\ViewEntry::make('replies')
                            ->view('filament.infolists.feedback-replies')
                            ->state($this->record->replies),
                    ])
                    ->visible(fn () => $this->record->replies->count() > 0),
            ]);
    }
}
