<?php

namespace App\Filament\Resources\SaleResource\Pages;

use App\Filament\Resources\SaleResource;
use App\Services\OrganizationContext;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;
use Illuminate\Validation\ValidationException;

class CreateSale extends CreateRecord
{
    protected static string $resource = SaleResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['organization_id'] = OrganizationContext::getCurrentOrganizationId();

        // Validate payment amount
        $totalAmount = (float) ($data['total_amount'] ?? 0);
        $amountPaid = (float) ($data['amount_paid'] ?? 0);

        if ($amountPaid < $totalAmount) {
            Notification::make()
                ->title('Payment Validation Failed')
                ->body("Amount paid (₹" . number_format($amountPaid, 2) . ") is less than total amount (₹" . number_format($totalAmount, 2) . "). Please collect full payment.")
                ->danger()
                ->persistent()
                ->send();

            throw ValidationException::withMessages([
                'amount_paid' => "Insufficient payment. Required: ₹" . number_format($totalAmount, 2) . ", Received: ₹" . number_format($amountPaid, 2),
            ]);
        }

        // Calculate and set change amount
        $data['amount_change'] = $amountPaid - $totalAmount;

        return $data;
    }

    protected function getCreatedNotification(): ?Notification
    {
        $sale = $this->getRecord();
        $changeAmount = (float) ($sale->amount_change ?? 0);

        $message = "Sale completed successfully!";
        if ($changeAmount > 0) {
            $message .= " \n💰 Return Change: ₹" . number_format($changeAmount, 2);
        }

        return Notification::make()
            ->success()
            ->title('Sale Completed')
            ->body($message)
            ->persistent()
            ->duration(10000);
    }
}
