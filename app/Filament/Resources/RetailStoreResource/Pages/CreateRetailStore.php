<?php

namespace App\Filament\Resources\RetailStoreResource\Pages;

use App\Filament\Resources\RetailStoreResource;
use App\Services\OrganizationContext;
use Filament\Resources\Pages\CreateRecord;

class CreateRetailStore extends CreateRecord
{
    protected static string $resource = RetailStoreResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();
        $data['organization_id'] = $data['organization_id']
            ?? OrganizationContext::getCurrentOrganizationId()
            ?? auth()->user()->organization_id;

        return $data;
    }
}
