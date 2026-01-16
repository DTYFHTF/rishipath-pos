<?php

namespace App\Services;

use App\Models\Sale;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    private $receiptService;

    public function __construct(ReceiptService $receiptService)
    {
        $this->receiptService = $receiptService;
    }

    /**
     * Send receipt via WhatsApp using Twilio
     */
    public function sendReceipt(Sale $sale, string $phoneNumber): bool
    {
        try {
            // Ensure phone number is in E.164 format (+[country code][number])
            $formattedPhone = $this->formatPhoneNumber($phoneNumber);

            if (! $formattedPhone) {
                Log::error('Invalid phone number format', ['phone' => $phoneNumber]);

                return false;
            }

            // Generate the receipt text
            $receiptText = $this->receiptService->generateReceipt($sale);

            // Build WhatsApp message
            $message = "Thank you for your purchase!\n\n".$receiptText;

            // Check if Twilio credentials are configured
            if (! config('services.twilio.account_sid') || ! config('services.twilio.auth_token')) {
                // Log the receipt instead if Twilio not configured
                Log::info('WhatsApp receipt (Twilio not configured)', [
                    'sale_id' => $sale->id,
                    'phone' => $formattedPhone,
                    'receipt' => $receiptText,
                ]);

                return true; // Return true in development mode
            }

            // Send via Twilio WhatsApp API
            $response = Http::withBasicAuth(
                config('services.twilio.account_sid'),
                config('services.twilio.auth_token')
            )->asForm()->post(
                'https://api.twilio.com/2010-04-01/Accounts/'.config('services.twilio.account_sid').'/Messages.json',
                [
                    'From' => 'whatsapp:'.config('services.twilio.whatsapp_from'),
                    'To' => 'whatsapp:'.$formattedPhone,
                    'Body' => $message,
                ]
            );

            if ($response->successful()) {
                Log::info('WhatsApp receipt sent successfully', [
                    'sale_id' => $sale->id,
                    'phone' => $formattedPhone,
                    'message_sid' => $response->json('sid'),
                ]);

                return true;
            } else {
                Log::error('Failed to send WhatsApp receipt', [
                    'sale_id' => $sale->id,
                    'phone' => $formattedPhone,
                    'error' => $response->body(),
                ]);

                return false;
            }
        } catch (\Exception $e) {
            Log::error('WhatsApp service error', [
                'sale_id' => $sale->id,
                'phone' => $phoneNumber,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Format phone number to E.164 format
     * Assumes Indian numbers by default (+91)
     */
    private function formatPhoneNumber(string $phone): ?string
    {
        // Remove all non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $phone);

        if (empty($phone)) {
            return null;
        }

        // If already has country code (starts with 91 and is 12 digits)
        if (strlen($phone) === 12 && str_starts_with($phone, '91')) {
            return '+'.$phone;
        }

        // If 10 digits, assume Indian number and add +91
        if (strlen($phone) === 10) {
            return '+91'.$phone;
        }

        // If 11 digits starting with 0, remove leading 0 and add +91
        if (strlen($phone) === 11 && str_starts_with($phone, '0')) {
            return '+91'.substr($phone, 1);
        }

        // If already has + and is 11+ digits, return as is
        if (strlen($phone) >= 11) {
            return '+'.$phone;
        }

        return null;
    }

    /**
     * Validate if WhatsApp service is configured
     */
    public function isConfigured(): bool
    {
        return ! empty(config('services.twilio.account_sid')) &&
               ! empty(config('services.twilio.auth_token')) &&
               ! empty(config('services.twilio.whatsapp_from'));
    }
}
