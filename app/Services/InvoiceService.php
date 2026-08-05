<?php

namespace App\Services;

use App\Models\BulkOrderInquiry;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Product;
use App\Models\Sale;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class InvoiceService
{
    /**
     * Generate PDF invoice for a sale
     *
     * @param  bool  $save  Whether to save to storage
     * @return \Barryvdh\DomPDF\PDF
     */
    public function generateInvoicePdf(Sale $sale, bool $save = false)
    {
        $sale->load(['store', 'items', 'customer', 'cashier', 'organization']);

        $data = [
            'sale' => $sale,
            'organization' => $sale->organization,
            'store' => $sale->store,
            'items' => $sale->items,
            'customer' => $sale->customer,
            'cashier' => $sale->cashier,
        ];

        $pdf = Pdf::loadView('invoices.invoice', $data)
            ->setPaper('a4')
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('isRemoteEnabled', true);

        if ($save) {
            $filename = $this->generateFilename($sale);
            $path = "invoices/{$filename}";
            Storage::disk('public')->put($path, $pdf->output());

            return [
                'pdf' => $pdf,
                'path' => $path,
            ];
        }

        return $pdf;
    }

    /**
     * Generate invoice filename
     */
    private function generateFilename(Sale $sale): string
    {
        $invoiceNumber = preg_replace('/[^A-Za-z0-9\-]/', '', $sale->invoice_number);
        $date = $sale->date->format('Ymd');

        return "invoice-{$invoiceNumber}-{$date}.pdf";
    }

    /**
     * Get invoice HTML for preview
     */
    public function getInvoiceHtml(Sale $sale): string
    {
        $sale->load(['store', 'items', 'customer', 'cashier', 'organization']);

        return view('invoices.invoice', [
            'sale' => $sale,
            'organization' => $sale->organization,
            'store' => $sale->store,
            'items' => $sale->items,
            'customer' => $sale->customer,
            'cashier' => $sale->cashier,
        ])->render();
    }

    /**
     * Generate and save invoice, return path string
     */
    public function generateAndSaveInvoice(Sale $sale): string
    {
        $result = $this->generateInvoicePdf($sale, true);

        return $result['path'];
    }

    /**
     * Delete invoice file if exists
     */
    public function deleteInvoice(Sale $sale): bool
    {
        $filename = $this->generateFilename($sale);
        $path = "invoices/{$filename}";

        if (Storage::disk('public')->exists($path)) {
            return Storage::disk('public')->delete($path);
        }

        return false;
    }

    // ─── Quotation from Bulk Order Inquiry ───────

    /**
     * Generate a quotation (Invoice record) from a BulkOrderInquiry.
     *
     * @param  array  $quotationData  [
     *                                'products' => [ ['product_id'=>?, 'product_name'=>?, 'quantity'=>?, 'unit_price'=>?, 'tax_rate'=>0, 'discount'=>0] ],
     *                                'discount_amount' => 0,
     *                                'discount_type'   => 'fixed',
     *                                'shipping_amount' => 0,
     *                                'terms_and_conditions' => '',
     *                                'notes' => '',
     *                                'due_days' => 30,
     *                                ]
     */
    public function generateQuotationFromBulkInquiry(BulkOrderInquiry $inquiry, array $quotationData): Invoice
    {
        return DB::transaction(function () use ($inquiry, $quotationData) {
            $orgId = $inquiry->organization_id;

            $invoice = Invoice::create([
                'organization_id' => $orgId,
                'invoice_number' => Invoice::generateNumber('quotation', $orgId),
                'type' => 'quotation',
                'status' => 'draft',
                'invoiceable_type' => BulkOrderInquiry::class,
                'invoiceable_id' => $inquiry->id,
                'customer_id' => null,
                'retail_store_id' => $inquiry->retail_store_id,
                'recipient_name' => $inquiry->name,
                'recipient_email' => $inquiry->email,
                'recipient_phone' => $inquiry->phone,
                'recipient_address' => implode(', ', array_filter([
                    $inquiry->shipping_address,
                    $inquiry->shipping_city,
                    $inquiry->shipping_state,
                    $inquiry->shipping_pincode,
                ])),
                'discount_amount' => $quotationData['discount_amount'] ?? 0,
                'discount_type' => $quotationData['discount_type'] ?? 'fixed',
                'shipping_amount' => $quotationData['shipping_amount'] ?? 0,
                'currency' => 'NPR',
                'issue_date' => now()->toDateString(),
                'due_date' => now()->addDays($quotationData['due_days'] ?? 30)->toDateString(),
                'terms_and_conditions' => $quotationData['terms_and_conditions'] ?? null,
                'notes' => $quotationData['notes'] ?? null,
            ]);

            // Create line items with price snapshots
            $products = $quotationData['products'] ?? [];
            $sort = 0;

            foreach ($products as $item) {
                $product = isset($item['product_id']) ? Product::find($item['product_id']) : null;

                $qty = (float) ($item['quantity'] ?? 0);
                $unitPrice = (float) ($item['unit_price'] ?? 0);
                $taxRate = (float) ($item['tax_rate'] ?? 0);
                $discount = (float) ($item['discount'] ?? 0);
                $base = $qty * $unitPrice;
                $afterDiscount = $base - $discount;
                $taxAmount = $afterDiscount * ($taxRate / 100);
                $lineTotal = $afterDiscount + $taxAmount;

                InvoiceLine::create([
                    'invoice_id' => $invoice->id,
                    'product_id' => $product?->id,
                    'product_variant_id' => $item['product_variant_id'] ?? null,
                    'item_name' => $item['product_name'] ?? $product?->name ?? 'Unknown',
                    'item_sku' => $product?->sku ?? null,
                    'item_description' => $item['description'] ?? $product?->description ?? null,
                    'quantity' => $qty,
                    'unit' => $item['unit'] ?? 'pcs',
                    'unit_price' => $unitPrice,
                    'discount_amount' => $discount,
                    'tax_amount' => $taxAmount,
                    'tax_rate' => $taxRate,
                    'line_total' => $lineTotal,
                    'sort_order' => $sort++,
                ]);
            }

            // Recalculate totals
            $invoice->recalculateTotals();

            // Update inquiry status
            $inquiry->update(['status' => 'quoted']);

            return $invoice->fresh(['lines']);
        });
    }
}
