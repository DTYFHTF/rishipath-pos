<x-filament-panels::page>
    <div class="space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
            <div>
                <label class="block text-xs text-gray-500 mb-1">Agent</label>
                <select wire:model.live="agentId" class="w-full rounded-lg border-gray-300 dark:border-gray-700">
                    <option value="">All Agents</option>
                    @foreach($this->agents as $agent)
                        <option value="{{ $agent->id }}">{{ $agent->name }} ({{ $agent->agent_code }})</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">From</label>
                <input type="date" wire:model.live="fromDate" class="w-full rounded-lg border-gray-300 dark:border-gray-700" />
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">To</label>
                <input type="date" wire:model.live="toDate" class="w-full rounded-lg border-gray-300 dark:border-gray-700" />
            </div>
            <div class="rounded-lg border border-blue-200 bg-blue-50 px-3 py-2 dark:border-blue-900 dark:bg-blue-900/20">
                <p class="text-xs text-blue-700 dark:text-blue-300">Unsettled Paid Sales</p>
                <p class="text-xl font-semibold text-blue-800 dark:text-blue-200">{{ $this->summary['unsettled_count'] }}</p>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
            <div class="rounded-lg border border-gray-200 bg-white px-4 py-3 dark:border-gray-700 dark:bg-gray-900">
                <p class="text-xs text-gray-500">Total Sales</p>
                <p class="text-xl font-semibold">NPR {{ number_format($this->summary['total_sales'], 2) }}</p>
            </div>
            <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 dark:border-green-900 dark:bg-green-900/20">
                <p class="text-xs text-green-700 dark:text-green-300">Paid Collections</p>
                <p class="text-xl font-semibold text-green-800 dark:text-green-200">NPR {{ number_format($this->summary['paid_collections'], 2) }}</p>
            </div>
            <div class="rounded-lg border border-purple-200 bg-purple-50 px-4 py-3 dark:border-purple-900 dark:bg-purple-900/20">
                <p class="text-xs text-purple-700 dark:text-purple-300">Agent Commission</p>
                <p class="text-xl font-semibold text-purple-800 dark:text-purple-200">NPR {{ number_format($this->summary['commissions'], 2) }}</p>
            </div>
            <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 dark:border-amber-900 dark:bg-amber-900/20">
                <p class="text-xs text-amber-700 dark:text-amber-300">Delivery Charges</p>
                <p class="text-xl font-semibold text-amber-800 dark:text-amber-200">NPR {{ number_format($this->summary['delivery_charges'], 2) }}</p>
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden dark:border-gray-700 dark:bg-gray-900">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-800">
                        <tr class="text-left text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            <th class="px-3 py-2">Date</th>
                            <th class="px-3 py-2">Receipt</th>
                            <th class="px-3 py-2">Agent</th>
                            <th class="px-3 py-2 text-right">Total</th>
                            <th class="px-3 py-2 text-right">Delivery</th>
                            <th class="px-3 py-2 text-right">Commission</th>
                            <th class="px-3 py-2">Payment</th>
                            <th class="px-3 py-2">Settlement</th>
                            <th class="px-3 py-2 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse($this->sales as $sale)
                            <tr>
                                <td class="px-3 py-2">{{ optional($sale->date)->format('Y-m-d') }}</td>
                                <td class="px-3 py-2 font-medium">{{ $sale->receipt_number }}</td>
                                <td class="px-3 py-2">{{ $sale->salesAgent?->name ?? '-' }}</td>
                                <td class="px-3 py-2 text-right">NPR {{ number_format((float) $sale->total_amount, 2) }}</td>
                                <td class="px-3 py-2 text-right">NPR {{ number_format((float) ($sale->delivery_charge ?? 0), 2) }}</td>
                                <td class="px-3 py-2 text-right">NPR {{ number_format((float) ($sale->agent_commission_amount ?? 0), 2) }}</td>
                                <td class="px-3 py-2">
                                    <span class="inline-flex rounded-full px-2 py-0.5 text-xs {{ $sale->payment_status === 'paid' ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700' }}">
                                        {{ strtoupper($sale->payment_status) }}
                                    </span>
                                    <div class="text-[11px] text-gray-500">{{ strtoupper($sale->payment_method ?? '-') }}</div>
                                </td>
                                <td class="px-3 py-2">
                                    @if($sale->settlement_confirmed_at)
                                        <span class="inline-flex rounded-full px-2 py-0.5 text-xs bg-green-100 text-green-700">Confirmed</span>
                                        <div class="text-[11px] text-gray-500">{{ $sale->settlement_confirmed_at->format('Y-m-d H:i') }}</div>
                                    @else
                                        <span class="inline-flex rounded-full px-2 py-0.5 text-xs bg-gray-100 text-gray-700">Pending</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-right">
                                    @if($sale->payment_status === 'paid' && !$sale->settlement_confirmed_at)
                                        <button wire:click="confirmSettlement({{ $sale->id }})" class="px-2 py-1 text-xs rounded bg-primary-600 text-white hover:bg-primary-700">
                                            Confirm
                                        </button>
                                    @else
                                        <span class="text-xs text-gray-400">-</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="px-3 py-6 text-center text-gray-500">No records found for selected filters.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-filament-panels::page>
