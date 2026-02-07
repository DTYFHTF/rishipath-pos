@php
    use App\Models\ProductBatch;
    use App\Models\StockLevel;
    use App\Models\InventoryMovement;
    use App\Models\PurchaseItem;
    use App\Models\SaleItem;
    use App\Services\StoreContext;
    
    $storeId = StoreContext::getCurrentStoreId();
    
    // Get all variants
    $variants = $product->variants()->with('stockLevels', 'batches')->get();
    
    // Calculate totals
    $totalStock = $variants->sum(function($variant) use ($storeId) {
        $stockLevel = $variant->stockLevels()
            ->when($storeId, fn($q) => $q->where('store_id', $storeId))
            ->first();
        return $stockLevel ? $stockLevel->quantity : 0;
    });
    
    $totalBatches = $variants->sum(function($variant) use ($storeId) {
        return $variant->batches()
            ->when($storeId, fn($q) => $q->where('store_id', $storeId))
            ->sum('quantity_remaining');
    });
    
    $inventoryValue = $variants->sum(function($variant) use ($storeId) {
        $batchValue = $variant->batches()
            ->when($storeId, fn($q) => $q->where('store_id', $storeId))
            ->selectRaw('SUM(quantity_remaining * purchase_price) as total')
            ->value('total') ?? 0;
            
        if ($batchValue > 0) {
            return $batchValue;
        }
        
        $stockLevel = $variant->stockLevels()
            ->when($storeId, fn($q) => $q->where('store_id', $storeId))
            ->first();
        $qty = $stockLevel ? $stockLevel->quantity : 0;
        $cost = $variant->cost_price ?? ($variant->base_price * 0.6);
        return $qty * $cost;
    });
    
    // Recent movements
    $recentMovements = InventoryMovement::query()
        ->whereIn('product_variant_id', $variants->pluck('id'))
        ->when($storeId, fn($q) => $q->where('store_id', $storeId))
        ->with(['productVariant', 'user', 'store'])
        ->latest()
        ->limit(10)
        ->get();
    
    // Recent purchase items
    $recentPurchases = PurchaseItem::query()
        ->whereIn('product_variant_id', $variants->pluck('id'))
        ->with(['purchase.supplier', 'productVariant'])
        ->latest()
        ->limit(5)
        ->get();
    
    // Recent sale items
    $recentSales = SaleItem::query()
        ->whereIn('product_variant_id', $variants->pluck('id'))
        ->with(['sale.customer', 'productVariant'])
        ->latest()
        ->limit(5)
        ->get();

    // Bill-wise transactions (group purchases and sales by bill/invoice)
    $purchaseLines = PurchaseItem::query()
        ->whereIn('product_variant_id', $variants->pluck('id'))
        ->with(['purchase.supplier'])
        ->get();

    $saleLinesAll = SaleItem::query()
        ->whereIn('product_variant_id', $variants->pluck('id'))
        ->with(['sale.customer'])
        ->get();

    $bills = collect();

    foreach ($purchaseLines as $line) {
        if (! $line->purchase) continue;
        $key = 'purchase_'.$line->purchase->id;
        if (! $bills->has($key)) {
            $bills[$key] = (object) [
                'type' => 'Purchase',
                'bill_number' => $line->purchase->purchase_number ?? ('P#'.$line->purchase->id),
                'date' => $line->purchase->purchase_date ?? $line->purchase->created_at,
                'party' => $line->purchase->supplier->name ?? 'Supplier',
                'qty' => 0,
                'unit' => $line->product_variant?->unit ?? '',
                'unit_price' => 0,
                'total' => 0,
            ];
        }
        $bills[$key]->qty += $line->quantity_ordered;
        $bills[$key]->total += $line->line_total ?? ($line->quantity_ordered * ($line->unit_cost ?? 0));
        $bills[$key]->unit_price = $line->unit_cost ?? $bills[$key]->unit_price;
        $bills[$key]->record_id = $line->purchase->id;
    }

    foreach ($saleLinesAll as $line) {
        if (! $line->sale) continue;
        $key = 'sale_'.$line->sale->id;
        if (! $bills->has($key)) {
            $bills[$key] = (object) [
                'type' => 'Sale',
                'bill_number' => $line->sale->invoice_number ?? ('S#'.$line->sale->id),
                'date' => $line->sale->date ?? $line->sale->created_at,
                'party' => $line->sale->customer->name ?? 'Customer',
                'qty' => 0,
                'unit' => $line->product_variant?->unit ?? '',
                'unit_price' => 0,
                'total' => 0,
            ];
        }
        $bills[$key]->qty += $line->quantity;
        $bills[$key]->total += $line->line_total ?? ($line->quantity * ($line->unit_price ?? 0));
        $bills[$key]->unit_price = $line->unit_price ?? $bills[$key]->unit_price;
        $bills[$key]->record_id = $line->sale->id;
    }

    // Sort bills by date desc and take recent 15
    $bills = $bills->sortByDesc(fn($b) => $b->date)->values()->take(15);

    // Party transactions: recent customer & supplier ledger entries related to parties in these bills
    $customerIds = $saleLinesAll->pluck('sale.customer_id')->filter()->unique()->values();
    $supplierIds = $purchaseLines->pluck('purchase.supplier_id')->filter()->unique()->values();

    $customerEntries = \App\Models\CustomerLedgerEntry::query()
        ->when($customerIds->isNotEmpty(), fn($q) => $q->whereIn('customer_id', $customerIds))
        ->orderByDesc('transaction_date')
        ->limit(10)
        ->get();

    $supplierEntries = \App\Models\SupplierLedgerEntry::query()
        ->when($supplierIds->isNotEmpty(), fn($q) => $q->whereIn('supplier_id', $supplierIds))
        ->orderByDesc('transaction_date')
        ->limit(10)
        ->get();

    $partyEntries = $customerEntries->map(fn($e) => (object) [
        'type' => 'Customer',
        'party' => $e->customer->name ?? 'Customer',
        'date' => $e->transaction_date,
        'debit' => $e->debit_amount,
        'credit' => $e->credit_amount,
        'balance' => $e->balance,
        'notes' => $e->description ?? $e->notes,
    ])->concat($supplierEntries->map(fn($e) => (object) [
        'type' => 'Supplier',
        'party' => $e->supplier->name ?? 'Supplier',
        'date' => $e->transaction_date,
        'debit' => $e->debit_amount,
        'credit' => $e->credit_amount,
        'balance' => $e->balance,
        'notes' => $e->description ?? $e->notes,
    ]))->sortByDesc(fn($e) => $e->date)->values();

    try {
        $purchaseCreateUrl = \App\Filament\Resources\PurchaseResource::getUrl('create');
    } catch (\Throwable $e) {
        $purchaseCreateUrl = null;
    }

    $firstVariantId = $variants->first()?->id ?? null;
