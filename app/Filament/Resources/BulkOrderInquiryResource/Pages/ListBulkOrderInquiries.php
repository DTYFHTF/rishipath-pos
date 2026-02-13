<?php

namespace App\Filament\Resources\BulkOrderInquiryResource\Pages;

use App\Filament\Resources\BulkOrderInquiryResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListBulkOrderInquiries extends ListRecords
{
    protected static string $resource = BulkOrderInquiryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
