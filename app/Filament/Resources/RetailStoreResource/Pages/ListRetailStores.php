<?php

namespace App\Filament\Resources\RetailStoreResource\Pages;

use App\Filament\Resources\RetailStoreResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListRetailStores extends ListRecords
{
    protected static string $resource = RetailStoreResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
