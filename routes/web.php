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

// Pricing calculator - connected to POS products
Route::get('/price-calculator', fn() => view('pages.price-calculator'))->name('price-calculator');

// Pricing calculator product search API - no hard auth, graceful fallback
Route::get('/api/price-calculator/products', function (\Illuminate\Http\Request $request) {
    // Must be authenticated to query products
    if (!auth()->check()) {
        return response()->json(['products' => [], 'auth_required' => true], 200);
    }

    // Initialize org context from user if not already set in session
    \App\Services\OrganizationContext::initialize();

    $orgId = \App\Services\OrganizationContext::getCurrentOrganizationId()
             ?? auth()->user()?->organization_id;

    if (!$orgId) {
        return response()->json(['products' => [], 'error' => 'No organization context']);
    }

    $q = trim($request->get('q', ''));

    $products = \App\Models\Product::where('organization_id', $orgId)
        ->where('active', true)
        ->when(strlen($q) >= 1, function ($query) use ($q) {
            $query->where(function ($sub) use ($q) {
                $sub->where('name', 'like', "%{$q}%")
                    ->orWhere('name_nepali', 'like', "%{$q}%")
                    ->orWhere('name_hindi', 'like', "%{$q}%")
                    ->orWhere('name_romanized', 'like', "%{$q}%")
                    ->orWhere('sku', 'like', "%{$q}%");
            });
        })
        ->with(['variants' => fn($vq) => $vq->where('active', true)->orderBy('pack_size')])
        ->orderBy('name')
        ->limit(20)
        ->get(['id', 'name', 'name_nepali', 'name_hindi', 'name_romanized', 'sku'])
        ->map(fn($p) => [
            'id'       => $p->id,
            'name'     => $p->name,
            'display_name' => trim($p->name . ' (' . implode(' / ', array_filter([
                $p->name_nepali,
                $p->name_romanized,
                $p->name_hindi,
            ])) . ')', ' ()'),
            'sku'      => $p->sku,
            'variants' => $p->variants->map(fn($v) => [
                'id'                  => $v->id,
                'sku'                 => $v->sku,
                'pack_size'           => (float) $v->pack_size,
                'unit'                => strtolower($v->unit ?? 'g'),
                'cost_price'          => (float) $v->cost_price,
                'base_price'          => (float) $v->base_price,
                'mrp_india'           => (float) $v->mrp_india,
                'selling_price_nepal' => (float) $v->selling_price_nepal,
            ])->values(),
        ]);

    return response()->json(['products' => $products]);
});
