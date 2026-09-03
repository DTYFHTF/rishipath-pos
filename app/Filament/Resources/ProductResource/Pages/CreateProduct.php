<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use App\Services\OrganizationContext;
use App\Services\PackVariantGenerator;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['organization_id'] = OrganizationContext::getCurrentOrganizationId();

        return $data;
    }

    /**
     * Build the pack variants the form asked for. This runs after create
     * because a variant's SKU is derived from the saved product's own SKU,
     * which the Product model generates on insert.
     */
    protected function afterCreate(): void
    {
        // The two fields are dehydrated, so they never reach $data — read them
        // from the raw form state instead.
        $state = $this->form->getRawState();
        $packs = array_filter(array_map('intval', $state['generate_pack_sizes'] ?? []));

        if (! $this->record->has_variants || $packs === []) {
            return;
        }

        $result = PackVariantGenerator::generate(
            $this->record,
            $packs,
            (float) ($state['generate_cost_per_kg'] ?? 0),
        );

        if ($result['created'] === 0) {
            return;
        }

        Notification::make()
            ->success()
            ->title("{$result['created']} pack variant(s) created")
            ->body('Prices were derived from the cost per kilo. Adjust any of them under Product Variants.')
            ->send();
    }
}
