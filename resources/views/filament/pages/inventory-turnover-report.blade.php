<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Filters -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold mb-4">Filter Options</h3>
            <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
                <div>
                    <label class="block text-sm font-medium mb-1">Start Date</label>
                    <input 
                        type="date" 
                        wire:model.live="startDate"
                        class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900"
                    />
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">End Date</label>
                    <input 
                        type="date" 
                        wire:model.live="endDate"
                        class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900"
                    />
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Store</label>
                    <select 
                        wire:model.live="storeId"
                        class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900"
                    >
                        <option value="">All Stores</option>
                        @foreach(\App\Models\Store::all() as $store)
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
                <div class="flex items-end">
                    <button 
                        wire:click="exportToExcel"
                        class="w-full px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition"
                    >
                        📥 Export to Excel
                    </button>
                </div>
            </div>
        </div>

        <!-- Turnover Metrics Cards -->
        @php
            $metrics = $this->getTurnoverMetrics();
        @endphp
        
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="bg-gradient-to-br from-indigo-500 to-indigo-600 rounded-lg shadow p-6 text-gray-900 dark:text-white">
                <div class="text-sm opacity-90">Turnover Rate</div>
                <div class="text-3xl font-bold mt-2">{{ $metrics['turnover_rate'] }}x</div>
                <div class="text-xs opacity-75 mt-1">Times inventory sold</div>
            </div>
            
            <div class="bg-gradient-to-br from-cyan-500 to-cyan-600 rounded-lg shadow p-6 text-gray-900 dark:text-white">
                <div class="text-sm opacity-90">Days to Sell</div>
                <div class="text-3xl font-bold mt-2">{{ $metrics['days_to_sell'] }}</div>
                <div class="text-xs opacity-75 mt-1">Average inventory age</div>
            </div>
            
            <div class="bg-gradient-to-br from-teal-500 to-teal-600 rounded-lg shadow p-6 text-gray-900 dark:text-white">
                <div class="text-sm opacity-90">Active Products</div>
                <div class="text-3xl font-bold mt-2">{{ $metrics['active_products'] }}</div>
                <div class="text-xs opacity-75 mt-1">{{ $metrics['inactive_products'] }} inactive</div>
            </div>
            
            <div class="bg-gradient-to-br from-amber-500 to-amber-600 rounded-lg shadow p-6 text-gray-900 dark:text-white">
                <div class="text-sm opacity-90">Avg Inventory Value</div>
                <div class="text-3xl font-bold mt-2">₹{{ number_format($metrics['avg_inventory_value'], 0) }}</div>
                <div class="text-xs opacity-75 mt-1">COGS: ₹{{ number_format($metrics['cogs'], 0) }}</div>
            </div>
        </div>

        <!-- ABC Analysis -->
        @php
            $abc = $this->getAbcAnalysis();
        @endphp
        
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold mb-4">ABC Analysis (Revenue-Based)</h3>
            <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                Class A products contribute 80% of revenue, Class B contributes 15%, and Class C contributes 5%
            </p>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="border-2 border-green-300 dark:border-green-700 rounded-lg p-4 bg-green-50 dark:bg-green-900/20">
                    <div class="text-2xl font-bold text-green-600 dark:text-green-400 mb-1">Class A</div>
                    <div class="text-sm text-gray-600 dark:text-gray-400">High Priority</div>
                    <div class="mt-3 space-y-1">
                        <div class="flex justify-between text-sm">
                            <span>Products:</span>
                            <span class="font-semibold">{{ $abc['A']['count'] }}</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span>Revenue:</span>
                            <span class="font-semibold">₹{{ number_format($abc['A']['revenue'], 2) }}</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span>Stock Value:</span>
                            <span class="font-semibold">₹{{ number_format($abc['A']['stock_value'], 2) }}</span>
                        </div>
                    </div>
                </div>

                <div class="border-2 border-yellow-300 dark:border-yellow-700 rounded-lg p-4 bg-yellow-50 dark:bg-yellow-900/20">
                    <div class="text-2xl font-bold text-yellow-600 dark:text-yellow-400 mb-1">Class B</div>
                    <div class="text-sm text-gray-600 dark:text-gray-400">Medium Priority</div>
                    <div class="mt-3 space-y-1">
                        <div class="flex justify-between text-sm">
                            <span>Products:</span>
                            <span class="font-semibold">{{ $abc['B']['count'] }}</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span>Revenue:</span>
                            <span class="font-semibold">₹{{ number_format($abc['B']['revenue'], 2) }}</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span>Stock Value:</span>
                            <span class="font-semibold">₹{{ number_format($abc['B']['stock_value'], 2) }}</span>
                        </div>
                    </div>
                </div>

                <div class="border-2 border-gray-300 dark:border-gray-700 rounded-lg p-4 bg-gray-50 dark:bg-gray-800/20">
                    <div class="text-2xl font-bold text-gray-600 dark:text-gray-400 mb-1">Class C</div>
                    <div class="text-sm text-gray-600 dark:text-gray-400">Low Priority</div>
                    <div class="mt-3 space-y-1">
                        <div class="flex justify-between text-sm">
                            <span>Products:</span>
                            <span class="font-semibold">{{ $abc['C']['count'] }}</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span>Revenue:</span>
                            <span class="font-semibold">₹{{ number_format($abc['C']['revenue'], 2) }}</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span>Stock Value:</span>
                            <span class="font-semibold">₹{{ number_format($abc['C']['stock_value'], 2) }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Fast vs Slow Moving Products -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Fast Moving Products -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <h3 class="text-lg font-semibold mb-4 text-green-600 dark:text-green-400">🚀 Fast Moving Products</h3>
                <div class="space-y-3">
                    @foreach($this->getFastMovingProducts(10) as $product)
                            <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4 hover:bg-gray-200/30 dark:hover:bg-gray-700/50 transition-colors">
                            <div class="flex items-center justify-between mb-2">
                                <div>
                                    <div class="font-semibold text-sm">{{ $product['product_name'] }}</div>
                                    <div class="text-xs text-gray-500">{{ $product['variant_name'] }}</div>
                                </div>
                                <span class="px-2 py-1 bg-{{ $product['abc_class'] === 'A' ? 'green' : ($product['abc_class'] === 'B' ? 'yellow' : 'gray') }}-100 
                                    text-{{ $product['abc_class'] === 'A' ? 'green' : ($product['abc_class'] === 'B' ? 'yellow' : 'gray') }}-800 
                                    dark:bg-{{ $product['abc_class'] === 'A' ? 'green' : ($product['abc_class'] === 'B' ? 'yellow' : 'gray') }}-900 
                                    dark:text-{{ $product['abc_class'] === 'A' ? 'green' : ($product['abc_class'] === 'B' ? 'yellow' : 'gray') }}-200 
                                    rounded text-xs font-medium">
                                    Class {{ $product['abc_class'] }}
                                </span>
                            </div>
                            <div class="grid grid-cols-3 gap-2 text-xs text-gray-600 dark:text-gray-400">
                                <div>
                                    <div class="text-gray-500">Turnover</div>
                                    <div class="font-bold text-green-600 dark:text-green-400">{{ $product['turnover_rate'] }}x</div>
                                </div>
                                <div>
                                    <div class="text-gray-500">Days to Sell</div>
                                    <div class="font-medium">{{ $product['days_to_sell'] }} days</div>
                                </div>
                                <div>
                                    <div class="text-gray-500">Stock</div>
                                    <div class="font-medium">{{ $product['current_stock'] }} units</div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- Slow Moving Products -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <h3 class="text-lg font-semibold mb-4 text-orange-600 dark:text-orange-400">🐌 Slow Moving Products</h3>
                <div class="space-y-3">
                    @foreach($this->getSlowMovingProducts(10) as $product)
                            <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4 hover:bg-gray-200/30 dark:hover:bg-gray-700/50 transition-colors">
                            <div class="flex items-center justify-between mb-2">
                                <div>
                                    <div class="font-semibold text-sm">{{ $product['product_name'] }}</div>
                                    <div class="text-xs text-gray-500">{{ $product['variant_name'] }}</div>
                                </div>
                                <span class="px-2 py-1 bg-{{ $product['abc_class'] === 'A' ? 'green' : ($product['abc_class'] === 'B' ? 'yellow' : 'gray') }}-100 
                                    text-{{ $product['abc_class'] === 'A' ? 'green' : ($product['abc_class'] === 'B' ? 'yellow' : 'gray') }}-800 
                                    dark:bg-{{ $product['abc_class'] === 'A' ? 'green' : ($product['abc_class'] === 'B' ? 'yellow' : 'gray') }}-900 
                                    dark:text-{{ $product['abc_class'] === 'A' ? 'green' : ($product['abc_class'] === 'B' ? 'yellow' : 'gray') }}-200 
                                    rounded text-xs font-medium">
                                    Class {{ $product['abc_class'] }}
                                </span>
                            </div>
                            <div class="grid grid-cols-3 gap-2 text-xs text-gray-600 dark:text-gray-400">
                                <div>
                                    <div class="text-gray-500">Turnover</div>
                                    <div class="font-bold text-orange-600 dark:text-orange-400">{{ $product['turnover_rate'] }}x</div>
                                </div>
                                <div>
                                    <div class="text-gray-500">Days to Sell</div>
                                    <div class="font-medium">{{ $product['days_to_sell'] }} days</div>
                                </div>
                                <div>
                                    <div class="text-gray-500">Stock</div>
                                    <div class="font-medium">{{ $product['current_stock'] }} units</div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <!-- All Products Table -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold mb-4">All Products Turnover Details</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="px-3 py-2 text-left text-xs font-semibold uppercase">Product</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold uppercase">Category</th>
                            <th class="px-3 py-2 text-center text-xs font-semibold uppercase">Class</th>
                            <th class="px-3 py-2 text-right text-xs font-semibold uppercase">Sold</th>
                            <th class="px-3 py-2 text-right text-xs font-semibold uppercase">Revenue</th>
                            <th class="px-3 py-2 text-right text-xs font-semibold uppercase">Stock</th>
                            <th class="px-3 py-2 text-right text-xs font-semibold uppercase">Turnover</th>
                            <th class="px-3 py-2 text-right text-xs font-semibold uppercase">Days</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($this->getProductTurnover() as $product)
                            <tr class="hover:bg-gray-200/30 dark:hover:bg-gray-700/50 transition-colors">
                                <td class="px-3 py-2">
                                    <div class="font-medium">{{ $product['product_name'] }}</div>
                                    <div class="text-xs text-gray-500">{{ $product['variant_name'] }}</div>
                                </td>
                                <td class="px-3 py-2 text-gray-600 dark:text-gray-400">{{ $product['category'] }}</td>
                                <td class="px-3 py-2 text-center">
                                    <span class="inline-flex px-2 py-1 rounded text-xs font-medium
                                        @if($product['abc_class'] === 'A') bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200
                                        @elseif($product['abc_class'] === 'B') bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200
                                        @else bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200
                                        @endif">
                                        {{ $product['abc_class'] }}
                                    </span>
                                </td>
                                <td class="px-3 py-2 text-right">{{ number_format($product['total_sold']) }}</td>
                                <td class="px-3 py-2 text-right text-blue-600 dark:text-blue-400">₹{{ number_format($product['revenue'], 0) }}</td>
                                <td class="px-3 py-2 text-right">{{ $product['current_stock'] }}</td>
                                <td class="px-3 py-2 text-right font-semibold">{{ $product['turnover_rate'] }}x</td>
                                <td class="px-3 py-2 text-right">{{ $product['days_to_sell'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-filament-panels::page>
