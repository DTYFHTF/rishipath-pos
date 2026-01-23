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
            // Manual creation disabled - batches are created automatically via Purchase Orders
            Actions\Action::make('info')
                ->label('ℹ️ Batches are created via Purchase Orders')
                ->color('info')
                ->url(route('filament.admin.resources.purchases.index'))
                ->icon('heroicon-o-shopping-cart'),
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
