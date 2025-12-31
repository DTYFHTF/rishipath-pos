<?php

namespace App\Filament\Resources\LoyaltyOverviewResource\Pages;

use App\Filament\Resources\LoyaltyOverviewResource;
use Filament\Resources\Pages\Page;

class LoyaltyOverview extends Page
{
    protected static string $resource = LoyaltyOverviewResource::class;

    protected static string $view = 'filament.resources.loyalty-overview-resource.pages.loyalty-overview';
}
