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

    {{-- Value banners --}}
    @if($isStale && !empty($priceList))
        <div class="mb-4 p-3 rounded-lg bg-amber-50 border border-amber-200 text-amber-800 text-sm dark:bg-amber-900/20 dark:border-amber-800 dark:text-amber-300">
            <strong>Note:</strong> This price list is more than 24 hours old. Prices may have changed. Click <em>Regenerate</em> to get the latest list.
        </div>
    @endif

    @if(!empty($priceList))
        <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-5">
            <div class="rounded-lg border border-orange-200 bg-orange-50 px-4 py-3 dark:border-orange-900 dark:bg-orange-900/20">
                <p class="text-xs text-orange-700 dark:text-orange-300 uppercase tracking-wide">Changed Prices</p>
                <p class="text-2xl font-semibold text-orange-800 dark:text-orange-200">{{ $this->getChangedPriceCount() }}</p>
                <p class="text-xs text-orange-700/80 dark:text-orange-300/80">Highlighted in orange</p>
            </div>
            <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 dark:border-red-900 dark:bg-red-900/20">
                <p class="text-xs text-red-700 dark:text-red-300 uppercase tracking-wide">Missing 500g/1kg</p>
                <p class="text-2xl font-semibold text-red-800 dark:text-red-200">{{ $this->getMandatoryPackIssueCount() }}</p>
                <p class="text-xs text-red-700/80 dark:text-red-300/80">Weight products with missing compulsory packs</p>
            </div>
            <div class="rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 dark:border-blue-900 dark:bg-blue-900/20">
                <p class="text-xs text-blue-700 dark:text-blue-300 uppercase tracking-wide">MRP Rule</p>
                <p class="text-sm font-semibold text-blue-800 dark:text-blue-200">1g MRP valid up to 15g</p>
                <p class="text-xs text-blue-700/80 dark:text-blue-300/80">20g shown as optional where available</p>
            </div>
        </div>
    @endif

    {{-- Price list table --}}
    @if(!empty($priceList))
        <div class="space-y-6">
            @foreach($priceList as $group)
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm overflow-hidden border border-gray-200 dark:border-gray-700">

                    {{-- Category header --}}
                    @php $grouped = collect($group['items'])->groupBy('product_name'); @endphp
                    <div class="px-4 py-3 bg-primary-50 dark:bg-primary-900/30 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="font-semibold text-primary-800 dark:text-primary-300">{{ $group['category'] }}</h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $grouped->count() }} products &middot; {{ count($group['items']) }} variants</p>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="bg-gray-50 dark:bg-gray-900 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    <th class="px-4 py-2 w-8">#</th>
                                    <th class="px-4 py-2 text-center">Pack Size</th>
                                    <th class="px-4 py-2 text-center">Rule</th>
                                    <th class="px-4 py-2 text-right">Cost</th>
                                    <th class="px-4 py-2 text-right">Wholesale</th>
                                    <th class="px-4 py-2 text-right">MRP</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                @php $productIndex = 0; @endphp
                                @foreach($grouped as $productName => $variants)
                                    @php
                                        $productIndex++;
                                        $anyChanged = $variants->contains('price_changed', true);
                                        $missingPacks = $variants->first()['missing_mandatory_packs'] ?? [];
                                    @endphp
                                    {{-- Product header row --}}
                                    <tr class="bg-gray-100 dark:bg-gray-700/60 border-t-2 border-gray-200 dark:border-gray-600">
                                        <td class="px-4 py-2.5 text-gray-500 dark:text-gray-400 text-xs font-medium align-middle">{{ $productIndex }}</td>
                                        <td colspan="5" class="px-4 py-2.5 align-middle">
                                            <div class="flex items-center gap-2 flex-wrap">
                                                <span class="text-base font-bold text-gray-900 dark:text-gray-100">{{ $productName }}</span>
                                                @if($anyChanged)
                                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium bg-orange-100 text-orange-800 dark:bg-orange-900/50 dark:text-orange-200">Price Changed</span>
                                                @endif
                                                @if(!empty($missingPacks))
                                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900/50 dark:text-red-200">
                                                        Missing: {{ implode(', ', $missingPacks) }}
                                                    </span>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                    {{-- Variant rows --}}
                                    @foreach($variants as $vi => $item)
                                        <tr class="{{ $item['price_changed'] ? 'bg-orange-50 dark:bg-orange-900/20' : ($vi % 2 === 0 ? 'bg-white dark:bg-gray-800' : 'bg-gray-50 dark:bg-gray-900/50') }} hover:bg-blue-50 dark:hover:bg-blue-900/20 transition-colors">
                                            <td class="px-4 py-2"></td>
                                            <td class="px-4 py-2 text-center text-gray-600 dark:text-gray-300">
                                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold {{ $item['pack_color_class'] }}">{{ strtoupper($item['pack_code']) }}</span>
                                                <div class="mt-1 text-xs">{{ $item['pack_size'] }}</div>
                                            </td>
                                            <td class="px-4 py-2 text-center text-xs text-gray-600 dark:text-gray-300">
                                                @if($item['rule_note'])
                                                    <div class="font-medium">{{ $item['rule_note'] }}</div>
                                                @endif
                                                @if($item['one_gram_mrp'])
                                                    <div>1g: NPR {{ number_format($item['one_gram_mrp'], 2) }}</div>
                                                @endif
                                                @if($item['fifteen_gram_mrp'])
                                                    <div>15g cap: NPR {{ number_format($item['fifteen_gram_mrp'], 2) }}</div>
                                                @endif
                                                @if($item['pack_size_grams'] == 20 && $item['optional_20g_mrp'])
                                                    <div>20g ref: NPR {{ number_format($item['optional_20g_mrp'], 2) }}</div>
                                                @endif
                                            </td>
                                            <td class="px-4 py-2 text-right text-gray-600 dark:text-gray-300">
                                                NPR {{ number_format($item['cost_price'], 2) }}
                                            </td>
                                            <td class="px-4 py-2 text-right text-blue-700 dark:text-blue-400 font-medium">
                                                NPR {{ number_format($item['wholesale'], 2) }}
                                            </td>
                                            <td class="px-4 py-2 text-right font-semibold {{ $item['price_changed'] ? 'text-orange-700 dark:text-orange-300' : 'text-green-700 dark:text-green-400' }}">
                                                NPR {{ number_format($item['mrp'], 2) }}
                                            </td>
                                        </tr>
                                    @endforeach
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
