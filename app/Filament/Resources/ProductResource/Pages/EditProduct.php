<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use App\Services\PackPricing;
use App\Services\PackVariantGenerator;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('generatePackVariants')
                ->label('Generate pack variants')
                ->icon('heroicon-o-squares-plus')
                ->color('gray')
                ->visible(fn () => auth()->user()?->hasPermission('create_product_variants') ?? false)
                ->modalHeading('Generate pack variants')
                ->modalDescription('Packs this product already sells are left untouched — only missing ones are added, and any that were switched off are switched back on.')
                ->modalSubmitActionLabel('Generate')
                ->form([
                    Forms\Components\CheckboxList::make('packs')
                        ->label('Pack sizes')
                        ->options(PackVariantGenerator::options())
                        ->default(PackVariantGenerator::STANDARD_PACKS)
                        ->columns(3)
                        ->required(),
                    Forms\Components\TextInput::make('cost_per_kg')
                        ->label('Cost per kilo (Rs)')
                        ->numeric()
                        ->minValue(0)
                        ->required()
                        // Pre-fill from what the product's existing packs imply,
                        // so the usual case is confirming a number, not finding it.
                        ->default(fn () => PackPricing::costPerKg($this->record->loadMissing('variants')))
                        ->helperText('Used only for the packs being created now.'),
                ])
                ->action(function (array $data) {
                    $result = PackVariantGenerator::generate(
                        $this->record,
                        array_map('intval', $data['packs']),
                        (float) $data['cost_per_kg'],
                    );

                    $parts = [];
                    foreach (['created' => 'created', 'reactivated' => 'reactivated', 'skipped' => 'already there'] as $key => $word) {
                        if ($result[$key] > 0) {
                            $parts[] = "{$result[$key]} {$word}";
                        }
                    }

                    Notification::make()
                        ->success()
                        ->title($result['created'] || $result['reactivated'] ? 'Pack variants updated' : 'Nothing to add')
                        ->body(implode(', ', $parts) ?: 'Every pack you picked already exists.')
                        ->send();
                }),

            Actions\DeleteAction::make(),
        ];
    }
}
