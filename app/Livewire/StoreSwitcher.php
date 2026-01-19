<?php

namespace App\Livewire;

use App\Services\StoreContext;
use Livewire\Component;

class StoreSwitcher extends Component
{
    public $currentStoreId;
    public $availableStores;
    public $showDropdown = false;

    public function mount()
    {
        $this->currentStoreId = StoreContext::getCurrentStoreId();
        $this->availableStores = StoreContext::getAccessibleStores();
    }

    public function switchStore($storeId)
    {
        if (StoreContext::hasAccessToStore($storeId)) {
            StoreContext::setCurrentStoreId($storeId);
            $this->currentStoreId = $storeId;
            $this->showDropdown = false;
            
            // Broadcast event to all listening components
            $this->dispatch('store-switched', storeId: $storeId);
            
            // Refresh the page to update all components
            $this->redirect(request()->header('Referer') ?: url()->current());
        }
    }

    public function render()
    {
        $currentStore = StoreContext::getCurrentStore();
        
        return view('livewire.store-switcher', [
            'currentStore' => $currentStore,
            'stores' => $this->availableStores
        ]);
    }
}
