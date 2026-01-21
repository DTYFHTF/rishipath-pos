<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\Sale;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class InvoiceService
{
    /**
     * Generate PDF invoice for a sale
     *
     * @param Sale $sale
     * @param bool $save Whether to save to storage
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
}
