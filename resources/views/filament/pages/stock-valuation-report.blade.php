<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Filters -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium mb-1">Store</label>
                    <select 
                        wire:model.live="storeId"
                        class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900"
                    >
                        <option value="">All Stores</option>
                        @foreach(\App\Models\Store::where('active', true)->get() as $store)
                            <option value="{{ $store->id }}">{{ $store->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Category</label>
                    <select 
                        wire:model.live="categoryId"
                        class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900"
                    >
                        <option value="">All Categories</option>
                        @foreach(\App\Models\Category::all() as $category)
                            <option value="{{ $category->id }}">{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        <!-- Summary Cards -->
        @php $summary = $this->getValuationSummary(); @endphp
        <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-7 gap-4">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 border-l-4 border-blue-500">
                <div class="text-xs text-gray-600 dark:text-gray-400">Total SKUs</div>
                <div class="text-xl font-bold">{{ number_format($summary['total_items']) }}</div>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 border-l-4 border-green-500">
                <div class="text-xs text-gray-600 dark:text-gray-400">In Stock</div>
                <div class="text-xl font-bold text-green-600">{{ number_format($summary['items_in_stock']) }}</div>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 border-l-4 border-purple-500">
                <div class="text-xs text-gray-600 dark:text-gray-400">Total Qty</div>
                <div class="text-xl font-bold">{{ number_format($summary['total_quantity'], 0) }}</div>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 border-l-4 border-red-500">
                <div class="text-xs text-gray-600 dark:text-gray-400">Cost Value</div>
                <div class="text-xl font-bold text-red-600">₹{{ number_format($summary['total_cost_value'], 0) }}</div>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 border-l-4 border-indigo-500">
                <div class="text-xs text-gray-600 dark:text-gray-400">Sale Value</div>
                <div class="text-xl font-bold text-indigo-600">₹{{ number_format($summary['total_sale_value'], 0) }}</div>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 border-l-4 border-teal-500">
                <div class="text-xs text-gray-600 dark:text-gray-400">Potential Profit</div>
                <div class="text-xl font-bold text-teal-600">₹{{ number_format($summary['potential_profit'], 0) }}</div>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 border-l-4 border-amber-500">
                <div class="text-xs text-gray-600 dark:text-gray-400">Margin %</div>
                <div class="text-xl font-bold text-amber-600">{{ number_format($summary['margin_percent'], 1) }}%</div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Category Breakdown -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <h3 class="text-lg font-semibold mb-4">📊 Value by Category</h3>
                <div class="space-y-3">
                    @foreach($this->getCategoryBreakdown() as $cat)
                        @php
                            $maxValue = max(array_column($this->getCategoryBreakdown(), 'cost_value'));
                            $width = $maxValue > 0 ? ($cat['cost_value'] / $maxValue) * 100 : 0;
                        @endphp
                        <div>
                            <div class="flex justify-between text-sm mb-1">
                                <span class="font-medium">{{ $cat['name'] }}</span>
                                <span class="text-gray-600 dark:text-gray-400">₹{{ number_format($cat['cost_value'], 0) }}</span>
                            </div>
                            <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                                <div class="bg-blue-600 h-2 rounded-full" style="width: {{ $width }}%"></div>
                            </div>
                            <div class="flex justify-between text-xs text-gray-500 mt-1">
                                <span>{{ $cat['item_count'] }} items</span>
                                <span>{{ number_format($cat['total_quantity'], 0) }} qty</span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- Top Value Items -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <h3 class="text-lg font-semibold mb-4">💰 Top 10 by Stock Value</h3>
                <div class="space-y-2">
                    @foreach($this->getTopValueItems(10) as $item)
                        <div class="flex items-center justify-between p-2 rounded hover:bg-gray-200/30 dark:hover:bg-gray-700/50 transition-colors">
                            <div>
                                <div class="font-medium text-sm">{{ $item['product_name'] }}</div>
                                <div class="text-xs text-gray-500">{{ $item['variant'] }} • {{ $item['sku'] }}</div>
                            </div>
                            <div class="text-right">
                                <div class="font-bold text-sm text-green-600">₹{{ number_format($item['cost_value'], 0) }}</div>
                                <div class="text-xs text-gray-500">{{ number_format($item['quantity'], 0) }} units</div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <!-- Dead Stock -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold mb-4">⚠️ Dead Stock (No Sales in 30 Days)</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="px-3 py-2 text-left font-semibold">Product</th>
                            <th class="px-3 py-2 text-center font-semibold">SKU</th>
                            <th class="px-3 py-2 text-right font-semibold">Qty</th>
                            <th class="px-3 py-2 text-right font-semibold">Value</th>
                            <th class="px-3 py-2 text-center font-semibold">Last Movement</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @forelse($this->getDeadStock(30) as $item)
                            <tr class="hover:bg-gray-200/30 dark:hover:bg-gray-700/50 transition-colors">
                                <td class="px-3 py-2">
                                    <div class="font-medium">{{ $item['product_name'] }}</div>
                                    <div class="text-xs text-gray-500">{{ $item['variant'] }}</div>
                                </td>
                                <td class="px-3 py-2 text-center font-mono text-xs">{{ $item['sku'] }}</td>
                                <td class="px-3 py-2 text-right font-semibold text-orange-600">{{ number_format($item['quantity'], 0) }}</td>
                                <td class="px-3 py-2 text-right font-semibold text-red-600">₹{{ number_format($item['cost_value'], 0) }}</td>
                                <td class="px-3 py-2 text-center text-xs text-gray-500">
                                    {{ $item['last_movement'] ? \Carbon\Carbon::parse($item['last_movement'])->diffForHumans() : 'Never' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-3 py-8 text-center text-gray-500">
                                    🎉 No dead stock found! All items have been sold recently.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-filament-panels::page>
