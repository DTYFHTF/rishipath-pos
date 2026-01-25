<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Filters Form --}}
        <x-filament::card>
            <form wire:submit.prevent="generateReport">
                {{ $this->form }}
            </form>
        </x-filament::card>

        @if($supplierData)
            {{-- Supplier Info + Summary - Inventory Style --}}
            <x-filament::card>
                <div class="space-y-3">
                    {{-- Supplier Details - Compact Layout --}}
                    <div class="flex flex-wrap gap-x-4 gap-y-1 text-xs">
                        <div>
                            <span class="text-gray-500 dark:text-gray-400">Name:</span>
                            <span class="font-semibold text-gray-900 dark:text-gray-100">{{ $supplierData['name'] }}</span>
                        </div>
                        <div>
                            <span class="text-gray-500 dark:text-gray-400">ID:</span>
                            <span class="font-semibold text-gray-900 dark:text-gray-100">{{ $supplierData['id'] }}</span>
                        </div>
                        <div>
                            <span class="text-gray-500 dark:text-gray-400">Code:</span>
                            <span class="font-semibold text-gray-900 dark:text-gray-100">{{ $supplierData['supplier_code'] }}</span>
                        </div>
                        @if($supplierData['contact_person'])
                        <div>
                            <span class="text-gray-500 dark:text-gray-400">Contact:</span>
                            <span class="font-semibold text-gray-900 dark:text-gray-100">{{ $supplierData['contact_person'] }}</span>
                        </div>
                        @endif
                        <div>
                            <span class="text-gray-500 dark:text-gray-400">Phone:</span>
                            <span class="font-semibold text-gray-900 dark:text-gray-100">{{ $supplierData['phone'] }}</span>
                        </div>
                        @if($supplierData['email'])
                        <div>
                            <span class="text-gray-500 dark:text-gray-400">Email:</span>
                            <span class="font-semibold text-gray-900 dark:text-gray-100">{{ $supplierData['email'] }}</span>
                        </div>
                        @endif
                    </div>
                    
                    {{-- Summary - Horizontal Cards --}}
                    <div class="flex flex-wrap gap-2">
                        <div class="bg-white dark:bg-gray-800 rounded shadow-sm px-3 py-1.5 border-l-2 border-green-500 flex items-center gap-2">
                            <span class="text-xs text-gray-500">Paid:</span>
                            <span class="text-base font-bold text-green-600">₹{{ number_format($summary['total_debit'], 2) }}</span>
                        </div>
                        <div class="bg-white dark:bg-gray-800 rounded shadow-sm px-3 py-1.5 border-l-2 border-red-500 flex items-center gap-2">
                            <span class="text-xs text-gray-500">Payable:</span>
                            <span class="text-base font-bold text-red-600">₹{{ number_format($summary['total_credit'], 2) }}</span>
                        </div>
                        <div class="bg-white dark:bg-gray-800 rounded shadow-sm px-3 py-1.5 border-l-2 border-gray-500 flex items-center gap-2">
                            <span class="text-xs text-gray-500">Net:</span>
                            <span class="text-base font-bold">₹{{ number_format($summary['net_amount'], 2) }}</span>
                        </div>
                        <div class="bg-white dark:bg-gray-800 rounded shadow-sm px-3 py-1.5 border-l-2 border-orange-500 flex items-center gap-2">
                            <span class="text-xs text-gray-500">We Owe:</span>
                            <span class="text-base font-bold {{ $summary['current_balance'] > 0 ? 'text-orange-600' : 'text-green-600' }}">₹{{ number_format($summary['current_balance'], 2) }}</span>
                        </div>
                    </div>
                </div>
            </x-filament::card>

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
                    <div class="overflow-x-auto max-h-[600px] overflow-y-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50 dark:bg-gray-800 sticky top-0 z-10">
                                <tr>
                                    <th class="px-4 py-3 text-left text-gray-700 dark:text-gray-300">Date</th>
                                    <th class="px-4 py-3 text-left text-gray-700 dark:text-gray-300">Reference</th>
                                    <th class="px-4 py-3 text-left text-gray-700 dark:text-gray-300">Description</th>
                                    <th class="px-4 py-3 text-left text-gray-700 dark:text-gray-300">Type</th>
                                    <th class="px-4 py-3 text-right text-gray-700 dark:text-gray-300">Paid (₹)</th>
                                    <th class="px-4 py-3 text-right text-gray-700 dark:text-gray-300">Payable (₹)</th>
                                    <th class="px-4 py-3 text-right text-gray-700 dark:text-gray-300">Balance (₹)</th>
                                    <th class="px-4 py-3 text-center text-gray-700 dark:text-gray-300">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                @foreach($ledgerEntries as $entry)
                                    <tr class="hover:bg-gray-200/30 dark:hover:bg-gray-700/50 transition-colors">
                                        <td class="px-4 py-3 text-gray-900 dark:text-gray-100">{{ $entry['date'] }}</td>
                                        <td class="px-4 py-3">
                                            @if($entry['reference'] && $entry['reference_type'] === 'Sale' && $entry['reference_id'])
                                                <a href="{{ route('filament.admin.resources.sales.view', ['record' => $entry['reference_id']]) }}" 
                                                   class="text-primary-600 dark:text-primary-400 hover:underline flex items-center gap-1"
                                                   title="View Invoice">
                                                    {{ $entry['reference'] }}
                                                    <x-heroicon-o-arrow-top-right-on-square class="w-3 h-3" />
                                                </a>
                                            @else
                                                <span class="text-gray-900 dark:text-gray-100">{{ $entry['reference'] ?? '-' }}</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-gray-900 dark:text-gray-100">
                                            {{ $entry['description'] }}
                                            @if($entry['store'])
                                                <span class="text-xs text-gray-500 dark:text-gray-400">({{ $entry['store'] }})</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3">
                                            <span class="px-2 py-1 text-xs rounded-full
                                                {{ $entry['type'] === 'receivable' ? 'bg-blue-100 dark:bg-blue-900/30 text-blue-800 dark:text-blue-400' : '' }}
                                                {{ $entry['type'] === 'payment' ? 'bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-400' : '' }}
                                                {{ $entry['type'] === 'credit_note' ? 'bg-purple-100 dark:bg-purple-900/30 text-purple-800 dark:text-purple-400' : '' }}">
                                                {{ $entry['type'] === 'receivable' ? 'Receivable' : ucwords(str_replace('_', ' ', $entry['type'])) }}
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
