<?php

use App\Http\Controllers\DeployWebhookController;
use Illuminate\Support\Facades\Route;

// GitHub Actions deploy webhooks (no CSRF; verified via X-Hub-Signature-256 HMAC)
Route::post('/webhook/deploy', [DeployWebhookController::class, 'deploy'])
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class]);
Route::post('/webhook/assets', [DeployWebhookController::class, 'assets'])
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class]);

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
        ->header('Content-Disposition', 'inline; filename="invoice-'.$sale->invoice_number.'.pdf"');
})->middleware(['auth'])->name('filament.admin.resources.sales.invoice');

// Pricing calculator - connected to POS products
Route::get('/price-calculator', fn () => view('pages.price-calculator'))->name('price-calculator');

// Pricing calculator product search API - no hard auth, graceful fallback
Route::get('/api/price-calculator/products', function (\Illuminate\Http\Request $request) {
    // Must be authenticated to query products
    if (! auth()->check()) {
        return response()->json(['products' => [], 'auth_required' => true], 200);
    }

    // Initialize org context from user if not already set in session
    \App\Services\OrganizationContext::initialize();

    $orgId = \App\Services\OrganizationContext::getCurrentOrganizationId()
             ?? auth()->user()?->organization_id;

    if (! $orgId) {
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
        ->with(['variants' => fn ($vq) => $vq->where('active', true)->orderBy('pack_size')])
        ->orderBy('name')
        ->limit(20)
        ->get(['id', 'name', 'name_nepali', 'name_hindi', 'name_romanized', 'sku'])
        ->map(fn ($p) => [
            'id' => $p->id,
            'name' => $p->name,
            'display_name' => trim($p->name.' ('.implode(' / ', array_filter([
                $p->name_nepali,
                $p->name_romanized,
                $p->name_hindi,
            ])).')', ' ()'),
            'sku' => $p->sku,
            'variants' => $p->variants->map(fn ($v) => [
                'id' => $v->id,
                'sku' => $v->sku,
                'pack_size' => (float) $v->pack_size,
                'unit' => strtolower($v->unit ?? 'g'),
                'cost_price' => (float) $v->cost_price,
                'base_price' => (float) $v->base_price,
                'mrp_india' => (float) $v->mrp_india,
                'selling_price_nepal' => (float) $v->selling_price_nepal,
            ])->values(),
        ]);

    return response()->json(['products' => $products]);
});

// Saved blend recipes (product compositions) for the price calculator
Route::get('/api/price-calculator/recipes', function () {
    if (! auth()->check()) {
        return response()->json(['recipes' => [], 'auth_required' => true], 200);
    }

    \App\Services\OrganizationContext::initialize();
    $orgId = \App\Services\OrganizationContext::getCurrentOrganizationId()
             ?? auth()->user()?->organization_id;

    $recipes = \App\Models\Product::where('organization_id', $orgId)
        ->where('active', true)
        ->whereHas('compositions')
        ->with(['compositions.componentProduct.variants' => fn ($q) => $q->where('active', true)])
        ->orderBy('name')
        ->get()
        ->map(fn ($p) => [
            'id' => $p->id,
            'name' => $p->name,
            'name_nepali' => $p->name_nepali,
            'components' => $p->compositions->map(fn ($c) => [
                'name' => $c->name,
                'product_id' => $c->component_product_id,
                'qty' => (float) $c->quantity,
                'cost_per_100g' => $c->componentProduct?->costPerKg()
                    ? round($c->componentProduct->costPerKg() / 10, 2)
                    : null,
            ])->values(),
        ]);

    return response()->json(['recipes' => $recipes]);
});

// Ingredient pairing suggestions from the Ingredient Knowledge Base:
// given the products already in the blend, suggest what combines well.
Route::get('/api/price-calculator/suggestions', function (\Illuminate\Http\Request $request) {
    if (! auth()->check()) {
        return response()->json(['suggestions' => [], 'auth_required' => true], 200);
    }

    \App\Services\OrganizationContext::initialize();
    $orgId = \App\Services\OrganizationContext::getCurrentOrganizationId()
             ?? auth()->user()?->organization_id;

    $productIds = array_filter(array_map('intval', explode(',', $request->get('products', ''))));
    if (! $productIds) {
        return response()->json(['suggestions' => []]);
    }

    $inBlend = \App\Models\Ingredient::where('organization_id', $orgId)
        ->whereIn('product_id', $productIds)
        ->get();

    if ($inBlend->isEmpty()) {
        return response()->json(['suggestions' => []]);
    }

    // All KB ingredients that have live pricing and aren't already in the mix
    $candidates = \App\Models\Ingredient::where('organization_id', $orgId)
        ->where('active', true)
        ->whereNotNull('product_id')
        ->whereNotIn('product_id', $productIds)
        ->with(['product.variants' => fn ($q) => $q->where('active', true)])
        ->get();

    $suggestions = [];
    foreach ($inBlend as $ing) {
        $partners = preg_split('/[,;·|]/', (string) $ing->combines_well_with) ?: [];
        foreach ($partners as $partner) {
            $partner = trim($partner);
            if (strlen($partner) < 3) {
                continue;
            }
            foreach ($candidates as $cand) {
                if (isset($suggestions[$cand->id])) {
                    continue;
                }
                // "Black Pepper" matches ingredient "Black Pepper"; "Cardamom"
                // matches "Green Cardamom" etc. — loose contains both ways.
                $candPlain = trim(preg_replace('/\s*\(.*\)$/', '', $cand->name));
                if (stripos($candPlain, $partner) !== false || stripos($partner, $candPlain) !== false) {
                    $perKg = $cand->product?->costPerKg();
                    $suggestions[$cand->id] = [
                        'name' => $cand->name,
                        'product_id' => $cand->product_id,
                        'cost_per_100g' => $perKg ? round($perKg / 10, 2) : null,
                        'reason' => 'Combines well with '.$ing->name,
                    ];
                }
            }
        }
    }

    return response()->json(['suggestions' => array_slice(array_values($suggestions), 0, 8)]);
});

// Public helper tools - completely client-side, no auth required
Route::get('/helper-tools', fn () => view('helper-tools.index'))->name('helper-tools');
