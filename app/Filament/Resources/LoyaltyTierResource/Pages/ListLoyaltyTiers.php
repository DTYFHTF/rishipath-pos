<?php

namespace App\Filament\Resources\LoyaltyTierResource\Pages;

use App\Filament\Resources\LoyaltyTierResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListLoyaltyTiers extends ListRecords
{
    protected static string $resource = LoyaltyTierResource::class;

    protected $listeners = [
        'organization-switched' => '$refresh',
    ];

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
