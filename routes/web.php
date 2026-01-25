<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Invoice preview route
Route::get('/admin/sales/{record}/invoice', function ($recordId) {
    $sale = \App\Models\Sale::findOrFail($recordId);
    $invoiceService = app(\App\Services\InvoiceService::class);
    
    // Generate PDF and stream it inline
    $pdf = $invoiceService->generateInvoicePdf($sale);
    
    return response($pdf->output())
        ->header('Content-Type', 'application/pdf')
        ->header('Content-Disposition', 'inline; filename="invoice-' . $sale->invoice_number . '.pdf"');
})->middleware(['auth'])->name('filament.admin.resources.sales.invoice');
