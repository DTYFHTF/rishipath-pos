<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Filters -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-3">
            <h3 class="text-lg font-semibold mb-3">Filter Options</h3>
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
                        @foreach(\App\Services\StoreContext::getAccessibleStores() as $store)
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

        <!-- Customer Metrics Cards -->
        @php
            $metrics = $this->getCustomerMetrics();
        @endphp
        
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-lg shadow p-6 text-gray-900 dark:text-white">
                <div class="text-sm opacity-90">Total Customers</div>
                <div class="text-3xl font-bold mt-2">{{ number_format($metrics['total_customers']) }}</div>
                <div class="text-xs opacity-75 mt-1">{{ $metrics['new_customers'] }} new this period</div>
            </div>
            
            <div class="bg-gradient-to-br from-green-500 to-green-600 rounded-lg shadow p-6 text-gray-900 dark:text-white">
                <div class="text-sm opacity-90">Active Customers</div>
                <div class="text-3xl font-bold mt-2">{{ number_format($metrics['active_customers']) }}</div>
                <div class="text-xs opacity-75 mt-1">{{ number_format(($metrics['active_customers'] / max($metrics['total_customers'], 1)) * 100, 1) }}% of total</div>
            </div>
            
            <div class="bg-gradient-to-br from-purple-500 to-purple-600 rounded-lg shadow p-6 text-gray-900 dark:text-white">
                <div class="text-sm opacity-90">Avg Transaction Value</div>
                <div class="text-3xl font-bold mt-2">₹{{ number_format($metrics['avg_transaction_value'], 0) }}</div>
                <div class="text-xs opacity-75 mt-1">{{ number_format($metrics['total_transactions']) }} transactions</div>
            </div>
            
            <div class="bg-gradient-to-br from-amber-500 to-amber-600 rounded-lg shadow p-6 text-gray-900 dark:text-white">
                <div class="text-sm opacity-90">Avg Lifetime Value</div>
                <div class="text-3xl font-bold mt-2">₹{{ number_format($metrics['avg_lifetime_value'], 0) }}</div>
                <div class="text-xs opacity-75 mt-1">Per active customer</div>
            </div>
        </div>

        <!-- Customer Segments -->
        @php
            $segments = $this->getSegmentDistribution();
        @endphp
        
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold mb-4">Customer Segments (RFM Analysis)</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach($segments as $segment => $data)
                    <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4 hover:bg-gray-200/30 dark:hover:bg-gray-700/50 transition-colors">
                        <div class="flex items-center justify-between mb-2">
                            <div class="font-semibold">{{ $segment }}</div>
                            <span class="text-2xl">
                                @if($segment === 'Champions') 🏆
                                @elseif($segment === 'Loyal Customers') 💎
                                @elseif($segment === 'Potential Loyalists') 🌟
                                @elseif($segment === 'New Customers') 🆕
                                @elseif($segment === 'Promising') ⭐
                                @elseif($segment === 'At Risk') ⚠️
                                @elseif($segment === 'Hibernating') 😴
                                @elseif($segment === 'Cannot Lose Them') 🚨
                                @else ❌
                                @endif
                            </span>
                        </div>
                        <div class="space-y-1 text-sm">
                            <div class="flex justify-between">
                                <span class="text-gray-600 dark:text-gray-400">Customers:</span>
                                <span class="font-semibold">{{ $data['count'] }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600 dark:text-gray-400">Total Revenue:</span>
                                <span class="font-semibold text-green-600 dark:text-green-400">₹{{ number_format($data['revenue'], 0) }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600 dark:text-gray-400">Avg Revenue:</span>
                                <span class="font-semibold">₹{{ number_format($data['avg_revenue'], 0) }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600 dark:text-gray-400">Avg Frequency:</span>
                                <span class="font-semibold">{{ $data['avg_frequency'] }} purchases</span>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <!-- Top Customers -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold mb-4">🏆 Top 10 Customers by Spending</h3>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase">Rank</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase">Customer</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase">Phone</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold uppercase">Segment</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase">Purchases</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase">Last Visit</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase">Total Spent</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold uppercase">RFM Score</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($this->getTopCustomers(10) as $index => $customer)
                            <tr class="hover:bg-gray-200/30 dark:hover:bg-gray-700/50 transition-colors">
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center justify-center w-8 h-8 rounded-full 
                                        @if($index === 0) bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200
                                        @elseif($index === 1) bg-gray-200 text-gray-800 dark:bg-gray-700 dark:text-gray-200
                                        @elseif($index === 2) bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200
                                        @else bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400
                                        @endif font-bold">
                                        {{ $index + 1 }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 font-medium">{{ $customer['customer_name'] }}</td>
                                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ $customer['customer_phone'] }}</td>
                                <td class="px-4 py-3 text-center">
                                    <span class="inline-flex px-2 py-1 rounded-full text-xs font-medium
                                        @if(in_array($customer['segment'], ['Champions', 'Loyal Customers'])) bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200
                                        @elseif(in_array($customer['segment'], ['Potential Loyalists', 'Promising', 'New Customers'])) bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200
                                        @elseif(in_array($customer['segment'], ['At Risk', 'Cannot Lose Them'])) bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200
                                        @else bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200
                                        @endif">
                                        {{ $customer['segment'] }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right">{{ $customer['frequency'] }}</td>
                                <td class="px-4 py-3 text-right">{{ $customer['recency_days'] }} days ago</td>
                                <td class="px-4 py-3 text-right text-green-600 dark:text-green-400 font-bold">₹{{ number_format($customer['monetary'], 2) }}</td>
                                <td class="px-4 py-3 text-center">
                                    <span class="inline-flex items-center justify-center w-10 h-10 rounded-full bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200 font-bold">
                                        {{ $customer['rfm_score'] }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Purchase Frequency Distribution -->
        @php
            $frequency = $this->getPurchaseFrequency();
        @endphp
        
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold mb-4">Purchase Frequency Distribution</h3>
            <div class="space-y-3">
                @foreach($frequency as $range => $count)
                    @php
                        $total = array_sum($frequency);
                        $percentage = $total > 0 ? ($count / $total) * 100 : 0;
                    @endphp
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <span class="text-sm font-medium">{{ $range }}</span>
                            <span class="text-sm text-gray-600 dark:text-gray-400">{{ $count }} customers ({{ number_format($percentage, 1) }}%)</span>
                        </div>
                        <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-3">
                            <div class="bg-gradient-to-r from-blue-500 to-blue-600 h-3 rounded-full transition-all duration-300" style="width: {{ $percentage }}%"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <!-- All Customers RFM Table -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold mb-4">All Customers - RFM Analysis</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="px-3 py-2 text-left text-xs font-semibold uppercase">Customer</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold uppercase">Phone</th>
                            <th class="px-3 py-2 text-right text-xs font-semibold uppercase">Recency</th>
                            <th class="px-3 py-2 text-right text-xs font-semibold uppercase">Frequency</th>
                            <th class="px-3 py-2 text-right text-xs font-semibold uppercase">Monetary</th>
                            <th class="px-3 py-2 text-center text-xs font-semibold uppercase">R</th>
                            <th class="px-3 py-2 text-center text-xs font-semibold uppercase">F</th>
                            <th class="px-3 py-2 text-center text-xs font-semibold uppercase">M</th>
                            <th class="px-3 py-2 text-center text-xs font-semibold uppercase">Score</th>
                            <th class="px-3 py-2 text-center text-xs font-semibold uppercase">Segment</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($this->getRfmAnalysis() as $customer)
                            <tr class="hover:bg-gray-200/30 dark:hover:bg-gray-700/50 transition-colors">
                                <td class="px-3 py-2 font-medium">{{ $customer['customer_name'] }}</td>
                                <td class="px-3 py-2 text-gray-600 dark:text-gray-400">{{ $customer['customer_phone'] }}</td>
                                <td class="px-3 py-2 text-right">{{ $customer['recency_days'] }} days</td>
                                <td class="px-3 py-2 text-right">{{ $customer['frequency'] }}</td>
                                <td class="px-3 py-2 text-right text-green-600 dark:text-green-400">₹{{ number_format($customer['monetary'], 0) }}</td>
                                <td class="px-3 py-2 text-center"><span class="font-mono font-semibold">{{ $customer['recency_score'] }}</span></td>
                                <td class="px-3 py-2 text-center"><span class="font-mono font-semibold">{{ $customer['frequency_score'] }}</span></td>
                                <td class="px-3 py-2 text-center"><span class="font-mono font-semibold">{{ $customer['monetary_score'] }}</span></td>
                                <td class="px-3 py-2 text-center">
                                    <span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200 font-bold text-xs">
                                        {{ $customer['rfm_score'] }}
                                    </span>
                                </td>
                                <td class="px-3 py-2 text-center">
                                    <span class="inline-flex px-2 py-1 rounded text-xs font-medium
                                        @if(in_array($customer['segment'], ['Champions', 'Loyal Customers'])) bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200
                                        @elseif(in_array($customer['segment'], ['Potential Loyalists', 'Promising', 'New Customers'])) bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200
                                        @elseif(in_array($customer['segment'], ['At Risk', 'Cannot Lose Them'])) bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200
                                        @else bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200
                                        @endif">
                                        {{ $customer['segment'] }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-filament-panels::page>
