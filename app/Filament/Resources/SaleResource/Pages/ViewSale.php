<?php

namespace App\Filament\Resources\SaleResource\Pages;

use App\Filament\Resources\SaleResource;
use App\Services\WhatsAppService;
use App\Services\InvoiceService;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Notifications\Notification;

class ViewSale extends ViewRecord
{
    protected static string $resource = SaleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
            
            Actions\Action::make('sendWhatsApp')
                ->label('Send Receipt (Text)')
                ->icon('heroicon-o-chat-bubble-left-right')
                ->color('gray')
                ->visible(fn ($record) => $record->customer_phone)
                ->requiresConfirmation()
                ->modalHeading('Send Receipt via WhatsApp')
                ->modalDescription(fn ($record) => "Send receipt to {$record->customer_phone}?")
                ->action(function ($record) {
                    try {
                        $whatsapp = app(WhatsAppService::class);
                        $sent = $whatsapp->sendReceipt($record, $record->customer_phone);

                        if ($sent) {
                            Notification::make()
                                ->title('WhatsApp sent successfully!')
                                ->success()
                                ->send();
                        } else {
                            throw new \Exception('Failed to send WhatsApp receipt');
                        }
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Failed to send WhatsApp')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
            
            Actions\Action::make('downloadInvoice')
                ->label('Download Invoice')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('info')
                ->action(function ($record) {
                    try {
                        $invoice = app(InvoiceService::class);
                        $pdfPath = $invoice->generateAndSaveInvoice($record);

                        return response()->download(
                            storage_path('app/public/' . $pdfPath),
                            "invoice-{$record->receipt_number}.pdf"
                        );
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Failed to generate invoice')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
            
            Actions\Action::make('sendInvoiceWhatsApp')
                ->label('Send Invoice via WhatsApp')
                ->icon('heroicon-o-paper-airplane')
                ->color('success')
                ->visible(fn ($record) => $record->customer_phone)
                ->requiresConfirmation()
                ->modalHeading('Send Invoice via WhatsApp')
                ->modalDescription(fn ($record) => "Send PDF invoice to {$record->customer_phone}?")
                ->action(function ($record) {
                    try {
                        $whatsapp = app(WhatsAppService::class);
                        $invoice = app(InvoiceService::class);

                        // Generate invoice and get public URL
                        $pdfPath = $invoice->generateAndSaveInvoice($record);
                        $publicUrl = asset('storage/' . $pdfPath);

                        $result = $whatsapp->sendInvoicePdf($record, $record->customer_phone, $publicUrl);

                        if ($result['success']) {
                            Notification::make()
                                ->title('Invoice sent successfully!')
                                ->body("Sent to {$record->customer_phone}")
                                ->success()
                                ->send();
                        } else {
                            // If PDF send failed due to local URL, try text receipt
                            if (isset($result['error']) && str_contains($result['error'], 'Invalid media URL')) {
                                $textSent = $whatsapp->sendReceipt($record, $record->customer_phone);
                                if ($textSent) {
                                    Notification::make()
                                        ->info()
                                        ->title('Receipt Sent (Text Only)')
                                        ->body('Invoice PDF requires public URL. Text receipt sent instead.')
                                        ->send();
                                    return;
                                }
                            }
                            throw new \Exception($result['error'] ?? 'Unknown error');
                        }
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Failed to send invoice')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }
}
