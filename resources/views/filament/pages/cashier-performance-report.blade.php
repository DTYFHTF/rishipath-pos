<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Filters -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-3">
            <h3 class="text-lg font-semibold mb-3">Filter Options</h3>
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
                    <label class="block text-sm font-medium mb-1">Cashier</label>
                    <select 
                        wire:model.live="cashierId"
                        class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900"
                    >
                        <option value="">All Cashiers</option>
                        @foreach(\App\Models\User::whereHas('role', fn($q) => $q->whereIn('slug', ['cashier', 'store-manager', 'super-admin']))->get() as $user)
                            <option value="{{ $user->id }}">{{ $user->name }}</option>
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

        <!-- Performance Metrics Cards -->
        @php
            $metrics = $this->getPerformanceMetrics();
        @endphp
        
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-lg shadow p-6 text-gray-900 dark:text-white">
                <div class="text-sm opacity-90">Total Sales</div>
                <div class="text-3xl font-bold mt-2">{{ number_format($metrics['total_sales']) }}</div>
                <div class="text-xs opacity-75 mt-1">{{ $metrics['active_cashiers'] }} active cashiers</div>
            </div>
            
            <div class="bg-gradient-to-br from-green-500 to-green-600 rounded-lg shadow p-6 text-gray-900 dark:text-white">
                <div class="text-sm opacity-90">Total Revenue</div>
                <div class="text-3xl font-bold mt-2">₹{{ number_format($metrics['total_revenue'], 0) }}</div>
                <div class="text-xs opacity-75 mt-1">Avg: ₹{{ number_format($metrics['avg_sale_value'], 0) }}/sale</div>
            </div>
            
            <div class="bg-gradient-to-br from-purple-500 to-purple-600 rounded-lg shadow p-6 text-gray-900 dark:text-white">
                <div class="text-sm opacity-90">Items Sold</div>
                <div class="text-3xl font-bold mt-2">{{ number_format($metrics['total_items']) }}</div>
                <div class="text-xs opacity-75 mt-1">Avg: {{ number_format($metrics['avg_items_per_sale'], 1) }} items/sale</div>
            </div>
            
            <div class="bg-gradient-to-br from-orange-500 to-orange-600 rounded-lg shadow p-6 text-gray-900 dark:text-white">
                <div class="text-sm opacity-90">Sales Per Hour</div>
                <div class="text-3xl font-bold mt-2">{{ number_format($metrics['avg_sales_per_hour'], 1) }}</div>
                <div class="text-xs opacity-75 mt-1">Average across all cashiers</div>
            </div>
        </div>

        <!-- Top Performers -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold mb-4">🏆 Top 5 Performers (By Efficiency Score)</h3>
            <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
                @foreach($this->getTopCashiers(5) as $index => $cashier)
                    <div class="border-2 border-gray-200 dark:border-gray-700 rounded-lg p-4 text-center hover:border-blue-400 dark:hover:border-blue-600 transition
                        @if($index === 0) bg-gradient-to-b from-yellow-50 to-white dark:from-yellow-900/20 dark:to-gray-800 border-yellow-300 dark:border-yellow-700 @endif">
                        <div class="text-4xl mb-2">
                            @if($index === 0) 🥇
                            @elseif($index === 1) 🥈
                            @elseif($index === 2) 🥉
                            @else ⭐
                            @endif
                        </div>
                        <div class="font-semibold text-sm mb-1">{{ $cashier['cashier_name'] }}</div>
                        <div class="text-2xl font-bold text-blue-600 dark:text-blue-400 mb-2">{{ $cashier['efficiency_score'] }}%</div>
                        <div class="text-xs text-gray-600 dark:text-gray-400 space-y-1">
                            <div>{{ $cashier['total_sales'] }} sales</div>
                            <div>₹{{ number_format($cashier['total_revenue'], 0) }}</div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <!-- All Cashiers Performance Table -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold mb-4">All Cashiers Performance</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="px-3 py-2 text-left text-xs font-semibold uppercase">Cashier</th>
                            <th class="px-3 py-2 text-right text-xs font-semibold uppercase">Sales</th>
                            <th class="px-3 py-2 text-right text-xs font-semibold uppercase">Revenue</th>
                            <th class="px-3 py-2 text-right text-xs font-semibold uppercase">Avg Sale</th>
                            <th class="px-3 py-2 text-right text-xs font-semibold uppercase">Items</th>
                            <th class="px-3 py-2 text-right text-xs font-semibold uppercase">Items/Sale</th>
                            <th class="px-3 py-2 text-right text-xs font-semibold uppercase">Hours</th>
                            <th class="px-3 py-2 text-right text-xs font-semibold uppercase">Sales/Hr</th>
                            <th class="px-3 py-2 text-right text-xs font-semibold uppercase">Revenue/Hr</th>
                            <th class="px-3 py-2 text-center text-xs font-semibold uppercase">Efficiency</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($this->getCashierPerformance() as $cashier)
                            <tr class="hover:bg-gray-200/30 dark:hover:bg-gray-700/50 transition-colors">
                                <td class="px-3 py-2 font-medium">{{ $cashier['cashier_name'] }}</td>
                                <td class="px-3 py-2 text-right">{{ number_format($cashier['total_sales']) }}</td>
                                <td class="px-3 py-2 text-right text-green-600 dark:text-green-400 font-semibold">₹{{ number_format($cashier['total_revenue'], 0) }}</td>
                                <td class="px-3 py-2 text-right">₹{{ number_format($cashier['avg_sale_value'], 0) }}</td>
                                <td class="px-3 py-2 text-right">{{ number_format($cashier['total_items']) }}</td>
                                <td class="px-3 py-2 text-right">{{ $cashier['avg_items_per_sale'] }}</td>
                                <td class="px-3 py-2 text-right">{{ $cashier['working_hours'] }}</td>
                                <td class="px-3 py-2 text-right">{{ $cashier['sales_per_hour'] }}</td>
                                <td class="px-3 py-2 text-right">₹{{ number_format($cashier['revenue_per_hour'], 0) }}</td>
                                <td class="px-3 py-2 text-center">
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold
                                        @if($cashier['efficiency_score'] >= 90) bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200
                                        @elseif($cashier['efficiency_score'] >= 70) bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200
                                        @elseif($cashier['efficiency_score'] >= 50) bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200
                                        @else bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200
                                        @endif">
                                        {{ $cashier['efficiency_score'] }}%
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-4 p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
                <p class="text-sm text-blue-800 dark:text-blue-200">
                    <strong>Efficiency Score Calculation:</strong> Sales per hour (40%) + Average sale value (30%) + Items per sale (30%)
                </p>
            </div>
        </div>

        <!-- Hourly Performance (Only shown when cashier is selected) -->
        @if($this->cashierId)
            @php
                $hourlyData = $this->getHourlyPerformance();
            @endphp
            
            @if(!empty($hourlyData))
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                    <h3 class="text-lg font-semibold mb-4">⏰ Hourly Performance Breakdown</h3>
                    <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-3">
                        @foreach($hourlyData as $hour)
                            <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-3 text-center hover:bg-gray-200/30 dark:hover:bg-gray-700/50 transition-colors">
                                <div class="text-lg font-bold text-blue-600 dark:text-blue-400">{{ $hour['hour'] }}</div>
                                <div class="text-2xl font-bold mt-1">{{ $hour['total_sales'] }}</div>
                                <div class="text-xs text-gray-600 dark:text-gray-400 mt-1">sales</div>
                                <div class="text-sm font-semibold text-green-600 dark:text-green-400 mt-1">₹{{ number_format($hour['total_revenue'], 0) }}</div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <!-- Payment Method Distribution -->
                @php
                    $paymentMethods = $this->getPaymentMethodDistribution();
                @endphp
                
                @if(!empty($paymentMethods))
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                        <h3 class="text-lg font-semibold mb-4">💳 Payment Method Distribution</h3>
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                            @foreach($paymentMethods as $method => $data)
                                <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                                    <div class="text-sm text-gray-600 dark:text-gray-400 mb-1">{{ ucfirst($method) }}</div>
                                    <div class="text-2xl font-bold text-blue-600 dark:text-blue-400">{{ $data['count'] }}</div>
                                    <div class="text-sm text-green-600 dark:text-green-400 font-semibold mt-1">₹{{ number_format($data['total'], 0) }}</div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            @endif
        @endif

        <!-- Daily Performance Trend -->
        @php
            $dailyData = $this->getDailyPerformance();
        @endphp
        
        @if(!empty($dailyData))
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <h3 class="text-lg font-semibold mb-4">Daily Performance Trend</h3>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-3 py-2 text-left text-xs font-semibold uppercase">Date</th>
                                <th class="px-3 py-2 text-left text-xs font-semibold uppercase">Cashiers</th>
                                <th class="px-3 py-2 text-right text-xs font-semibold uppercase">Total Sales</th>
                                <th class="px-3 py-2 text-right text-xs font-semibold uppercase">Total Revenue</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach($dailyData as $day)
                                <tr class="hover:bg-gray-200/30 dark:hover:bg-gray-700/50 transition-colors">
                                    <td class="px-3 py-2 font-medium">{{ \Carbon\Carbon::parse($day['date'])->format('M d, Y') }}</td>
                                    <td class="px-3 py-2">
                                        <div class="space-y-1">
                                            @foreach($day['cashiers'] as $cashier)
                                                <div class="text-xs">
                                                    <span class="font-medium">{{ $cashier['name'] }}:</span>
                                                    <span class="text-gray-600 dark:text-gray-400">{{ $cashier['sales'] }} sales, ₹{{ number_format($cashier['revenue'], 0) }}</span>
                                                </div>
                                            @endforeach
                                        </div>
                                    </td>
                                    <td class="px-3 py-2 text-right font-semibold">{{ $day['total_sales'] }}</td>
                                    <td class="px-3 py-2 text-right text-green-600 dark:text-green-400 font-semibold">₹{{ number_format($day['total_revenue'], 0) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
