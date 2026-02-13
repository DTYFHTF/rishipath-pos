<?php

namespace App\Filament\Resources\BulkOrderInquiryResource\Pages;

use App\Filament\Resources\BulkOrderInquiryResource;
use App\Models\RetailStore;
use App\Services\OrganizationContext;
use Filament\Resources\Pages\CreateRecord;

class CreateBulkOrderInquiry extends CreateRecord
{
    protected static string $resource = BulkOrderInquiryResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['organization_id'] = $data['organization_id']
            ?? OrganizationContext::getCurrentOrganizationId()
            ?? auth()->user()->organization_id;

        $data['user_id'] = auth()->id();

        return $data;
    }

    /**
     * Pre-fill retail_store_id AND auto-sync shipping fields from URL query param ?store=X
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        if ($storeId = request()->query('store')) {
            $store = RetailStore::find($storeId);
            if ($store) {
                $data['retail_store_id'] = (int) $storeId;
                $data['name'] = $store->contact_person ?? $store->store_name;
                $data['phone'] = $store->contact_number;
                $data['company_name'] = $store->store_name;
                $data['shipping_address'] = $store->address;
                $data['shipping_area'] = $store->area;
                $data['shipping_landmark'] = $store->landmark;
                $data['shipping_city'] = $store->city;
                $data['shipping_state'] = $store->state;
                $data['shipping_pincode'] = $store->pincode;
                $data['shipping_country'] = $store->country ?? 'Nepal';
            }
        }

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->record]);
    }
}
