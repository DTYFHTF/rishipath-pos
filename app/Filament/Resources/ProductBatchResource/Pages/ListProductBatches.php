<?php

namespace App\Filament\Resources\ProductBatchResource\Pages;

use App\Filament\Resources\ProductBatchResource;
use App\Services\StoreContext;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;

class ListProductBatches extends ListRecords
{
    protected static string $resource = ProductBatchResource::class;

    #[On('store-switched')]
    public function refreshList(): void
    {
        // Trigger table refresh when store switches
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
