<?php

namespace App\Filament\Resources\SalesAgentResource\Pages;

use App\Filament\Resources\SalesAgentResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSalesAgent extends EditRecord
{
    protected static string $resource = SalesAgentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
