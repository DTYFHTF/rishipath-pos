<?php

namespace App\Filament\Resources\SaleResource\Pages;

use App\Filament\Resources\SaleResource;
use App\Services\StoreContext;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;

class ListSales extends ListRecords
{
    protected static string $resource = SaleResource::class;

    #[On('organization-switched')]
    #[On('store-switched')]
    public function refreshList(): void
    {
        // Trigger table refresh
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    protected function getTableQuery(): Builder
    {
        $query = parent::getTableQuery();
        $storeId = StoreContext::getCurrentStoreId();

        if ($storeId) {
            $query->where('store_id', $storeId);
        }

        return $query;
    }
}
