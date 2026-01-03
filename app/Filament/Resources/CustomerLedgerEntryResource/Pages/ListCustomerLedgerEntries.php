<?php

namespace App\Filament\Resources\CustomerLedgerEntryResource\Pages;

use App\Filament\Resources\CustomerLedgerEntryResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCustomerLedgerEntries extends ListRecords
{
    protected static string $resource = CustomerLedgerEntryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
