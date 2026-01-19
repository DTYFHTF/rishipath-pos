<?php

namespace App\Filament\Resources\LoyaltyTierResource\Pages;

use App\Filament\Resources\LoyaltyTierResource;
use App\Services\OrganizationContext;
use Filament\Resources\Pages\CreateRecord;

class CreateLoyaltyTier extends CreateRecord
{
    protected static string $resource = LoyaltyTierResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['organization_id'] = OrganizationContext::getCurrentOrganizationId();
        return $data;
    }
}
