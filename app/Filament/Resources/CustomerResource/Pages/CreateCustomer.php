<?php

namespace App\Filament\Resources\CustomerResource\Pages;

use App\Filament\Resources\CustomerResource;
use App\Services\OrganizationContext;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateCustomer extends CreateRecord
{
    protected static string $resource = CustomerResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['organization_id'] = OrganizationContext::getCurrentOrganizationId();

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        // If coming from POS, redirect back to POS with the new customer
        if (session()->has('pos_return_session')) {
            session()->put('new_customer_id', $this->record->id);
            session()->forget('pos_return_session');

            return route('filament.admin.pages.enhanced-p-o-s');
        }

        // Otherwise, use default redirect
        return $this->getResource()::getUrl('index');
    }

    protected function getCreatedNotification(): ?Notification
    {
        // If going back to POS, skip the notification (we'll show it there)
        if (session()->has('new_customer_id')) {
            return null;
        }

        return parent::getCreatedNotification();
    }
}
