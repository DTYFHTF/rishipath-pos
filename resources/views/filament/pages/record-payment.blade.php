<x-filament-panels::page>
    <div class="space-y-6">
        <x-filament::card>
            <form wire:submit.prevent="recordPayment">
                {{ $this->form }}

                @if($outstandingBalance !== null)
                    <div class="mt-6 p-4 bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg">
                        <div class="flex items-center gap-2 mb-2">
                            <x-heroicon-o-exclamation-triangle class="w-5 h-5 text-yellow-600 dark:text-yellow-400" />
                            <h4 class="font-semibold text-yellow-900 dark:text-yellow-100">Customer Balance Information</h4>
                        </div>
                        <div class="grid grid-cols-2 gap-4 text-sm">
                            <div>
                                <p class="text-yellow-700 dark:text-yellow-300">Outstanding (Pending):</p>
                                <p class="text-xl font-bold text-yellow-900 dark:text-yellow-100">₹{{ number_format($outstandingBalance, 2) }}</p>
                            </div>
                            <div>
                                <p class="text-yellow-700 dark:text-yellow-300">Current Balance:</p>
                                <p class="text-xl font-bold {{ $currentBalance > 0 ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400' }}">
                                    ₹{{ number_format($currentBalance, 2) }}
                                </p>
                            </div>
                        </div>
                    </div>
                @endif

                <div class="mt-6 flex justify-end gap-3">
                    <x-filament::button
                        type="button"
                        color="gray"
                        wire:click="$dispatch('open-modal', { id: 'view-ledger' })"
                    >
                        View Ledger
                    </x-filament::button>

                    <x-filament::button
                        type="submit"
                        color="success"
                        icon="heroicon-o-banknotes"
                    >
                        Record Payment
                    </x-filament::button>
                </div>
            </form>
        </x-filament::card>

        {{-- Recent Payments --}}
        <x-filament::card>
            <h3 class="text-lg font-semibold mb-4 text-gray-900 dark:text-gray-100">Recent Payments (Today)</h3>
            
            @php
                $recentPayments = \App\Models\CustomerLedgerEntry::where('entry_type', 'payment')
                    ->whereDate('transaction_date', today())
                    ->with(['customer', 'createdBy'])
                    ->orderBy('created_at', 'desc')
                    ->limit(10)
                    ->get();
            @endphp

            @if($recentPayments->count() > 0)
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-800">
                            <tr>
                                <th class="px-4 py-3 text-left text-gray-700 dark:text-gray-300">Time</th>
                                <th class="px-4 py-3 text-left text-gray-700 dark:text-gray-300">Customer</th>
                                <th class="px-4 py-3 text-left text-gray-700 dark:text-gray-300">Amount</th>
                                <th class="px-4 py-3 text-left text-gray-700 dark:text-gray-300">Method</th>
                                <th class="px-4 py-3 text-left text-gray-700 dark:text-gray-300">Reference</th>
                                <th class="px-4 py-3 text-left text-gray-700 dark:text-gray-300">Recorded By</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach($recentPayments as $payment)
                                <tr class="hover:bg-gray-200/30 dark:hover:bg-gray-700/50 transition-colors">
                                    <td class="px-4 py-3 text-gray-900 dark:text-gray-100">
                                        {{ $payment->created_at->format('h:i A') }}
                                    </td>
                                    <td class="px-4 py-3 text-gray-900 dark:text-gray-100">
                                        {{ $payment->customer->name }}
                                    </td>
                                    <td class="px-4 py-3 text-green-600 dark:text-green-400 font-semibold">
                                        ₹{{ number_format($payment->credit_amount, 2) }}
                                    </td>
                                    <td class="px-4 py-3 text-gray-900 dark:text-gray-100">
                                        {{ $payment->payment_method ? ucfirst(str_replace('_', ' ', $payment->payment_method)) : '-' }}
                                    </td>
                                    <td class="px-4 py-3 text-gray-600 dark:text-gray-400">
                                        {{ $payment->payment_reference ?? '-' }}
                                    </td>
                                    <td class="px-4 py-3 text-gray-600 dark:text-gray-400">
                                        {{ $payment->createdBy?->name ?? '-' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="text-center py-8 text-gray-500 dark:text-gray-400">No payments recorded today</p>
            @endif
        </x-filament::card>
    </div>
</x-filament-panels::page>
