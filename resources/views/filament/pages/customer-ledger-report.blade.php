<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Filters Form --}}
        <x-filament::card>
            <form wire:submit.prevent="generateReport">
                {{ $this->form }}
            </form>
        </x-filament::card>

        @if($customerData)
            {{-- Customer Info --}}
            <x-filament::card>
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Customer Name</p>
                        <p class="font-semibold text-gray-900 dark:text-gray-100">{{ $customerData['name'] }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Customer Code</p>
                        <p class="font-semibold text-gray-900 dark:text-gray-100">{{ $customerData['customer_code'] }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Phone</p>
                        <p class="font-semibold text-gray-900 dark:text-gray-100">{{ $customerData['phone'] }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Email</p>
                        <p class="font-semibold text-gray-900 dark:text-gray-100">{{ $customerData['email'] ?? 'N/A' }}</p>
                    </div>
                </div>
            </x-filament::card>

            {{-- Summary Cards --}}
            <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
                <x-filament::card>
                    <div class="text-center">
                        <p class="text-sm text-gray-500 dark:text-gray-400">Total Debit</p>
                        <p class="text-2xl font-bold text-red-600 dark:text-red-400">₹{{ number_format($summary['total_debit'], 2) }}</p>
                    </div>
                </x-filament::card>

                <x-filament::card>
                    <div class="text-center">
                        <p class="text-sm text-gray-500 dark:text-gray-400">Total Credit</p>
                        <p class="text-2xl font-bold text-green-600 dark:text-green-400">₹{{ number_format($summary['total_credit'], 2) }}</p>
                    </div>
                </x-filament::card>

                <x-filament::card>
                    <div class="text-center">
                        <p class="text-sm text-gray-500 dark:text-gray-400">Net Amount</p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">₹{{ number_format($summary['net_amount'], 2) }}</p>
                    </div>
                </x-filament::card>

                <x-filament::card>
                    <div class="text-center">
                        <p class="text-sm text-gray-500 dark:text-gray-400">Current Balance</p>
                        <p class="text-2xl font-bold {{ $summary['current_balance'] > 0 ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400' }}">
                            ₹{{ number_format($summary['current_balance'], 2) }}
                        </p>
                    </div>
                </x-filament::card>

                <x-filament::card>
                    <div class="text-center">
                        <p class="text-sm text-gray-500 dark:text-gray-400">Outstanding</p>
                        <p class="text-2xl font-bold text-orange-600 dark:text-orange-400">₹{{ number_format($summary['outstanding'], 2) }}</p>
                    </div>
                </x-filament::card>
            </div>

            {{-- Ledger Table --}}
            <x-filament::card>
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Ledger Entries</h3>
                    <div class="flex gap-2">
                        <x-filament::button wire:click="downloadExcel" color="success" size="sm">
                            <x-heroicon-o-arrow-down-tray class="w-4 h-4 mr-1" />
                            Excel
                        </x-filament::button>
                        <x-filament::button wire:click="downloadPdf" color="danger" size="sm">
                            <x-heroicon-o-arrow-down-tray class="w-4 h-4 mr-1" />
                            PDF
                        </x-filament::button>
                    </div>
                </div>

                @if(count($ledgerEntries) > 0)
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50 dark:bg-gray-800">
                                <tr>
                                    <th class="px-4 py-3 text-left text-gray-700 dark:text-gray-300">Date</th>
                                    <th class="px-4 py-3 text-left text-gray-700 dark:text-gray-300">Reference</th>
                                    <th class="px-4 py-3 text-left text-gray-700 dark:text-gray-300">Description</th>
                                    <th class="px-4 py-3 text-left text-gray-700 dark:text-gray-300">Type</th>
                                    <th class="px-4 py-3 text-right text-gray-700 dark:text-gray-300">Debit (₹)</th>
                                    <th class="px-4 py-3 text-right text-gray-700 dark:text-gray-300">Credit (₹)</th>
                                    <th class="px-4 py-3 text-right text-gray-700 dark:text-gray-300">Balance (₹)</th>
                                    <th class="px-4 py-3 text-center text-gray-700 dark:text-gray-300">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                @foreach($ledgerEntries as $entry)
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800">
                                        <td class="px-4 py-3 text-gray-900 dark:text-gray-100">{{ $entry['date'] }}</td>
                                        <td class="px-4 py-3 text-gray-900 dark:text-gray-100">{{ $entry['reference'] ?? '-' }}</td>
                                        <td class="px-4 py-3 text-gray-900 dark:text-gray-100">
                                            {{ $entry['description'] }}
                                            @if($entry['store'])
                                                <span class="text-xs text-gray-500 dark:text-gray-400">({{ $entry['store'] }})</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3">
                                            <span class="px-2 py-1 text-xs rounded-full
                                                {{ $entry['type'] === 'sale' ? 'bg-blue-100 dark:bg-blue-900/30 text-blue-800 dark:text-blue-400' : '' }}
                                                {{ $entry['type'] === 'payment' ? 'bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-400' : '' }}
                                                {{ $entry['type'] === 'credit_note' ? 'bg-purple-100 dark:bg-purple-900/30 text-purple-800 dark:text-purple-400' : '' }}">
                                                {{ ucwords(str_replace('_', ' ', $entry['type'])) }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-right {{ $entry['debit'] > 0 ? 'text-red-600 dark:text-red-400 font-semibold' : 'text-gray-500 dark:text-gray-400' }}">
                                            {{ $entry['debit'] > 0 ? number_format($entry['debit'], 2) : '-' }}
                                        </td>
                                        <td class="px-4 py-3 text-right {{ $entry['credit'] > 0 ? 'text-green-600 dark:text-green-400 font-semibold' : 'text-gray-500 dark:text-gray-400' }}">
                                            {{ $entry['credit'] > 0 ? number_format($entry['credit'], 2) : '-' }}
                                        </td>
                                        <td class="px-4 py-3 text-right font-semibold {{ $entry['balance'] > 0 ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400' }}">
                                            {{ number_format($entry['balance'], 2) }}
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            <span class="px-2 py-1 text-xs rounded-full
                                                {{ $entry['status'] === 'completed' ? 'bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-400' : '' }}
                                                {{ $entry['status'] === 'pending' ? 'bg-yellow-100 dark:bg-yellow-900/30 text-yellow-800 dark:text-yellow-400' : '' }}
                                                {{ $entry['status'] === 'overdue' ? 'bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-400' : '' }}">
                                                {{ ucfirst($entry['status']) }}
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="bg-gray-50 dark:bg-gray-800 font-bold">
                                <tr>
                                    <td colspan="4" class="px-4 py-3 text-right text-gray-900 dark:text-gray-100">Total:</td>
                                    <td class="px-4 py-3 text-right text-red-600 dark:text-red-400">{{ number_format($summary['total_debit'], 2) }}</td>
                                    <td class="px-4 py-3 text-right text-green-600 dark:text-green-400">{{ number_format($summary['total_credit'], 2) }}</td>
                                    <td class="px-4 py-3 text-right {{ $summary['current_balance'] > 0 ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400' }}">
                                        {{ number_format($summary['current_balance'], 2) }}
                                    </td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                @else
                    <div class="text-center py-8 text-gray-500 dark:text-gray-400">
                        No ledger entries found for the selected period.
                    </div>
                @endif
            </x-filament::card>
        @endif
    </div>
</x-filament-panels::page>
