<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Filters -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-medium mb-1">Supplier</label>
                    <select 
                        wire:model.live="supplierId"
                        class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900"
                    >
                        <option value="">All Suppliers</option>
                        @foreach(\App\Models\Supplier::where('active', true)->orderBy('name')->get() as $supplier)
                            <option value="{{ $supplier->id }}">{{ $supplier->name }} ({{ $supplier->supplier_code }})</option>
                        @endforeach
                    </select>
                </div>
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
            </div>
        </div>

        <!-- Overall Metrics (Ultra Compact - Inline) -->
        @php $metrics = $this->getOverallMetrics(); @endphp
        <div class="flex flex-wrap gap-2">
            <div class="bg-white dark:bg-gray-800 rounded shadow-sm px-3 py-1.5 border-l-2 border-red-500 flex items-center gap-2">
                <span class="text-xs text-gray-500">Payable:</span>
                <span class="text-sm font-bold text-red-600">₹{{ number_format($metrics['total_payable'], 0) }}</span>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded shadow-sm px-3 py-1.5 border-l-2 border-orange-500 flex items-center gap-2">
                <span class="text-xs text-gray-500">With Balance:</span>
                <span class="text-base font-bold text-orange-600">{{ $metrics['suppliers_with_balance'] }}</span>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded shadow-sm px-3 py-1.5 border-l-2 border-blue-500 flex items-center gap-2">
                <span class="text-xs text-gray-500">Suppliers:</span>
                <span class="text-base font-bold">{{ $metrics['total_suppliers'] }}</span>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded shadow-sm px-3 py-1.5 border-l-2 border-purple-500 flex items-center gap-2">
                <span class="text-xs text-gray-500">Purchases:</span>
                <span class="text-sm font-bold text-purple-600">₹{{ number_format($metrics['period_purchases'], 0) }}</span>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded shadow-sm px-3 py-1.5 border-l-2 border-green-500 flex items-center gap-2">
                <span class="text-xs text-gray-500">Payments:</span>
                <span class="text-sm font-bold text-green-600">₹{{ number_format($metrics['period_payments'], 0) }}</span>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Supplier Balances -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <h3 class="text-lg font-semibold mb-4">📋 Supplier Balances</h3>
                <div class="space-y-2 max-h-96 overflow-y-auto">
                    @foreach($this->getSupplierSummary() as $supplier)
                        <div 
                            wire:click="$set('supplierId', {{ $supplier['id'] }})"
                            class="p-3 rounded-lg cursor-pointer transition-colors
                                {{ $supplierId == $supplier['id'] ? 'bg-primary-100 dark:bg-primary-900/30 border-2 border-primary-500' : 'hover:bg-gray-200/30 dark:hover:bg-gray-700/50 border border-gray-200 dark:border-gray-700' }}"
                        >
                            <div class="flex justify-between items-start">
                                <div>
                                    <div class="font-medium text-sm">{{ $supplier['name'] }}</div>
                                    <div class="text-xs text-gray-500">{{ $supplier['code'] }}</div>
                                </div>
                                <div class="text-right">
                                    <div class="font-bold {{ $supplier['current_balance'] > 0 ? 'text-red-600' : 'text-green-600' }}">
                                        ₹{{ number_format($supplier['current_balance'], 0) }}
                                    </div>
                                    <div class="text-xs text-gray-500">{{ $supplier['purchase_count'] }} purchases</div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- Ledger Entries -->
            <div class="lg:col-span-2 bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <h3 class="text-lg font-semibold mb-4">
                    📜 Ledger Entries
                    @if($selectedSupplier = $this->getSelectedSupplier())
                        <span class="text-sm font-normal text-gray-500">- {{ $selectedSupplier->name }}</span>
                    @endif
                </h3>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-3 py-2 text-left font-semibold">Date</th>
                                <th class="px-3 py-2 text-left font-semibold">Supplier</th>
                                <th class="px-3 py-2 text-center font-semibold">Type</th>
                                <th class="px-3 py-2 text-right font-semibold">Amount</th>
                                <th class="px-3 py-2 text-right font-semibold">Balance</th>
                                <th class="px-3 py-2 text-left font-semibold">Reference</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @forelse($this->getLedgerEntries() as $entry)
                                <tr class="hover:bg-gray-200/30 dark:hover:bg-gray-700/50 transition-colors">
                                    <td class="px-3 py-2 text-xs">
                                        {{ $entry->created_at->format('M d, Y H:i') }}
                                    </td>
                                    <td class="px-3 py-2">
                                        <div class="font-medium text-sm">{{ $entry->supplier->name ?? '-' }}</div>
                                    </td>
                                    <td class="px-3 py-2 text-center">
                                        <span class="px-2 py-0.5 text-xs rounded
                                            @if($entry->type === 'purchase') bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200
                                            @elseif($entry->type === 'payment') bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200
                                            @elseif($entry->type === 'return') bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200
                                            @else bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200
                                            @endif">
                                            {{ ucfirst($entry->type) }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-2 text-right font-semibold {{ $entry->amount > 0 ? 'text-red-600' : 'text-green-600' }}">
                                        {{ $entry->amount > 0 ? '+' : '' }}₹{{ number_format($entry->amount, 0) }}
                                    </td>
                                    <td class="px-3 py-2 text-right font-medium">
                                        ₹{{ number_format($entry->balance_after, 0) }}
                                    </td>
                                    <td class="px-3 py-2 text-xs text-gray-500">
                                        @if($entry->purchase)
                                            {{ $entry->purchase->purchase_number }}
                                        @endif
                                        @if($entry->reference_number)
                                            <br>Ref: {{ $entry->reference_number }}
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-3 py-8 text-center text-gray-500">
                                        No ledger entries found
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
