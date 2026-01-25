<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Filters -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
            <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
                <div>
                    <label class="block text-sm font-medium mb-1">Category</label>
                    <select 
                        wire:model.live="categoryId"
                        class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900 text-sm"
                    >
                        <option value="">All Categories</option>
                        @foreach(\App\Models\Category::all() as $category)
                            <option value="{{ $category->id }}">{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium mb-1">Search</label>
                    <input 
                        type="text"
                        wire:model.live.debounce.300ms="search"
                        placeholder="Search by name or SKU..."
                        class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900 text-sm"
                    />
                </div>
                <div class="flex items-end gap-2">
                    <label class="inline-flex items-center cursor-pointer">
                        <input type="checkbox" wire:model.live="showLowStock" class="rounded border-gray-300 dark:border-gray-700">
                        <span class="ml-2 text-sm text-yellow-600 dark:text-yellow-400">Low Stock</span>
                    </label>
                </div>
                <div class="flex items-end gap-2">
                    <label class="inline-flex items-center cursor-pointer">
                        <input type="checkbox" wire:model.live="showOutOfStock" class="rounded border-gray-300 dark:border-gray-700">
                        <span class="ml-2 text-sm text-red-600 dark:text-red-400">Out of Stock</span>
                    </label>
                </div>
            </div>
        </div>

        <!-- Metrics Cards (Ultra Compact - Inline) -->
        @php $metrics = $this->getMetrics(); @endphp
        <div class="flex flex-wrap gap-2">
            <div class="bg-white dark:bg-gray-800 rounded shadow-sm px-3 py-1.5 border-l-2 border-blue-500 flex items-center gap-2">
                <span class="text-xs text-gray-500">Total:</span>
                <span class="text-base font-bold">{{ number_format($metrics['total_items']) }}</span>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded shadow-sm px-3 py-1.5 border-l-2 border-green-500 flex items-center gap-2">
                <span class="text-xs text-gray-500">In Stock:</span>
                <span class="text-base font-bold text-green-600">{{ number_format($metrics['positive_stock']) }}</span>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded shadow-sm px-3 py-1.5 border-l-2 border-yellow-500 flex items-center gap-2">
                <span class="text-xs text-gray-500">Low:</span>
                <span class="text-base font-bold text-yellow-600">{{ number_format($metrics['low_stock']) }}</span>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded shadow-sm px-3 py-1.5 border-l-2 border-red-500 flex items-center gap-2">
                <span class="text-xs text-gray-500">Out:</span>
                <span class="text-base font-bold text-red-600">{{ number_format($metrics['out_of_stock']) }}</span>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded shadow-sm px-3 py-1.5 border-l-2 border-purple-500 flex items-center gap-2">
                <span class="text-xs text-gray-500">Cost:</span>
                <span class="text-sm font-bold">₹{{ number_format($metrics['cost_value'], 0) }}</span>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded shadow-sm px-3 py-1.5 border-l-2 border-indigo-500 flex items-center gap-2">
                <span class="text-xs text-gray-500">Sale:</span>
                <span class="text-sm font-bold">₹{{ number_format($metrics['sale_value'], 0) }}</span>
            </div>
        </div>

        <!-- Inventory Table (Compact) -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <th class="px-3 py-2 text-left font-semibold text-xs">Item</th>
                        <th class="px-3 py-2 text-center font-semibold text-xs">SKU</th>
                        <th class="px-3 py-2 text-right font-semibold text-xs">Qty</th>
                        <th class="px-3 py-2 text-right font-semibold text-xs">Cost</th>
                        <th class="px-3 py-2 text-right font-semibold text-xs">Sale Price</th>
                        <th class="px-3 py-2 text-center font-semibold text-xs">Last Updated</th>
                        <th class="px-3 py-2 text-center font-semibold text-xs">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($this->getInventory() as $stock)
                        @php
                            $variant = $stock->productVariant;
                            $product = $variant->product ?? null;
                            $isLow = $stock->quantity <= $stock->reorder_level && $stock->quantity > 0;
                            $isOut = $stock->quantity <= 0;
                        @endphp
                        <tr class="hover:bg-gray-200/30 dark:hover:bg-gray-700/50 transition-colors {{ $isOut ? 'bg-red-50 dark:bg-red-900/20' : ($isLow ? 'bg-yellow-50 dark:bg-yellow-900/20' : '') }}">
                            <td class="px-3 py-1.5">
                                <div class="font-medium text-sm">{{ $product->name ?? 'Unknown' }}</div>
                                <div class="text-xs text-gray-500">{{ $variant->pack_size ?? '' }}{{ $variant->unit ?? '' }}</div>
                            </td>
                            <td class="px-3 py-1.5 text-center font-mono text-xs">{{ $variant->sku ?? '-' }}</td>
                            <td class="px-3 py-1.5 text-right font-bold {{ $isOut ? 'text-red-600' : ($isLow ? 'text-yellow-600' : 'text-green-600') }}">
                                {{ number_format($stock->quantity, 0) }}
                            </td>
                            <td class="px-3 py-1.5 text-right text-sm">₹{{ number_format($variant->cost_price ?? 0, 2) }}</td>
                            <td class="px-3 py-1.5 text-right text-sm text-green-600 dark:text-green-400 font-semibold">
                                @php
                                    $organization = auth()->user()?->organization;
                                    $price = \App\Services\PricingService::getSellingPrice($variant, $organization);
                                    $currency = \App\Services\PricingService::getCurrencySymbol($organization);
                                @endphp
                                {{ $currency }}{{ number_format($price, 2) }}
                            </td>
                            <td class="px-3 py-1.5 text-center text-xs text-gray-500">
                                {{ $stock->last_movement_at ? \Carbon\Carbon::parse($stock->last_movement_at)->diffForHumans() : '-' }}
                            </td>
                            <td class="px-3 py-1.5">
                                <div class="flex items-center justify-center gap-1">
                                    <button 
                                        wire:click="openDetails({{ $variant->id }})"
                                        class="px-2 py-1 text-sm bg-purple-50 text-purple-800 dark:bg-purple-900/30 dark:text-purple-200 rounded hover:bg-purple-100 dark:hover:bg-purple-800 transition flex items-center gap-2"
                                        title="View Details"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M12 2a10 10 0 110 20 10 10 0 010-20z" />
                                        </svg>
                                        <span class="hidden sm:inline">Details</span>
                                    </button>
                                    <button 
                                    <!-- Stock In/Out actions removed for safety -->
                                    <button 
                                        wire:click="openTimeline({{ $variant->id }})"
                                        class="px-2 py-1 text-xs bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300 rounded hover:bg-blue-200 dark:hover:bg-blue-800 transition"
                                        title="View Timeline"
                                    >
                                        📜
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-8 text-center text-gray-500">
                                No inventory items found
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
            
            <div class="px-4 py-3 border-t border-gray-200 dark:border-gray-700">
                {{ $this->getInventory()->links() }}
            </div>
        </div>
    </div>

    <!-- Manual Stock modal removed/disabled -->

    <!-- Timeline Modal -->
    @if($showTimelineModal)
        <div class="fixed inset-0 bg-black/50 flex items-center justify-center z-50" wire:click.self="closeModals">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl p-6 w-full max-w-2xl max-h-[80vh] overflow-hidden flex flex-col">
                @php $variantDetails = $this->getVariantDetails(); @endphp
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h3 class="text-lg font-semibold">📜 Stock Timeline</h3>
                        @if($variantDetails)
                            <div class="text-sm text-gray-600 dark:text-gray-400">
                                {{ $variantDetails->product->name ?? 'Unknown' }} - {{ $variantDetails->pack_size }}{{ $variantDetails->unit }}
                            </div>
                        @endif
                    </div>
                    <button wire:click="closeModals" class="text-gray-500 hover:text-gray-700 dark:hover:text-gray-300">
                        ✕
                    </button>
                </div>
                
                <div class="overflow-y-auto flex-1 max-h-[500px]">
                    <div class="space-y-3">
                        @forelse($this->getTimelineMovements() as $movement)
                            <div class="border-l-4 {{ $movement->quantity > 0 && in_array($movement->type, ['purchase', 'return', 'adjustment']) ? 'border-green-500' : 'border-red-500' }} pl-4 py-2">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        <span class="px-2 py-0.5 text-xs rounded 
                                            @if($movement->type === 'purchase') bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200
                                            @elseif($movement->type === 'sale') bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200
                                            @elseif($movement->type === 'adjustment') bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200
                                            @elseif($movement->type === 'transfer') bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200
                                            @elseif($movement->type === 'damage') bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200
                                            @else bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200
                                            @endif">
                                            {{ ucfirst($movement->type) }}
                                        </span>
                                        <span class="font-semibold {{ str_contains($movement->notes ?? '', 'out') || $movement->type === 'sale' ? 'text-red-600' : 'text-green-600' }}">
                                            {{ $movement->type === 'sale' ? '-' : '+' }}{{ $movement->quantity }} {{ $movement->unit }}
                                        </span>
                                    </div>
                                    <span class="text-xs text-gray-500">
                                        {{ $movement->created_at->format('M d, Y H:i') }}
                                    </span>
                                </div>
                                <div class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                    {{ $movement->from_quantity }} → {{ $movement->to_quantity }}
                                </div>
                                @if($movement->notes)
                                    <div class="text-xs text-gray-500 mt-1">{{ $movement->notes }}</div>
                                @endif
                                @if($movement->user)
                                    <div class="text-xs text-gray-400 mt-1">By: {{ $movement->user->name }}</div>
                                @endif
                            </div>
                        @empty
                            <div class="text-center text-gray-500 py-8">
                                No movement history found
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Product Details Modal -->
    @if($showDetailsModal)
        <div class="fixed inset-0 bg-black/50 flex items-center justify-center z-[9999] p-4" role="dialog" aria-modal="true" wire:click.self="closeModals">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl w-full max-w-7xl max-h-[90vh] overflow-hidden flex flex-col" role="document">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                    <h2 class="text-xl font-semibold">
                        {{ $this->getDetailsProduct()?->name ?? 'Product Details' }}
                    </h2>
                    <button 
                        wire:click="closeModals"
                        class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200"
                    >
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
                <div class="overflow-y-auto flex-1 max-h-[600px]">
                    @if($this->getDetailsProduct())
                        @include('filament.pages.product-detail-modal', ['product' => $this->getDetailsProduct()])
                    @endif
                </div>
            </div>
        </div>
    @endif
</x-filament-panels::page>
