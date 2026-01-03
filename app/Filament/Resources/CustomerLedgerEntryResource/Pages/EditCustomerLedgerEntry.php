<?php

namespace App\Filament\Resources\CustomerLedgerEntryResource\Pages;

use App\Filament\Resources\CustomerLedgerEntryResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCustomerLedgerEntry extends EditRecord
{
    protected static string $resource = CustomerLedgerEntryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
