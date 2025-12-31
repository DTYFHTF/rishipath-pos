<?php

namespace App\Services;

use App\Models\Sale;
use App\Models\Organization;

class ReceiptService
{
    public function generateReceipt(Sale $sale): string
    {
        $sale->load(['store', 'items.productVariant.product', 'customer', 'cashier']);
        $org = Organization::find($sale->organization_id);

        $receipt = $this->getHeader($org, $sale->store);
        $receipt .= $this->getSaleInfo($sale);
        $receipt .= $this->getItems($sale);
        $receipt .= $this->getTotals($sale);
        $receipt .= $this->getFooter($org);

        return $receipt;
    }

    private function getHeader(Organization $org, $store): string
    {
        $text = "========================================\n";
        $text .= $this->center($org->name) . "\n";
        $text .= $this->center($store->address ?? '') . "\n";
        $text .= $this->center("{$store->city}, {$store->state}") . "\n";
        $text .= $this->center("Phone: {$store->phone}") . "\n";
        if ($store->tax_number) {
            $text .= $this->center("GSTIN: {$store->tax_number}") . "\n";
        }
        $text .= "========================================\n\n";
        return $text;
    }

    private function getSaleInfo(Sale $sale): string
    {
        $text = "Receipt #: {$sale->receipt_number}\n";
        $text .= "Date: {$sale->date->format('d-M-Y')} Time: " . date('h:i A', strtotime($sale->time)) . "\n";
        $text .= "Cashier: {$sale->cashier->name}\n";
        
        if ($sale->customer_name) {
            $text .= "Customer: {$sale->customer_name}\n";
        }
        if ($sale->customer_phone) {
            $text .= "Phone: {$sale->customer_phone}\n";
        }
        
        $text .= "========================================\n\n";
        return $text;
    }

    private function getItems(Sale $sale): string
    {
        $text = sprintf("%-25s %5s %8s\n", "Item", "Qty", "Amount");
        $text .= "----------------------------------------\n";

        foreach ($sale->items as $item) {
            $name = $this->truncate($item->product_name, 25);
            $qty = number_format($item->quantity, 2);
            $amount = number_format($item->total, 2);
            
            $text .= sprintf("%-25s %5s %8s\n", $name, $qty, $amount);
            
            // Show price and tax details
            $details = "  @₹{$item->price_per_unit}";
            if ($item->tax_rate > 0) {
                $details .= " + GST {$item->tax_rate}%";
            }
            $text .= $details . "\n";
        }

        $text .= "========================================\n\n";
        return $text;
    }

    private function getTotals(Sale $sale): string
    {
        $text = sprintf("%25s: %12s\n", "Subtotal", "₹" . number_format($sale->subtotal, 2));
        
        if ($sale->discount_amount > 0) {
            $text .= sprintf("%25s: %12s\n", "Discount", "-₹" . number_format($sale->discount_amount, 2));
        }
        
        $text .= sprintf("%25s: %12s\n", "Tax (GST)", "₹" . number_format($sale->tax_amount, 2));
        $text .= "----------------------------------------\n";
        $text .= sprintf("%25s: %12s\n", "TOTAL", "₹" . number_format($sale->total_amount, 2));
        $text .= "========================================\n\n";

        // Payment details
        $text .= sprintf("%25s: %12s\n", "Payment Method", strtoupper($sale->payment_method));
        
        if ($sale->payment_method === 'cash') {
            $text .= sprintf("%25s: %12s\n", "Amount Paid", "₹" . number_format($sale->amount_paid, 2));
            $text .= sprintf("%25s: %12s\n", "Change", "₹" . number_format($sale->amount_change, 2));
        }
        
        $text .= "\n";
        return $text;
    }

    private function getFooter(Organization $org): string
    {
        $text = "\n";
        $text .= $this->center("Thank you for your purchase!") . "\n";
        $text .= $this->center("Visit us again") . "\n\n";
        $text .= $this->center("Powered by Rishipath POS") . "\n";
        $text .= "========================================\n";
        return $text;
    }

    private function center(string $text, int $width = 40): string
    {
        $len = strlen($text);
        if ($len >= $width) {
            return $text;
        }
        $padding = floor(($width - $len) / 2);
        return str_repeat(' ', $padding) . $text;
    }

    private function truncate(string $text, int $length): string
    {
        if (strlen($text) <= $length) {
            return $text;
        }
        return substr($text, 0, $length - 3) . '...';
    }

    public function printReceipt(Sale $sale): bool
    {
        // Generate receipt text
        $receipt = $this->generateReceipt($sale);
        
        // In a real implementation, this would send to a thermal printer
        // For now, we'll log it or return the text
        
        \Log::info("Receipt for Sale #{$sale->id}:\n" . $receipt);
        
        return true;
    }

    public function generateBarcode(string $code, string $type = 'CODE128'): string
    {
        // This would generate actual barcode image
        // For now, return a placeholder
        return "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==";
    }
}
