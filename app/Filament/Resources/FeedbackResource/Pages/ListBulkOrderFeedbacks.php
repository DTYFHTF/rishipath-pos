<?php

namespace App\Filament\Resources\FeedbackResource\Pages;

use App\Filament\Resources\FeedbackResource;
use App\Models\BulkOrderInquiry;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListBulkOrderFeedbacks extends ListRecords
{
    protected static string $resource = FeedbackResource::class;

    protected static ?string $title = 'Bulk Order Feedback';

    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';

    protected static ?string $navigationLabel = 'Bulk Order Feedback';

    protected static ?string $navigationGroup = 'Notes & Feedback';

    protected static ?int $navigationSort = 12;

    public function getTableQuery(): Builder
    {
        return parent::getTableQuery()->forBulkOrders();
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('create')
                ->icon('heroicon-o-plus')
                ->url(fn () => FeedbackResource::getUrl('create', ['feedbackable_type' => BulkOrderInquiry::class])),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        $orgId = \App\Services\OrganizationContext::getCurrentOrganizationId()
            ?? auth()->user()?->organization_id;

        return (string) \App\Models\Feedback::where('organization_id', $orgId)
            ->topLevel()
            ->forBulkOrders()
            ->whereIn('status', ['new', 'in_progress'])
            ->count();
    }
}
