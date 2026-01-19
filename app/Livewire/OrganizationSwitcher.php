<?php

namespace App\Livewire;

use App\Services\OrganizationContext;
use Filament\Notifications\Notification;
use Livewire\Component;

class OrganizationSwitcher extends Component
{
    public $currentOrganizationId;
    public $organizations;

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
        
        // Get organization name for toast
        $orgName = $this->organizations->firstWhere('id', $organizationId)?->name ?? 'Organization';

        // Show toast notification
        Notification::make()
            ->success()
            ->title('Organization Switched')
            ->body("Now viewing data for {$orgName}")
            ->send();

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