@endphp

<div class="space-y-4 p-4" x-data="{ activeTab: 'overview' }">
    {{-- Header Summary (compact) --}}
    <div class="flex flex-wrap items-center gap-3">
        <div class="flex items-center gap-2 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded px-3 py-2 text-sm">
            <div class="text-xs text-gray-500 hidden sm:block">Total stock</div>
            <div class="text-xs sm:hidden">📦</div>
            <div class="font-semibold text-lg text-gray-900 dark:text-gray-100">{{ number_format($totalStock) }}</div>
            <div class="text-xs text-gray-400 hidden md:inline">across variants</div>
        </div>

        <div class="flex items-center gap-2 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded px-3 py-2 text-sm">
            <div class="text-xs text-gray-500 hidden sm:block">Inventory value</div>
            <div class="text-xs sm:hidden">💰</div>
            <div class="font-semibold text-lg text-green-700 dark:text-green-200">₹{{ number_format($inventoryValue, 2) }}</div>
            <div class="text-xs text-gray-400 hidden md:inline">current valuation</div>
        </div>

        <div class="flex items-center gap-2 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded px-3 py-2 text-sm">
            <div class="text-xs text-gray-500 hidden sm:block">Variants</div>
            <div class="text-xs sm:hidden">🔢</div>
            <div class="font-semibold text-lg text-purple-700 dark:text-purple-200">{{ $variants->count() }}</div>
            <div class="text-xs text-gray-400 hidden md:inline">SKUs</div>
        </div>

        <div class="flex items-center gap-2 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded px-3 py-2 text-sm">
            <div class="text-xs text-gray-500 hidden sm:block">Batch tracked</div>
            <div class="text-xs sm:hidden">📋</div>
            <div class="font-semibold text-lg text-orange-700 dark:text-orange-200">{{ number_format($totalBatches) }}</div>
            <div class="text-xs text-gray-400 hidden md:inline">units</div>
        </div>
    </div>

    <div class="flex justify-between items-center">
        {{-- Tab Navigation --}}
        <div class="flex items-center gap-1 bg-gray-100 dark:bg-gray-900 rounded-lg p-1">
            <button 
                @click="activeTab = 'overview'" 
                :class="activeTab === 'overview' ? 'bg-white dark:bg-gray-800 shadow' : ''"
                class="px-4 py-2 text-sm font-medium rounded-md transition"
            >
                📊 Overview
            </button>
            <button 
                @click="activeTab = 'batches'" 
                :class="activeTab === 'batches' ? 'bg-white dark:bg-gray-800 shadow' : ''"
                class="px-4 py-2 text-sm font-medium rounded-md transition"
            >
                📦 Batches
            </button>
            <button 
                @click="activeTab = 'movements'" 
                :class="activeTab === 'movements' ? 'bg-white dark:bg-gray-800 shadow' : ''"
                class="px-4 py-2 text-sm font-medium rounded-md transition"
            >
                📈 Movements
            </button>
            <button 
                @click="activeTab = 'transactions'" 
                :class="activeTab === 'transactions' ? 'bg-white dark:bg-gray-800 shadow' : ''"
                class="px-4 py-2 text-sm font-medium rounded-md transition"
            >
                🧾 Transactions
            </button>
        </div>

        {{-- Quick Actions --}}
        <div class="flex items-center gap-2">
            @if($purchaseCreateUrl)
                <a href="{{ $purchaseCreateUrl }}" target="_blank" class="px-3 py-1.5 text-sm bg-indigo-600 text-white rounded hover:bg-indigo-700 transition">+ Purchase</a>
            @endif
            {{-- Adjust Stock button removed (manual adjustments deprecated) --}}
        </div>
    </div>

    {{-- Tab Content --}}
    <div class="mt-4">
        {{-- Overview Tab --}}
        <div x-show="activeTab === 'overview'" class="space-y-4">
            {{-- Consistency Check --}}
            @if($totalStock != $totalBatches && $totalBatches > 0)
                <div class="bg-yellow-50 dark:bg-yellow-950 border border-yellow-200 dark:border-yellow-800 rounded-lg p-3">
                    <div class="flex items-center gap-2">
                        <svg class="w-5 h-5 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                        <span class="text-sm font-medium text-yellow-800 dark:text-yellow-200">
                            Stock Mismatch: Stock Level ({{ $totalStock }}) vs Batch Total ({{ $totalBatches }}) differ by {{ abs($totalStock - $totalBatches) }} units
                        </span>
                    </div>
                </div>
            @endif

            {{-- Variants & Stock Levels --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">
        <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Variants & Stock</h3>
        </div>
        <div class="overflow-x-auto max-h-96 overflow-y-auto">
            <table class="w-full">
                <thead class="bg-gray-50 dark:bg-gray-900">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">SKU</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Pack Size</th>
                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400">Stock</th>
                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400">Batches</th>
                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400">Base Price</th>
                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400">MRP</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach($variants as $variant)
                        @php
                            $stockLevel = $variant->stockLevels()->when($storeId, fn($q) => $q->where('store_id', $storeId))->first();
                            $batchQty = $variant->batches()->when($storeId, fn($q) => $q->where('store_id', $storeId))->sum('quantity_remaining');
                        @endphp
                        <tr>
                            <td class="px-4 py-2 text-sm font-medium text-gray-900 dark:text-gray-100">{{ $variant->sku }}</td>
                            <td class="px-4 py-2 text-sm text-gray-600 dark:text-gray-400">{{ $variant->pack_size }} {{ $variant->unit }}</td>
                            <td class="px-4 py-2 text-sm text-right {{ $stockLevel && $stockLevel->quantity <= $stockLevel->reorder_level ? 'text-red-600 font-semibold' : 'text-gray-900 dark:text-gray-100' }}">
                                {{ $stockLevel ? number_format($stockLevel->quantity) : 0 }}
                            </td>
                            <td class="px-4 py-2 text-sm text-right text-gray-600 dark:text-gray-400">{{ number_format($batchQty) }}</td>
                            <td class="px-4 py-2 text-sm text-right text-gray-900 dark:text-gray-100">
                                @php
                                    $organization = auth()->user()?->organization;
                                    $price = \App\Services\PricingService::getSellingPrice($variant, $organization);
                                    $currency = \App\Services\PricingService::getCurrencySymbol($organization);
                                @endphp
                                {{ $currency }}{{ number_format($price, 2) }}
                            </td>
                            <td class="px-4 py-2 text-sm text-right text-gray-900 dark:text-gray-100">
                                @php
                                    $mrpPrice = $variant->mrp_india ?? \App\Services\PricingService::getSellingPrice($variant, $organization) * 1.12;
                                @endphp
                                {{ $currency }}{{ number_format($mrpPrice, 2) }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
        </div> {{-- End Overview Tab --}}

        {{-- Batches Tab --}}
        <div x-show="activeTab === 'batches'" class="space-y-4">
            @php
                $allBatches = ProductBatch::query()
                    ->whereIn('product_variant_id', $variants->pluck('id'))
                    ->when($storeId, fn($q) => $q->where('store_id', $storeId))
                    ->with(['productVariant', 'purchase', 'supplier'])
                    ->latest()
                    ->limit(20)
                    ->get();
            @endphp
    
    @if($allBatches->count() > 0)
        <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">
            <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Product Batches (Recent 20)</h3>
            </div>
            <div class="overflow-x-auto max-h-96 overflow-y-auto">
                <table class="w-full">
                    <thead class="bg-gray-50 dark:bg-gray-900">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Batch #</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">SKU</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Expiry</th>
                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400">Remaining</th>
                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400">Cost</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Purchase</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($allBatches as $batch)
                            <tr>
                                <td class="px-4 py-2 text-sm font-mono text-gray-900 dark:text-gray-100">{{ $batch->batch_number }}</td>
                                <td class="px-4 py-2 text-sm text-gray-600 dark:text-gray-400">{{ $batch->productVariant->sku }}</td>
                                <td class="px-4 py-2 text-sm">
                                    @if($batch->expiry_date)
                                        <span class="
                                            @if($batch->expiry_date < now()) text-red-600 font-semibold
                                            @elseif($batch->expiry_date < now()->addDays(30)) text-orange-600 font-semibold
                                            @else text-gray-900 dark:text-gray-100
                                            @endif
                                        ">
                                            {{ $batch->expiry_date->format('d/m/Y') }}
                                        </span>
                                    @else
                                        <span class="text-gray-400">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-sm text-right text-gray-900 dark:text-gray-100">{{ number_format($batch->quantity_remaining) }}</td>
                                <td class="px-4 py-2 text-sm text-right text-gray-900 dark:text-gray-100">₹{{ number_format($batch->purchase_price, 2) }}</td>
                                <td class="px-4 py-2 text-sm text-gray-600 dark:text-gray-400">
                                    {{ $batch->purchase ? $batch->purchase->purchase_number : '—' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
        </div> {{-- End Batches Tab --}}

        {{-- Movements Tab --}}
        <div x-show="activeTab === 'movements'" class="space-y-4">
    {{-- Recent Purchases --}}
    @if($recentPurchases->count() > 0)
        <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">
            <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Recent Purchases</h3>
            </div>
            <div class="overflow-x-auto max-h-96 overflow-y-auto">
                <table class="w-full">
                    <thead class="bg-gray-50 dark:bg-gray-900">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Date</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Purchase #</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Supplier</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">SKU</th>
                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400">Qty</th>
                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400">Unit Cost</th>
                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($recentPurchases as $item)
                            <tr>
                                <td class="px-4 py-2 text-sm text-gray-900 dark:text-gray-100">{{ $item->purchase->purchase_date->format('d/m/Y') }}</td>
                                <td class="px-4 py-2 text-sm font-mono text-gray-900 dark:text-gray-100">{{ $item->purchase->purchase_number }}</td>
                                <td class="px-4 py-2 text-sm text-gray-600 dark:text-gray-400">{{ $item->purchase->supplier->name ?? '—' }}</td>
                                <td class="px-4 py-2 text-sm text-gray-600 dark:text-gray-400">{{ $item->productVariant->sku }}</td>
                                <td class="px-4 py-2 text-sm text-right text-gray-900 dark:text-gray-100">{{ number_format($item->quantity_ordered) }}</td>
                                <td class="px-4 py-2 text-sm text-right text-gray-900 dark:text-gray-100">₹{{ number_format($item->unit_cost, 2) }}</td>
                                <td class="px-4 py-2 text-sm text-right font-semibold text-gray-900 dark:text-gray-100">₹{{ number_format($item->line_total, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- Recent Sales --}}
    @if($recentSales->count() > 0)
        <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">
            <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Recent Sales</h3>
            </div>
            <div class="overflow-x-auto max-h-96 overflow-y-auto">
                <table class="w-full">
                    <thead class="bg-gray-50 dark:bg-gray-900">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Date</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Sale #</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Customer</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">SKU</th>
                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400">Qty</th>
                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400">Price</th>
                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($recentSales as $item)
                            <tr>
                                <td class="px-4 py-2 text-sm text-gray-900 dark:text-gray-100">{{ $item->sale->date->format('d/m/Y') }}</td>
                                <td class="px-4 py-2 text-sm font-mono text-gray-900 dark:text-gray-100">{{ $item->sale->invoice_number }}</td>
                                <td class="px-4 py-2 text-sm text-gray-600 dark:text-gray-400">{{ $item->sale->customer->name ?? 'Walk-in' }}</td>
                                <td class="px-4 py-2 text-sm text-gray-600 dark:text-gray-400">{{ $item->productVariant->sku }}</td>
                                <td class="px-4 py-2 text-sm text-right text-gray-900 dark:text-gray-100">{{ number_format($item->quantity) }}</td>
                                <td class="px-4 py-2 text-sm text-right text-gray-900 dark:text-gray-100">₹{{ number_format($item->unit_price, 2) }}</td>
                                <td class="px-4 py-2 text-sm text-right font-semibold text-gray-900 dark:text-gray-100">₹{{ number_format($item->line_total, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
    
    {{-- Inventory Movements --}}
    @if($recentMovements->count() > 0)
        <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">
            <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Inventory Timeline (Last 10)</h3>
            </div>
            <div class="overflow-x-auto max-h-96 overflow-y-auto">
                <table class="w-full">
                    <thead class="bg-gray-50 dark:bg-gray-900">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Date</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Type</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">SKU</th>
                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400">Qty</th>
                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400">From</th>
                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400">To</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">User</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Notes</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($recentMovements as $movement)
                            <tr>
                                <td class="px-4 py-2 text-sm text-gray-900 dark:text-gray-100">{{ $movement->created_at->format('d/m/Y H:i') }}</td>
                                <td class="px-4 py-2 text-sm">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                                        @if($movement->type === 'purchase') bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200
                                        @elseif($movement->type === 'sale') bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200
                                        @elseif($movement->type === 'adjustment') bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200
                                        @else bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200
                                        @endif
                                    ">
                                        {{ ucfirst($movement->type) }}
                                    </span>
                                </td>
                                <td class="px-4 py-2 text-sm text-gray-600 dark:text-gray-400">{{ $movement->productVariant->sku }}</td>
                                <td class="px-4 py-2 text-sm text-right font-semibold {{ $movement->quantity >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                    {{ $movement->quantity >= 0 ? '+' : '' }}{{ number_format($movement->quantity) }}
                                </td>
                                <td class="px-4 py-2 text-sm text-right text-gray-600 dark:text-gray-400">{{ number_format($movement->from_quantity) }}</td>
                                <td class="px-4 py-2 text-sm text-right text-gray-900 dark:text-gray-100">{{ number_format($movement->to_quantity) }}</td>
                                <td class="px-4 py-2 text-sm text-gray-600 dark:text-gray-400">{{ $movement->user->name ?? '—' }}</td>
                                <td class="px-4 py-2 text-sm text-gray-600 dark:text-gray-400">{{ Str::limit($movement->notes ?? '—', 30) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
        </div> {{-- End Movements Tab --}}

        {{-- Transactions Tab --}}
        <div x-show="activeTab === 'transactions'" class="space-y-4">
    {{-- Bill-wise Transactions --}}
    @if($bills->count() > 0)
        <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">
            <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Bill-wise Transactions</h3>
            </div>
            <div class="overflow-x-auto max-h-96 overflow-y-auto">
                <table class="w-full">
                    <thead class="bg-gray-50 dark:bg-gray-900">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Type</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Bill #</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Date</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Party</th>
                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400">Qty</th>
                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400">Unit</th>
                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($bills as $bill)
                            <tr>
                                <td class="px-4 py-2 text-sm text-gray-700 dark:text-gray-200">{{ $bill->type }}</td>
                                <td class="px-4 py-2 text-sm font-mono text-gray-900 dark:text-gray-100">
                                    @php
                                        $billUrl = null;
                                        if (! empty($bill->record_id)) {
                                            try {
                                                if ($bill->type === 'Purchase') {
                                                    $billUrl = \App\Filament\Resources\PurchaseResource::getUrl('view', ['record' => $bill->record_id]);
                                                } elseif ($bill->type === 'Sale') {
                                                    $billUrl = \App\Filament\Resources\SaleResource::getUrl('view', ['record' => $bill->record_id]);
                                                }
                                            } catch (\Throwable $e) {
                                                $billUrl = null;
                                            }
                                        }
                                    @endphp

                                    @if($billUrl)
                                        <a href="{{ $billUrl }}" target="_blank" rel="noopener" class="text-indigo-600 hover:underline inline-flex items-center gap-2">
                                            <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 13v6a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6M15 3h6v6M10 14L21 3" />
                                            </svg>
                                            <span>{{ $bill->bill_number }}</span>
                                        </a>
                                    @else
                                        {{ $bill->bill_number }}
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-sm text-gray-600 dark:text-gray-400">{{ \Carbon\Carbon::parse($bill->date)->format('d/m/Y') }}</td>
                                <td class="px-4 py-2 text-sm text-gray-600 dark:text-gray-400">{{ $bill->party }}</td>
                                <td class="px-4 py-2 text-sm text-right text-gray-900 dark:text-gray-100">{{ number_format($bill->qty) }}</td>
                                <td class="px-4 py-2 text-sm text-right text-gray-600 dark:text-gray-400">{{ $bill->unit }}</td>
                                <td class="px-4 py-2 text-sm text-right font-semibold text-gray-900 dark:text-gray-100">₹{{ number_format($bill->total, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- Party Transactions --}}
    @if($partyEntries->count() > 0)
        <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">
            <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Party Transactions</h3>
            </div>
            <div class="overflow-x-auto max-h-96 overflow-y-auto">
                <table class="w-full">
                    <thead class="bg-gray-50 dark:bg-gray-900">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Type</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Party</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Date</th>
                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400">Debit</th>
                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400">Credit</th>
                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400">Balance</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Notes</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($partyEntries as $entry)
                            <tr>
                                <td class="px-4 py-2 text-sm text-gray-700 dark:text-gray-200">{{ $entry->type }}</td>
                                <td class="px-4 py-2 text-sm text-gray-600 dark:text-gray-400">{{ $entry->party }}</td>
                                <td class="px-4 py-2 text-sm text-gray-600 dark:text-gray-400">{{ \Carbon\Carbon::parse($entry->date)->format('d/m/Y') }}</td>
                                <td class="px-4 py-2 text-sm text-right text-red-600">{{ $entry->debit ? '₹'.number_format($entry->debit, 2) : '-' }}</td>
                                <td class="px-4 py-2 text-sm text-right text-green-600">{{ $entry->credit ? '₹'.number_format($entry->credit, 2) : '-' }}</td>
                                <td class="px-4 py-2 text-sm text-right font-semibold text-gray-900 dark:text-gray-100">₹{{ number_format($entry->balance ?? 0, 2) }}</td>
                                <td class="px-4 py-2 text-sm text-gray-600 dark:text-gray-400">{{ \Illuminate\Support\Str::limit($entry->notes ?? '-', 40) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
        </div> {{-- End Transactions Tab --}}
    </div> {{-- End Tab Content --}}
</div>
