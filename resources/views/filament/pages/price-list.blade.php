<x-filament-panels::page>

    {{-- Header stats & action bar --}}
    <div class="flex flex-wrap items-center gap-4 mb-6">

        {{-- Last generated info --}}
        @if($generatedAt)
            <div class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
                <x-heroicon-o-clock class="w-4 h-4"/>
                Last generated: <strong>{{ $this->getGeneratedAtForHumans() }}</strong>
                <span class="text-gray-400">({{ $generatedAt }})</span>

                @if($isStale)
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200">
                        <x-heroicon-o-exclamation-triangle class="w-3 h-3"/>
                        Outdated
                    </span>
                @else
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                        <x-heroicon-o-check-circle class="w-3 h-3"/>
                        Up to date
                    </span>
                @endif
            </div>
        @else
            <p class="text-sm text-gray-500 dark:text-gray-400">No price list generated yet.</p>
        @endif

        {{-- Spacer --}}
        <div class="flex-1"></div>

        {{-- Buttons --}}
        <div class="flex gap-2">
            @if($generatedAt && !$isStale)
                <button
                    wire:click="generate"
                    wire:loading.attr="disabled"
                    class="inline-flex items-center gap-2 px-3 py-1.5 text-sm border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700 transition"
                >
                    <x-heroicon-o-arrow-path wire:loading.class="animate-spin" class="w-4 h-4"/>
                    Regenerate
                </button>
            @else
                <button
                    wire:click="generate"
                    wire:loading.attr="disabled"
                    class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition"
                >
                    <x-heroicon-o-bolt wire:loading.class="hidden" class="w-4 h-4"/>
                    <x-heroicon-o-arrow-path wire:loading.class="animate-spin" wire:loading class="w-4 h-4 hidden"/>
                    Generate Price List
                </button>
            @endif

            @if(!empty($priceList))
                <button
                    wire:click="downloadExcel"
                    class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium bg-green-600 text-white rounded-lg hover:bg-green-700 transition"
                >
                    <x-heroicon-o-arrow-down-tray class="w-4 h-4"/>
                    Download Excel
                </button>
            @endif
        </div>
    </div>

    {{-- Stale warning banner --}}
    @if($isStale && !empty($priceList))
        <div class="mb-4 p-3 rounded-lg bg-amber-50 border border-amber-200 text-amber-800 text-sm dark:bg-amber-900/20 dark:border-amber-800 dark:text-amber-300">
            <strong>Note:</strong> This price list is more than 24 hours old. Prices may have changed. Click <em>Regenerate</em> to get the latest list.
        </div>
    @endif

    {{-- Price list table --}}
    @if(!empty($priceList))
        <div class="space-y-6">
            @foreach($priceList as $group)
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm overflow-hidden border border-gray-200 dark:border-gray-700">

                    {{-- Category header --}}
                    <div class="px-4 py-3 bg-primary-50 dark:bg-primary-900/30 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="font-semibold text-primary-800 dark:text-primary-300">{{ $group['category'] }}</h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ count($group['items']) }} variants</p>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="bg-gray-50 dark:bg-gray-900 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    <th class="px-4 py-2 w-8">#</th>
                                    <th class="px-4 py-2">Product</th>
                                    <th class="px-4 py-2 text-center">Pack Size</th>
                                    <th class="px-4 py-2 text-right">Cost</th>
                                    <th class="px-4 py-2 text-right">Wholesale</th>
                                    <th class="px-4 py-2 text-right">MRP</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                @foreach($group['items'] as $i => $item)
                                    <tr class="{{ $i % 2 === 0 ? 'bg-white dark:bg-gray-800' : 'bg-gray-50 dark:bg-gray-900/50' }} hover:bg-blue-50 dark:hover:bg-blue-900/20 transition-colors">
                                        <td class="px-4 py-2 text-gray-400 text-xs">{{ $i + 1 }}</td>
                                        <td class="px-4 py-2 font-medium text-gray-900 dark:text-gray-100">{{ $item['product_name'] }}</td>
                                        <td class="px-4 py-2 text-center text-gray-600 dark:text-gray-300">{{ $item['pack_size'] }}</td>
                                        <td class="px-4 py-2 text-right text-gray-600 dark:text-gray-300">
                                            NPR {{ number_format($item['cost_price'], 2) }}
                                        </td>
                                        <td class="px-4 py-2 text-right text-blue-700 dark:text-blue-400 font-medium">
                                            NPR {{ number_format($item['wholesale'], 2) }}
                                        </td>
                                        <td class="px-4 py-2 text-right text-green-700 dark:text-green-400 font-semibold">
                                            NPR {{ number_format($item['mrp'], 2) }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endforeach

            {{-- Footer summary --}}
            <div class="text-center text-xs text-gray-400 dark:text-gray-500 pt-2 pb-4">
                {{ $this->getTotalProducts() }} product variants across {{ count($priceList) }} categories &nbsp;·&nbsp;
                Generated {{ $generatedAt }}
            </div>
        </div>
    @else
        <div class="flex flex-col items-center justify-center py-20 text-gray-400 dark:text-gray-500">
            <x-heroicon-o-tag class="w-12 h-12 mb-3 opacity-50"/>
            <p class="text-lg font-medium">No price list yet</p>
            <p class="text-sm mt-1">Click <strong>Generate Price List</strong> to build the current list from your product catalog.</p>
        </div>
    @endif


<script>
    document.addEventListener('livewire:initialized', () => {
        Livewire.on('download-price-list', ({ url }) => {
            const a = document.createElement('a');
            a.href = url;
            a.download = '';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
        });
    });
</script>

</x-filament-panels::page>
