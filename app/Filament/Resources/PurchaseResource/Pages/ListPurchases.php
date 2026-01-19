<?php

namespace App\Filament\Resources\PurchaseResource\Pages;

use App\Filament\Resources\PurchaseResource;
use App\Services\StoreContext;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;

class ListPurchases extends ListRecords
{
    protected static string $resource = PurchaseResource::class;

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
