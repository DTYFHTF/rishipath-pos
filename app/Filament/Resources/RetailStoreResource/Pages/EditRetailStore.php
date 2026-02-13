<?php

namespace App\Filament\Resources\RetailStoreResource\Pages;

use App\Filament\Resources\RetailStoreResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditRetailStore extends EditRecord
{
    protected static string $resource = RetailStoreResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('getDirections')
                ->label('Get Directions')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->url(fn () => $this->record->latitude && $this->record->longitude
                    ? "https://www.google.com/maps/dir/?api=1&destination={$this->record->latitude},{$this->record->longitude}"
                    : null)
                ->openUrlInNewTab()
                ->visible(fn () => $this->record->latitude && $this->record->longitude),

            Actions\DeleteAction::make(),
            Actions\ForceDeleteAction::make(),
            Actions\RestoreAction::make(),
        ];
    }
}
