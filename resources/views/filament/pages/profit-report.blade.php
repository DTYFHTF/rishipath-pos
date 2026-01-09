<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Filters -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold mb-4">Filter Options</h3>
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
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

        <!-- Profit Summary Cards -->
        @php
            $summary = $this->getProfitSummary();
        @endphp
        
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-lg shadow p-6 text-white">
                <div class="text-sm opacity-90">Total Revenue</div>
                <div class="text-3xl font-bold mt-2">₹{{ number_format($summary['total_revenue'], 2) }}</div>
                <div class="text-xs opacity-75 mt-1">{{ $summary['transaction_count'] }} transactions</div>
            </div>
            
            <div class="bg-gradient-to-br from-red-500 to-red-600 rounded-lg shadow p-6 text-white">
                <div class="text-sm opacity-90">Total Cost</div>
                <div class="text-3xl font-bold mt-2">₹{{ number_format($summary['total_cost'], 2) }}</div>
                <div class="text-xs opacity-75 mt-1">COGS (Cost of Goods Sold)</div>
            </div>
            
            <div class="bg-gradient-to-br from-green-500 to-green-600 rounded-lg shadow p-6 text-white">
                <div class="text-sm opacity-90">Total Profit</div>
                <div class="text-3xl font-bold mt-2">₹{{ number_format($summary['total_profit'], 2) }}</div>
                <div class="text-xs opacity-75 mt-1">After tax & costs</div>
            </div>
            
            <div class="bg-gradient-to-br from-purple-500 to-purple-600 rounded-lg shadow p-6 text-white">
                <div class="text-sm opacity-90">Profit Margin</div>
                <div class="text-3xl font-bold mt-2">{{ number_format($summary['profit_margin'], 2) }}%</div>
                <div class="text-xs opacity-75 mt-1">Avg: ₹{{ number_format($summary['average_profit_per_sale'], 2) }}/sale</div>
            </div>
        </div>

        <!-- Profit by Category -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold mb-4">Profit by Category</h3>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase">Category</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase">Qty Sold</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase">Revenue</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase">Cost</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase">Profit</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase">Margin %</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($this->getProfitByCategory() as $category => $data)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                <td class="px-4 py-3 font-medium">{{ $category }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($data['quantity_sold']) }}</td>
                                <td class="px-4 py-3 text-right text-blue-600 dark:text-blue-400">₹{{ number_format($data['revenue'], 2) }}</td>
                                <td class="px-4 py-3 text-right text-red-600 dark:text-red-400">₹{{ number_format($data['cost'], 2) }}</td>
                                <td class="px-4 py-3 text-right text-green-600 dark:text-green-400 font-semibold">₹{{ number_format($data['profit'], 2) }}</td>
                                <td class="px-4 py-3 text-right">
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium
                                        @if($data['profit_margin'] >= 30) bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200
                                        @elseif($data['profit_margin'] >= 15) bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200
                                        @else bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200
                                        @endif">
                                        {{ number_format($data['profit_margin'], 2) }}%
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Top & Least Profitable Products Side by Side -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Top Profitable Products -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <h3 class="text-lg font-semibold mb-4 text-green-600 dark:text-green-400">🏆 Top 10 Profitable Products</h3>
                <div class="space-y-3">
                    @foreach($this->getTopProfitableProducts(10) as $product)
                        <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4 hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                            <div class="flex items-center justify-between mb-2">
                                <div class="font-semibold text-sm text-gray-900 dark:text-gray-100">{{ $product['name'] }}</div>
                                <span class="text-green-600 dark:text-green-400 font-bold">₹{{ number_format($product['profit'], 2) }}</span>
                            </div>
                            <div class="grid grid-cols-3 gap-2 text-xs text-gray-700 dark:text-gray-300">
                                <div>
                                    <div class="text-gray-500 dark:text-gray-400">Sold</div>
                                    <div class="font-medium">{{ $product['quantity_sold'] }} units</div>
                                </div>
                                <div>
                                    <div class="text-gray-500 dark:text-gray-400">Revenue</div>
                                    <div class="font-medium">₹{{ number_format($product['revenue'], 2) }}</div>
                                </div>
                                <div>
                                    <div class="text-gray-500 dark:text-gray-400">Margin</div>
                                    <div class="font-medium">{{ number_format($product['profit_margin'], 2) }}%</div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- Least Profitable Products -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <h3 class="text-lg font-semibold mb-4 text-red-600 dark:text-red-400">⚠️ Least Profitable Products</h3>
                <div class="space-y-3">
                    @foreach($this->getLeastProfitableProducts(10) as $product)
                        <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4 hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                            <div class="flex items-center justify-between mb-2">
                                <div class="font-semibold text-sm text-gray-900 dark:text-gray-100">{{ $product['name'] }}</div>
                                <span class="text-red-600 dark:text-red-400 font-bold">₹{{ number_format($product['profit'], 2) }}</span>
                            </div>
                            <div class="grid grid-cols-3 gap-2 text-xs text-gray-700 dark:text-gray-300">
                                <div>
                                    <div class="text-gray-500 dark:text-gray-400">Sold</div>
                                    <div class="font-medium">{{ $product['quantity_sold'] }} units</div>
                                </div>
                                <div>
                                    <div class="text-gray-500 dark:text-gray-400">Revenue</div>
                                    <div class="font-medium">₹{{ number_format($product['revenue'], 2) }}</div>
                                </div>
                                <div>
                                    <div class="text-gray-500 dark:text-gray-400">Margin</div>
                                    <div class="font-medium">{{ number_format($product['profit_margin'], 2) }}%</div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <!-- Daily Profit Trend -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold mb-4">Daily Profit Trend</h3>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase">Date</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase">Transactions</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase">Revenue</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase">Cost</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase">Profit</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase">Margin %</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($this->getDailyProfitTrend() as $day)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                <td class="px-4 py-3 font-medium">{{ \Carbon\Carbon::parse($day['date'])->format('M d, Y') }}</td>
                                <td class="px-4 py-3 text-right">{{ $day['transactions'] }}</td>
                                <td class="px-4 py-3 text-right text-blue-600 dark:text-blue-400">₹{{ number_format($day['revenue'], 2) }}</td>
                                <td class="px-4 py-3 text-right text-red-600 dark:text-red-400">₹{{ number_format($day['cost'], 2) }}</td>
                                <td class="px-4 py-3 text-right text-green-600 dark:text-green-400 font-semibold">₹{{ number_format($day['profit'], 2) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($day['profit_margin'], 2) }}%</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-filament-panels::page>
