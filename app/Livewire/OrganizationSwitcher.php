<?php

namespace App\Livewire;

use App\Services\OrganizationContext;
use Livewire\Component;

class OrganizationSwitcher extends Component
{
    public $currentOrganizationId;
    public $organizations;

    public function mount()
    {
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

        // Dispatch event for other components to react
        $this->dispatch('organization-switched', organizationId: $organizationId);

        // Redirect to refresh page data
        return redirect(request()->header('Referer'));
    }

    public function render()
    {
        return view('livewire.organization-switcher');
    }
}
