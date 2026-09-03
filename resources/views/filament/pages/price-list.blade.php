<x-filament-panels::page>

    @php $placeholderImage = \App\Filament\Pages\PriceListPage::PLACEHOLDER_IMAGE; @endphp

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
                    wire:click="downloadCompactPdf"
                    title="One row per product, per-kg price only, no photos — for printing and the shop counter"
                    style="display:inline-flex;align-items:center;gap:8px;padding:8px 16px;font-size:0.875rem;font-weight:700;color:#ffffff !important;background:#4338ca !important;border:2px solid #3730a3 !important;border-radius:8px;box-shadow:0 2px 6px rgba(0,0,0,.28);cursor:pointer;"
                    onmouseover="this.style.background='#3730a3'" onmouseout="this.style.background='#4338ca'"
                >
                    <x-heroicon-o-printer class="w-4 h-4"/>
                    Shop Sheet (Compact)
                </button>

            @endif
        </div>
    </div>

    {{-- Shareable public link. Retail prices only; the payload is filtered in
         PublicPriceListController, so cost and wholesale are absent from the
         page rather than hidden in it. --}}
    @if(!empty($priceList))
        <style>
            .share-box { display: flex; flex-wrap: wrap; align-items: center; gap: 0.5rem;
                         margin-bottom: 1rem; padding: 0.6rem 0.75rem; border-radius: 0.75rem;
                         border: 1px solid rgb(199 210 254); background: rgb(238 242 255 / 0.5); }
            .dark .share-box { border-color: rgb(55 48 163); background: rgb(49 46 129 / 0.2); }
            .share-label { font-size: 0.75rem; font-weight: 700; color: #4338ca; white-space: nowrap; }
            .dark .share-label { color: #a5b4fc; }
            .share-url { flex: 1 1 18rem; min-width: 0; font-size: 0.75rem; padding: 6px 10px;
                         border-radius: 8px; border: 1.5px solid #c7d2fe; background: #fff;
                         color: #312e81; font-family: ui-monospace, monospace; }
            .share-btn { flex-shrink: 0; padding: 6px 12px; border-radius: 8px; font-size: 0.75rem;
                         font-weight: 700; cursor: pointer; border: 1.5px solid #4338ca;
                         background: #4338ca; color: #fff; }
            .share-btn-plain { background: #fff; color: #4338ca; }
            .share-hint { flex-basis: 100%; font-size: 0.7rem; color: #6b7280; }
        </style>

        <div class="share-box">
            <span class="share-label">Share price list</span>

            @if($publicUrl)
                <input type="text" class="share-url" readonly value="{{ $publicUrl }}"
                       id="public-price-url" onclick="this.select()">
                <button type="button" class="share-btn" onclick="copyPublicPriceUrl(this)">Copy link</button>
                <a href="{{ $publicUrl }}" target="_blank" rel="noopener" class="share-btn share-btn-plain">Open</a>
                <button type="button" class="share-btn share-btn-plain"
                        wire:click="rotatePublicLink"
                        wire:confirm="Generate a new link? The current one will stop working for everyone you have already sent it to."
                >New link</button>
                <span class="share-hint">Anyone with this link sees retail prices only — no cost, no wholesale. It updates whenever you regenerate the list.</span>
            @else
                <button type="button" class="share-btn" wire:click="createPublicLink">Create shareable link</button>
                <span class="share-hint">Creates an unlisted link you can send on WhatsApp — searchable, always current, retail prices only.</span>
            @endif
        </div>

        <script>
            function copyPublicPriceUrl(button) {
                const field = document.getElementById('public-price-url');
                if (!field) return;
                navigator.clipboard.writeText(field.value).then(() => {
                    const original = button.textContent;
                    button.textContent = 'Copied';
                    setTimeout(() => { button.textContent = original; }, 1500);
                }).catch(() => {
                    // clipboard API needs a secure context; selecting the text
                    // at least leaves it ready for a manual copy.
                    field.select();
                });
            }
        </script>
    @endif

    {{-- Search + filter toolbar.

         Built from a scoped style block rather than utility classes: the admin
         panel loads Filament's precompiled CSS, which carries only the subset
         Filament itself uses — grid-cols-3, sm:hidden and every arbitrary value
         are silently absent, so anything written that way renders unstyled.

         This used to be three stacked blocks (search card, three stat cards,
         filter bar) that filled the whole first screen on a phone before a
         single price was visible. The stats double as filters, so they are the
         chips now, and the two checkboxes they drove are kept in the DOM for
         the filter JS to read. --}}
    @if(!empty($priceList))
    <style>
        .pl-bar { margin-bottom: 1rem; padding: 0.5rem; border-radius: 0.75rem;
                  border: 1px solid rgb(199 210 254); background: rgb(238 242 255 / 0.6); }
        .dark .pl-bar { border-color: rgb(55 48 163); background: rgb(49 46 129 / 0.2); }
        .pl-row { display: flex; align-items: center; gap: 0.5rem; }
        .pl-search { position: relative; flex: 1 1 auto; min-width: 0; }
        .pl-search input { width: 100%; padding: 9px 32px 9px 12px; font-size: 0.95rem;
                           border: 2px solid #a5b4fc; border-radius: 10px; outline: none;
                           background: #fff; color: #1e1b4b; }
        .pl-clear { position: absolute; right: 8px; top: 50%; transform: translateY(-50%);
                    background: none; border: none; cursor: pointer; color: #6b7280;
                    font-size: 1.1rem; line-height: 1; }
        .pl-mic { flex-shrink: 0; width: 42px; height: 42px; border-radius: 50%;
                  background: #6366f1; border: 2px solid #4338ca; color: #fff; display: flex;
                  align-items: center; justify-content: center; cursor: pointer;
                  box-shadow: 0 4px 14px rgba(99,102,241,.4); }

        /* One scrolling strip, so the chips never wrap into extra rows on a phone. */
        .pl-chips { display: flex; align-items: center; gap: 0.375rem; margin-top: 0.5rem;
                    overflow-x: auto; scrollbar-width: none; }
        .pl-chips::-webkit-scrollbar { display: none; }
        .pl-chip { flex-shrink: 0; display: inline-flex; align-items: center; gap: 0.3rem;
                   padding: 5px 10px; border-radius: 999px; font-size: 0.75rem; font-weight: 600;
                   white-space: nowrap; border: 1.5px solid transparent; cursor: pointer;
                   background: #fff; color: #374151; }
        .pl-chip b { font-size: 0.8rem; }
        .pl-chip-changed { border-color: #fed7aa; background: #fff7ed; color: #9a3412; }
        .pl-chip-missing { border-color: #fecaca; background: #fef2f2; color: #991b1b; }
        .pl-chip-photos  { border-color: #bfdbfe; background: #eff6ff; color: #1e40af; cursor: default; }

        /* The filter JS marks an active chip by toggling .ring-2. It also toggles
           ring-orange-400 / ring-red-400, which are not in the precompiled sheet,
           so the active state is drawn here instead of relying on them. */
        #filter-stat-changed.ring-2 { background: #ea580c; border-color: #c2410c; color: #fff; }
        #filter-stat-missing.ring-2 { background: #dc2626; border-color: #b91c1c; color: #fff; }

        /* A global form-select rule paints its own chevron as a background image
           and reserves space for it; the right padding here has to clear that
           or the option text runs underneath it. */
        .pl-select { flex-shrink: 0; font-size: 0.75rem; padding: 5px 2rem 5px 10px; border-radius: 999px;
                     border: 1.5px solid #d1d5db; background-color: #fff; color: #374151; max-width: 9.5rem; }
        .pl-lang { flex-shrink: 0; padding: 5px 9px; border-radius: 999px; font-size: 0.7rem;
                   font-weight: 700; cursor: pointer; }
        .pl-clear-link { flex-shrink: 0; margin-left: auto; padding-left: 0.5rem; font-size: 0.7rem;
                         color: #6b7280; text-decoration: underline; background: none;
                         border: none; cursor: pointer; }
        .pl-count { display: block; margin-top: 0.35rem; font-size: 0.7rem; color: #6b7280; }
        .pl-tip { display: none; margin-top: 0.4rem; font-size: 0.7rem; color: #6b7280; }
        /* The keyboard-dictation hint only matters on a phone. */
        @media (max-width: 640px) { .pl-tip { display: block; } }
    </style>

    <div id="voice-search-bar" class="pl-bar">
        <div class="pl-row">
            <div class="pl-search">
                <input
                    type="text"
                    id="price-search-input"
                    placeholder="Search or speak a product name…"
                    oninput="priceListSearch(this.value)"
                    autocomplete="off"
                    onfocus="this.style.borderColor='#6366f1'" onblur="this.style.borderColor='#a5b4fc'"
                >
                <button type="button" class="pl-clear" id="voice-clear-btn" title="Clear"
                    style="display:none;"
                    onclick="document.getElementById('price-search-input').value='';priceListSearch('');">✕</button>
            </div>

            <button id="voice-mic-btn" class="pl-mic" onclick="toggleVoiceSearch()" title="Tap to speak">
                <svg id="mic-icon" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4M12 3a4 4 0 014 4v4a4 4 0 01-8 0V7a4 4 0 014-4z"/>
                </svg>
            </button>
        </div>

        <div class="pl-chips">
            <select id="filter-category" class="pl-select" onchange="applyPriceListFilters()">
                <option value="">All categories</option>
                @foreach($priceList as $group)
                    <option value="{{ strtolower($group['category']) }}">{{ $group['category'] }}</option>
                @endforeach
            </select>

            <button type="button" id="filter-stat-changed" class="pl-chip pl-chip-changed"
                onclick="togglePriceListFilter('changed')" title="Show only products whose price changed">
                Changed <b>{{ $this->getChangedPriceCount() }}</b>
            </button>

            <button type="button" id="filter-stat-missing" class="pl-chip pl-chip-missing"
                onclick="togglePriceListFilter('missing')" title="Weight products missing a compulsory 500g or 1kg pack">
                Missing packs <b>{{ $this->getMandatoryPackIssueCount() }}</b>
            </button>

            <span class="pl-chip pl-chip-photos" title="{{ $this->getImageCoveragePercent() }}% of products have a photo">
                Photos <b>{{ $this->getProductsWithImageCount() }}/{{ $this->getUniqueProductCount() }}</b>
            </span>

            {{-- setVoiceLang() writes these three buttons' colours inline, so their
                 initial styles have to match what it sets for the active one. --}}
            <button id="lang-en" class="pl-lang" onclick="setVoiceLang('en-US')"
                style="border:2px solid #6366f1;background:#6366f1;color:#fff;">EN</button>
            <button id="lang-ne" class="pl-lang" onclick="setVoiceLang('ne-NP')"
                style="border:2px solid #a5b4fc;background:#fff;color:#6366f1;">नेपाली</button>
            <button id="lang-hi" class="pl-lang" onclick="setVoiceLang('hi-IN')"
                style="border:2px solid #a5b4fc;background:#fff;color:#6366f1;">हिन्दी</button>

            <button type="button" class="pl-clear-link" onclick="clearPriceListFilters()">Clear</button>
        </div>

        {{-- The chips above drive these; the filter JS reads .checked from them. --}}
        <input type="checkbox" id="filter-changed" class="sr-only" onchange="applyPriceListFilters()">
        <input type="checkbox" id="filter-missing" class="sr-only" onchange="applyPriceListFilters()">

        {{-- Hidden until showVoiceStatus() sets display:flex. This previously carried
             display:none and display:flex in one attribute, so the later one won and
             "Listening…" showed permanently. --}}
        <div id="voice-status" style="display:none;align-items:center;gap:10px;margin-top:8px;padding:8px 12px;border-radius:10px;background:#fff;border:1.5px solid #c7d2fe;font-size:0.9rem;color:#312e81;font-weight:600;">
            <span id="voice-status-icon">🎙️</span>
            <span id="voice-status-text">Listening…</span>
        </div>

        <span id="filter-result-count" class="pl-count"></span>

        <p class="pl-tip">💡 Tap the box, then your keyboard's 🎤 — works in any language, even offline.</p>
    </div>
    @endif

    @if($isStale && !empty($priceList))
        <div class="mb-4 p-3 rounded-lg bg-amber-50 border border-amber-200 text-amber-800 text-sm dark:bg-amber-900/20 dark:border-amber-800 dark:text-amber-300">
            <strong>Note:</strong> This price list is more than 24 hours old. Prices may have changed. Click <em>Regenerate</em> to get the latest list.
        </div>
    @endif

    {{-- Price list table --}}
    @if(!empty($priceList))
        <div id="price-list-print-root">
            @foreach($priceList as $group)
                @php $grouped = collect($group['items'])->groupBy('product_name'); @endphp

                {{-- Category section --}}
                <div class="price-list-category mb-8" data-category-section="{{ strtolower($group['category']) }}">
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
                                // image_src is resolved against the filesystem in generate(); null
                                // means the product genuinely has no photo yet, so it falls back to
                                // the neutral studio backdrop rather than collapsing the header.
                                $imageSrc = $variants->first()['image_src'] ?? null;
                                $hasPhoto = (bool) $imageSrc;
                                $imageSrc = $imageSrc ?: $placeholderImage;
                                $sortedVariants = $variants->sortBy(fn($v) => $v['pack_size_grams'] ?? PHP_INT_MAX)->values();
                                $ruleSource = $sortedVariants->first(fn($v) => !empty($v['one_gram_mrp']));
                                $oneGramMrp = $ruleSource['one_gram_mrp'] ?? null;

                                // "From NPR X - Y" span across every pack of this product. Only the
                                // price columns actually visible count, so the low end never quotes a
                                // wholesale rate on a retail-only print. The low end is rounded UP to
                                // the nearest 10 so the advertised entry price is never cheaper than
                                // what we really charge; the high end stays exact.
                                $rangeCandidates = [];
                                foreach ($sortedVariants as $rv) {
                                    $rangeCandidates[] = (float) ($rv['mrp'] ?? 0);
                                    if ($this->showWholesale) { $rangeCandidates[] = (float) ($rv['wholesale'] ?? 0); }
                                    if ($this->showCost)      { $rangeCandidates[] = (float) ($rv['cost_price'] ?? 0); }
                                }
                                $rangeCandidates = array_filter($rangeCandidates, fn($n) => $n > 0);
                                $rangeLow  = $rangeCandidates ? (int) (ceil(min($rangeCandidates) / 10) * 10) : null;
                                $rangeHigh = $rangeCandidates ? (int) round(max($rangeCandidates)) : null;
                                // Build search index from all name fields available
                                $searchIndex = strtolower(
                                    $productName . ' ' .
                                    ($variants->first()['name_nepali'] ?? '') . ' ' .
                                    ($variants->first()['name_hindi'] ?? '') . ' ' .
                                    ($variants->first()['name_romanized'] ?? '') . ' ' .
                                    ($group['category'] ?? '')
                                );
                            @endphp

                            {{-- Product card --}}
                            <div class="product-card bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden"
                                 data-search="{{ $searchIndex }}"
                                 data-product-name="{{ strtolower($productName) }}"
                                 data-changed="{{ $anyChanged ? '1' : '0' }}"
                                 data-missing="{{ !empty($missingPacks) ? '1' : '0' }}">

                                {{-- Product header --}}
                                <div class="flex items-start gap-3 p-3 bg-gray-50 dark:bg-gray-700/50 border-b border-gray-200 dark:border-gray-600">
                                    <div class="relative flex-shrink-0">
                                        <img src="{{ $imageSrc }}" alt="{{ $productName }}"
                                            class="w-16 h-16 rounded-lg object-cover border border-gray-200 dark:border-gray-600 {{ $hasPhoto ? '' : 'opacity-60' }}"
                                            onerror="this.onerror=null;this.src='{{ $placeholderImage }}';this.classList.add('opacity-60');">
                                        @if(!$hasPhoto)
                                            <span class="absolute inset-x-0 bottom-0 rounded-b-lg bg-gray-900/60 py-0.5 text-center text-[9px] font-medium leading-tight text-white print:hidden">No photo</span>
                                        @endif
                                    </div>
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
                                        <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                            @if($rangeLow)
                                                <span class="inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-semibold text-emerald-800 ring-1 ring-inset ring-emerald-600/20 dark:bg-emerald-900/30 dark:text-emerald-200 dark:ring-emerald-400/20">
                                                    @if($rangeHigh > $rangeLow)
                                                        From NPR {{ number_format($rangeLow) }} &ndash; {{ number_format($rangeHigh) }}
                                                    @else
                                                        NPR {{ number_format($rangeHigh) }}
                                                    @endif
                                                </span>
                                            @endif
                                            @if($oneGramMrp)
                                                <span class="text-xs text-blue-700 dark:text-blue-300 font-medium">1g = NPR {{ number_format($oneGramMrp, 0) }}</span>
                                            @endif
                                        </div>
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
    // ─── Print handler ────────────────────────────────────────────────────────
    document.addEventListener('livewire:initialized', () => {
    });

    // ─── Voice Search ─────────────────────────────────────────────────────────
    let voiceLang = 'en-US';
    let recognition = null;
    let isListening = false;

    function setVoiceLang(lang) {
        voiceLang = lang;
        // Update button styles
        const map = { 'en-US': 'lang-en', 'ne-NP': 'lang-ne', 'hi-IN': 'lang-hi' };
        ['lang-en', 'lang-ne', 'lang-hi'].forEach(id => {
            const btn = document.getElementById(id);
            if (!btn) return;
            if (id === map[lang]) {
                btn.style.background = '#6366f1';
                btn.style.color = '#fff';
                btn.style.borderColor = '#6366f1';
            } else {
                btn.style.background = '#fff';
                btn.style.color = '#6366f1';
                btn.style.borderColor = '#a5b4fc';
            }
        });
        if (recognition) recognition.lang = lang;
    }

    // ─── Filters (search + category + changed + missing) ─────────────────────
    // A single combined pass so the filters compose with AND logic instead of
    // fighting over card.style.display — search alone used to own that
    // property outright, which would have made adding a second filter a bug
    // magnet (whichever ran last would silently undo the other).
    let priceListSearchQuery = '';

    function priceListSearch(query) {
        priceListSearchQuery = query.trim().toLowerCase();
        const clearBtn = document.getElementById('voice-clear-btn');
        if (clearBtn) clearBtn.style.display = priceListSearchQuery ? 'block' : 'none';
        applyPriceListFilters();
    }

    function togglePriceListFilter(name) {
        const checkbox = document.getElementById('filter-' + name);
        if (checkbox) checkbox.checked = !checkbox.checked;
        applyPriceListFilters();
    }

    function clearPriceListFilters() {
        document.getElementById('price-search-input').value = '';
        priceListSearchQuery = '';
        document.getElementById('filter-category').value = '';
        document.getElementById('filter-changed').checked = false;
        document.getElementById('filter-missing').checked = false;
        applyPriceListFilters();
    }

    function applyPriceListFilters() {
        const q = priceListSearchQuery;
        const category = (document.getElementById('filter-category')?.value || '').toLowerCase();
        const changedOnly = document.getElementById('filter-changed')?.checked || false;
        const missingOnly = document.getElementById('filter-missing')?.checked || false;
        const anyFilterActive = !!q || !!category || changedOnly || missingOnly;

        // Reflect active state on the two clickable stat cards so it's
        // obvious a click actually did something.
        document.getElementById('filter-stat-changed')?.classList.toggle('ring-2', changedOnly);
        document.getElementById('filter-stat-changed')?.classList.toggle('ring-orange-400', changedOnly);
        document.getElementById('filter-stat-missing')?.classList.toggle('ring-2', missingOnly);
        document.getElementById('filter-stat-missing')?.classList.toggle('ring-red-400', missingOnly);

        const cards = document.querySelectorAll('.product-card');
        let matched = 0;

        cards.forEach(card => {
            const searchData = card.getAttribute('data-search') || '';
            const cardCategory = card.closest('[data-category-section]')?.getAttribute('data-category-section') || '';

            const visible =
                (!q || searchData.includes(q)) &&
                (!category || cardCategory === category) &&
                (!changedOnly || card.getAttribute('data-changed') === '1') &&
                (!missingOnly || card.getAttribute('data-missing') === '1');

            card.style.display = visible ? '' : 'none';
            card.style.boxShadow = (q && visible) ? '0 0 0 3px #6366f1' : '';
            if (visible) matched++;
        });

        // Show/hide category sections that have no visible cards
        document.querySelectorAll('[data-category-section]').forEach(section => {
            const hasVisible = Array.from(section.querySelectorAll('.product-card'))
                .some(c => c.style.display !== 'none');
            section.style.display = (anyFilterActive && !hasVisible) ? 'none' : '';
        });

        const resultEl = document.getElementById('filter-result-count');
        if (resultEl) {
            resultEl.textContent = anyFilterActive
                ? (matched === 0 ? '❌ No products match these filters' : `✅ ${matched} product${matched === 1 ? '' : 's'} shown`)
                : '';
        }

        // Auto-scroll to first match on a text search only — a filter
        // toggle shouldn't yank the page around under the user's cursor.
        if (q && matched > 0) {
            const first = document.querySelector('.product-card[style=""], .product-card:not([style*="none"])');
            if (first) first.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }

    function toggleVoiceSearch() {
        if (isListening) {
            stopListening();
            return;
        }

        const SpeechRec = window.SpeechRecognition || window.webkitSpeechRecognition;
        if (!SpeechRec) {
            showVoiceStatus('⚠️', 'Voice search is not supported in this browser. Please use Chrome or Safari.', '#ef4444');
            setTimeout(hideVoiceStatus, 4000);
            return;
        }

        recognition = new SpeechRec();
        recognition.lang = voiceLang;
        recognition.interimResults = true;
        recognition.maxAlternatives = 3;
        recognition.continuous = false;

        recognition.onstart = () => {
            isListening = true;
            setMicListening(true);
            const langLabels = { 'en-US': 'English', 'ne-NP': 'Nepali', 'hi-IN': 'Hindi' };
            showVoiceStatus('🎙️', 'Listening in ' + (langLabels[voiceLang] || voiceLang) + '… Speak now', '#312e81');
        };

        recognition.onresult = (event) => {
            let interim = '';
            let final = '';
            for (let i = event.resultIndex; i < event.results.length; i++) {
                if (event.results[i].isFinal) {
                    final += event.results[i][0].transcript;
                } else {
                    interim += event.results[i][0].transcript;
                }
            }
            const display = final || interim;
            if (display) {
                document.getElementById('price-search-input').value = display;
                priceListSearch(display);
                showVoiceStatus('🔍', '"' + display + '"', '#1e40af');
            }
            if (final) {
                speakConfirmation(final);
            }
        };

        recognition.onerror = (event) => {
            stopListening();
            if (event.error === 'network') {
                // Google's speech servers unreachable — show keyboard dictation tip instead
                showVoiceStatus('⌨️', 'Voice needs Google servers (unavailable). Use the keyboard mic 🎤 instead — tap the mic icon on your phone\'s keyboard!', '#b45309');
                // Keep visible longer so user can read
                setTimeout(hideVoiceStatus, 9000);
                return;
            }
            const msgs = {
                'no-speech': 'No speech detected. Speak louder or closer to the mic.',
                'audio-capture': 'Microphone not found. Check device permissions.',
                'not-allowed': 'Microphone access denied. Allow it in your browser settings.',
                'aborted': 'Stopped.',
            };
            showVoiceStatus('⚠️', msgs[event.error] || 'Error: ' + event.error, '#ef4444');
            setTimeout(hideVoiceStatus, 5000);
        };

        recognition.onend = () => {
            stopListening();
        };

        recognition.start();
    }

    function stopListening() {
        isListening = false;
        setMicListening(false);
        if (recognition) {
            try { recognition.stop(); } catch(e) {}
            recognition = null;
        }
    }

    function setMicListening(on) {
        const btn = document.getElementById('voice-mic-btn');
        if (!btn) return;
        if (on) {
            btn.style.background = '#ef4444';
            btn.style.borderColor = '#b91c1c';
            btn.style.boxShadow = '0 0 0 6px rgba(239,68,68,.3)';
            btn.style.animation = 'mic-pulse 1s ease-in-out infinite';
            btn.title = 'Tap to stop';
        } else {
            btn.style.background = '#6366f1';
            btn.style.borderColor = '#4338ca';
            btn.style.boxShadow = '0 4px 14px rgba(99,102,241,.4)';
            btn.style.animation = '';
            btn.title = 'Tap to speak';
        }
    }

    function showVoiceStatus(icon, text, color) {
        const bar = document.getElementById('voice-status');
        const iconEl = document.getElementById('voice-status-icon');
        const textEl = document.getElementById('voice-status-text');
        if (!bar) return;
        bar.style.display = 'flex';
        bar.style.borderColor = color || '#c7d2fe';
        if (iconEl) iconEl.textContent = icon;
        if (textEl) { textEl.textContent = text; textEl.style.color = color || '#312e81'; }
    }

    function hideVoiceStatus() {
        const bar = document.getElementById('voice-status');
        if (bar) bar.style.display = 'none';
    }

    function speakConfirmation(text) {
        if (!('speechSynthesis' in window)) return;
        const utterance = new SpeechSynthesisUtterance('Searching for ' + text);
        utterance.lang = 'en-US';
        utterance.rate = 0.95;
        utterance.pitch = 1;
        window.speechSynthesis.cancel();
        window.speechSynthesis.speak(utterance);
    }

    // Mic pulse animation
    const micStyle = document.createElement('style');
    micStyle.textContent = `
        @keyframes mic-pulse {
            0%   { box-shadow: 0 0 0 0 rgba(239,68,68,.5); }
            70%  { box-shadow: 0 0 0 14px rgba(239,68,68,0); }
            100% { box-shadow: 0 0 0 0 rgba(239,68,68,0); }
        }
    `;
    document.head.appendChild(micStyle);
</script>

</x-filament-panels::page>
