<?php

namespace App\Livewire;

use App\Models\Store;
use App\Services\OrganizationContext;
use App\Services\StoreContext;
use Filament\Notifications\Notification;
use Livewire\Component;

class OrganizationSwitcher extends Component
{
    public ?int $currentOrganizationId;
    public ?iterable $organizations = null;

    public function mount()
    {
        // Ensure organization context is initialized (sets session defaults)
        OrganizationContext::initialize();

        $this->currentOrganizationId = OrganizationContext::getCurrentOrganizationId();
        $this->organizations = OrganizationContext::getAccessibleOrganizations();
    }

    public function switchOrganization($organizationId)
    {
        // Verify access
        if (!OrganizationContext::hasAccessToOrganization($organizationId)) {
            $this->addError('organization', 'You do not have access to this organization.');
            return;
        }

        OrganizationContext::setCurrentOrganizationId($organizationId);
        $this->currentOrganizationId = $organizationId;
        
        // Reset store context - find first store for this organization
        $firstStore = Store::where('organization_id', $organizationId)
            ->where('active', true)
            ->first();
        
        if ($firstStore) {
            StoreContext::setCurrentStoreId($firstStore->id);
        } else {
            // Clear store context if no stores available
            StoreContext::clearCurrentStore();
        }
        
        // Get organization name for toast
        $orgName = $this->organizations->firstWhere('id', $organizationId)?->name ?? 'Organization';

        // Dispatch event BEFORE redirect so components can update
        $this->dispatch('organization-switched', organizationId: $organizationId);
        
        // Small delay to allow event to propagate
        usleep(50000); // 50ms

        // Show toast notification
        Notification::make()
            ->success()
            ->title('Organization Switched')
            ->body("Now viewing data for {$orgName}")
            ->send();

        // Redirect to refresh page data
        return redirect(request()->header('Referer'));
    }

    public function render()
    {
        return view('livewire.organization-switcher');
    }
}
