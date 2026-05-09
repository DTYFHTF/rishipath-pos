<?php

namespace App\Filament\Resources\SalesAgentResource\Pages;

use App\Filament\Resources\SalesAgentResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSalesAgents extends ListRecords
{
    protected static string $resource = SalesAgentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
