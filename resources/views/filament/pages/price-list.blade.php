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

        {{-- Toggles --}}
        @if(!empty($priceList))
            <div class="flex items-center gap-4 text-sm">
                <label class="flex items-center gap-2 cursor-pointer text-gray-700 dark:text-gray-300">
                    <input type="checkbox" wire:model.live="showInactive" class="rounded border-gray-300">
                    <span>Show Inactive</span>
                </label>
                <label class="flex items-center gap-2 cursor-pointer text-gray-700 dark:text-gray-300">
                    <input type="checkbox" wire:model.live="showCost" class="rounded border-gray-300">
                    <span>Show Cost</span>
                </label>
                <label class="flex items-center gap-2 cursor-pointer text-gray-700 dark:text-gray-300">
                    <input type="checkbox" wire:model.live="showWholesale" class="rounded border-gray-300">
                    <span>Show Wholesale</span>
                </label>
            </div>
        @endif

        {{-- Buttons --}}
        <div class="flex gap-2 sticky top-2 z-30 bg-white/95 dark:bg-gray-900/95 backdrop-blur px-2 py-2 rounded-xl border border-gray-200 dark:border-gray-700 shadow-md">
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
                    wire:click="downloadPdf"
                    style="display:inline-flex;align-items:center;gap:8px;padding:8px 16px;font-size:0.875rem;font-weight:700;color:#ffffff !important;background:#111827 !important;border:2px solid #000000 !important;border-radius:8px;box-shadow:0 2px 6px rgba(0,0,0,.28);cursor:pointer;"
                    onmouseover="this.style.background='#000000'" onmouseout="this.style.background='#111827'"
                >
                    <x-heroicon-o-document-arrow-down class="w-4 h-4"/>
                    Save as PDF
                </button>

                <button
                    wire:click="downloadExcel"
                    style="display:inline-flex;align-items:center;gap:8px;padding:8px 16px;font-size:0.875rem;font-weight:700;color:#ffffff !important;background:#0f766e !important;border:2px solid #115e59 !important;border-radius:8px;box-shadow:0 2px 6px rgba(0,0,0,.28);cursor:pointer;"
                    onmouseover="this.style.background='#115e59'" onmouseout="this.style.background='#0f766e'"
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
                <p class="text-xs text-blue-700 dark:text-blue-300 uppercase tracking-wide">Photo Coverage</p>
                <p class="text-2xl font-semibold text-blue-800 dark:text-blue-200">{{ $this->getProductsWithImageCount() }}/{{ $this->getUniqueProductCount() }}</p>
                <p class="text-xs text-blue-700/80 dark:text-blue-300/80">{{ $this->getImageCoveragePercent() }}% products have photos</p>
            </div>
        </div>
    @endif

    {{-- Price list table --}}
    @if(!empty($priceList))
        <div id="price-list-print-root">
            @foreach($priceList as $group)
                @php $grouped = collect($group['items'])->groupBy('product_name'); @endphp

                {{-- Category section --}}
                <div class="price-list-category mb-8">
                    {{-- Category header --}}
                    <div class="category-header px-4 py-3 rounded-t-xl bg-primary-600 text-white mb-3 flex items-center justify-between">
                        <h3 class="font-bold text-base">{{ $group['category'] }}</h3>
                        <span class="text-xs opacity-80">{{ $grouped->count() }} products &middot; {{ count($group['items']) }} variants</span>
                    </div>

                    {{-- 2-column product grid --}}
                    <div class="product-grid grid grid-cols-1 md:grid-cols-2 gap-3">
                        @php $productIndex = 0; @endphp
                        @foreach($grouped as $productName => $variants)
                            @php
                                $productIndex++;
                                $anyChanged = $variants->contains(fn($v) => !empty($v['price_changed']));
                                $missingPacks = $variants->first()['missing_mandatory_packs'] ?? [];
                                $imageSlug = $variants->first()['image_slug'] ?? '';
                                $imageUrl = $variants->first()['image_url'] ?? null;
                                $imageSrc = $imageUrl ?: ($imageSlug ? '/images/products/' . $imageSlug . '.jpg' : null);
                                $sortedVariants = $variants->sortBy(fn($v) => $v['pack_size_grams'] ?? PHP_INT_MAX)->values();
                                $ruleSource = $sortedVariants->first(fn($v) => !empty($v['one_gram_mrp']));
                                $oneGramMrp = $ruleSource['one_gram_mrp'] ?? null;
                            @endphp

                            {{-- Product card --}}
                            <div class="product-card bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">

                                {{-- Product header --}}
                                <div class="flex items-start gap-3 p-3 bg-gray-50 dark:bg-gray-700/50 border-b border-gray-200 dark:border-gray-600">
                                    @if($imageSrc)
                                        <img src="{{ $imageSrc }}" alt="{{ $productName }}"
                                            class="w-16 h-16 rounded-lg object-cover flex-shrink-0 border border-gray-200 dark:border-gray-600"
                                            onerror="this.style.display='none'">
                                    @endif
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-start gap-2 flex-wrap mb-1">
                                            <span class="text-xs text-gray-400 dark:text-gray-500 font-medium">{{ $productIndex }}.</span>
                                            <span class="font-bold text-gray-900 dark:text-gray-100 leading-snug">{{ $productName }}</span>
                                            @if($anyChanged)
                                                <span class="inline-flex items-center rounded-full px-1.5 py-0.5 text-xs font-medium bg-orange-100 text-orange-800">Price Changed</span>
                                            @endif
                                            @if(!empty($missingPacks))
                                                <span class="inline-flex items-center rounded-full px-1.5 py-0.5 text-xs font-medium bg-red-100 text-red-800">Missing: {{ implode(', ', $missingPacks) }}</span>
                                            @endif
                                        </div>
                                        <div class="flex flex-wrap gap-1 mb-1">
                                            @foreach($sortedVariants as $weightItem)
                                                <span class="inline-flex items-center rounded-full px-1.5 py-0.5 text-xs font-semibold {{ $weightItem['pack_color_class'] ?? 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200' }}">{{ $weightItem['pack_size'] ?? '' }}</span>
                                            @endforeach
                                        </div>
                                        @if($oneGramMrp)
                                            <span class="text-xs text-blue-700 dark:text-blue-300 font-medium">1g = NPR {{ number_format($oneGramMrp, 0) }}</span>
                                        @endif
                                    </div>
                                </div>

                                {{-- Variants table --}}
                                <table class="w-full text-sm">
                                    <thead>
                                        <tr class="bg-gray-50 dark:bg-gray-900/60 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                            <th class="px-3 py-1.5 text-left">Pack Size</th>
                                            @if($this->showCost)<th class="px-3 py-1.5 text-right">Cost</th>@endif
                                            @if($this->showWholesale)<th class="px-3 py-1.5 text-right">Wholesale</th>@endif
                                            <th class="px-3 py-1.5 text-right">MRP</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                        @foreach($sortedVariants as $vi => $item)
                                            @php
                                                $packColorClass = $item['pack_color_class'] ?? 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200';
                                                $packSize       = $item['pack_size'] ?? '';
                                                $wholesale      = $item['wholesale'] ?? 0;
                                                $mrp            = $item['mrp'] ?? 0;
                                                $priceChanged   = !empty($item['price_changed']);
                                            @endphp
                                            <tr class="{{ $priceChanged ? 'bg-orange-50 dark:bg-orange-900/20' : ($vi % 2 === 0 ? 'bg-white dark:bg-gray-800' : 'bg-gray-50 dark:bg-gray-900/40') }}">
                                                <td class="px-3 py-1.5">
                                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold {{ $packColorClass }}">{{ $packSize }}</span>
                                                </td>
                                                @if($this->showCost)
                                                    <td class="px-3 py-1.5 text-right text-gray-700 dark:text-gray-300 text-xs">NPR {{ number_format($item['cost_price'] ?? 0, 0) }}</td>
                                                @endif
                                                @if($this->showWholesale)
                                                    <td class="px-3 py-1.5 text-right text-blue-700 dark:text-blue-200 font-medium text-xs">NPR {{ number_format($wholesale, 0) }}</td>
                                                @endif
                                                <td class="px-3 py-1.5 text-right font-semibold text-xs {{ $priceChanged ? 'text-orange-700 dark:text-orange-200' : 'text-green-700 dark:text-green-300' }}">
                                                    NPR {{ number_format($mrp, 0) }}
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>{{-- /.product-card --}}
                        @endforeach
                    </div>{{-- /.product-grid --}}
                </div>{{-- /.price-list-category --}}
            @endforeach

            {{-- Footer summary --}}
            <div class="text-center text-xs text-gray-400 dark:text-gray-500 pt-2 pb-6">
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
        Livewire.on('print-price-list', () => {
            // Inject a temporary print stylesheet and call window.print() directly
            // (avoids popup blockers entirely)
            let printStyle = document.getElementById('price-list-print-style');
            if (!printStyle) {
                printStyle = document.createElement('style');
                printStyle.id = 'price-list-print-style';
                printStyle.media = 'print';
                document.head.appendChild(printStyle);
            }
            printStyle.textContent = `
                @page { size: A4 landscape; margin: 10mm; }
                .fi-sidebar, .fi-topbar, .fi-topbar-item,
                nav, header, aside, footer,
                [data-sidebar], button,
                .fi-page-header, .fi-breadcrumbs,
                .grid.grid-cols-1.md\\:grid-cols-3 { display: none !important; }

                body, html {
                    padding: 0 !important;
                    margin: 0 !important;
                    background: #fff !important;
                }

                #price-list-print-root {
                    display: block !important;
                }

                /* Keep same 2-column structure in print */
                .product-grid {
                    display: grid !important;
                    grid-template-columns: 1fr 1fr !important;
                    gap: 0.75rem !important;
                }

                /* Prevent splitting one product card across pages */
                .product-card {
                    break-inside: avoid !important;
                    page-break-inside: avoid !important;
                    -webkit-column-break-inside: avoid !important;
                }

                .category-header {
                    break-after: avoid !important;
                    page-break-after: avoid !important;
                }

                * {
                    -webkit-print-color-adjust: exact;
                    print-color-adjust: exact;
                }
            `;
            window.print();
        });
    });
</script>

</x-filament-panels::page>
