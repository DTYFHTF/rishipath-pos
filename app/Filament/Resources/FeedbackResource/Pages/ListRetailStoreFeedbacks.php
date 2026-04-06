<?php

namespace App\Filament\Resources\FeedbackResource\Pages;

use App\Filament\Resources\FeedbackResource;
use App\Models\RetailStore;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListRetailStoreFeedbacks extends ListRecords
{
    protected static string $resource = FeedbackResource::class;

    protected static ?string $title = 'Retail Store Feedback';

    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';

    protected static ?string $navigationLabel = 'Retail Store Feedback';

    protected static ?string $navigationGroup = 'Notes & Feedback';

    protected static ?int $navigationSort = 11;

    public function getTableQuery(): Builder
    {
        return parent::getTableQuery()->forRetailStores();
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('create')
                ->icon('heroicon-o-plus')
                ->url(fn () => FeedbackResource::getUrl('create', ['feedbackable_type' => RetailStore::class])),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        $orgId = \App\Services\OrganizationContext::getCurrentOrganizationId()
            ?? auth()->user()?->organization_id;

        return (string) \App\Models\Feedback::where('organization_id', $orgId)
            ->topLevel()
            ->forRetailStores()
            ->whereIn('status', ['new', 'in_progress'])
            ->count();
    }
}
