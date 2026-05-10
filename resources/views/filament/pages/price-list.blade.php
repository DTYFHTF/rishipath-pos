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

    {{-- Voice Search Bar --}}
    @if(!empty($priceList))
    <div id="voice-search-bar" class="mb-5 p-4 rounded-2xl bg-gradient-to-r from-indigo-50 to-blue-50 dark:from-indigo-900/30 dark:to-blue-900/30 border border-indigo-200 dark:border-indigo-700 shadow-sm">
        <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3">

            {{-- Label --}}
            <div class="flex items-center gap-2 shrink-0">
                <svg class="w-5 h-5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
                <span class="text-sm font-semibold text-indigo-700 dark:text-indigo-300 whitespace-nowrap">Search Product</span>
            </div>

            {{-- Text search input --}}
            <div class="relative flex-1">
                <input
                    type="text"
                    id="price-search-input"
                    placeholder="Type or speak a product name…"
                    oninput="priceListSearch(this.value)"
                    autocomplete="off"
                    style="width:100%;padding:12px 44px 12px 16px;font-size:1.05rem;border:2px solid #a5b4fc;border-radius:12px;outline:none;background:#fff;color:#1e1b4b;transition:border-color .2s;"
                    onfocus="this.style.borderColor='#6366f1'" onblur="this.style.borderColor='#a5b4fc'"
                >
                <button onclick="document.getElementById('price-search-input').value='';priceListSearch('');"
                    id="voice-clear-btn"
                    title="Clear"
                    style="display:none;position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:#6b7280;font-size:1.2rem;line-height:1;">
                    ✕
                </button>
            </div>

            {{-- Language selector --}}
            <div class="flex gap-1 shrink-0">
                <button id="lang-en" onclick="setVoiceLang('en-US')"
                    style="padding:8px 14px;border-radius:10px;font-size:0.8rem;font-weight:700;border:2px solid #6366f1;background:#6366f1;color:#fff;cursor:pointer;transition:all .15s;">
                    EN
                </button>
                <button id="lang-ne" onclick="setVoiceLang('ne-NP')"
                    style="padding:8px 14px;border-radius:10px;font-size:0.8rem;font-weight:700;border:2px solid #a5b4fc;background:#fff;color:#6366f1;cursor:pointer;transition:all .15s;">
                    नेपाली
                </button>
                <button id="lang-hi" onclick="setVoiceLang('hi-IN')"
                    style="padding:8px 14px;border-radius:10px;font-size:0.8rem;font-weight:700;border:2px solid #a5b4fc;background:#fff;color:#6366f1;cursor:pointer;transition:all .15s;">
                    हिन्दी
                </button>
            </div>

            {{-- Mic button --}}
            <button id="voice-mic-btn" onclick="toggleVoiceSearch()"
                title="Tap to speak"
                style="flex-shrink:0;width:52px;height:52px;border-radius:50%;background:#6366f1;border:3px solid #4338ca;color:#fff;display:flex;align-items:center;justify-content:center;cursor:pointer;box-shadow:0 4px 14px rgba(99,102,241,.4);transition:all .2s;">
                <svg id="mic-icon" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4M12 3a4 4 0 014 4v4a4 4 0 01-8 0V7a4 4 0 014-4z"/>
                </svg>
            </button>
        </div>

        {{-- Status / feedback bar --}}
        <div id="voice-status" style="display:none;margin-top:10px;padding:10px 16px;border-radius:10px;background:#fff;border:1.5px solid #c7d2fe;font-size:1rem;color:#312e81;font-weight:600;display:flex;align-items:center;gap:10px;min-height:44px;">
            <span id="voice-status-icon">🎙️</span>
            <span id="voice-status-text">Listening…</span>
        </div>

        {{-- Result count --}}
        <div id="voice-result-count" style="display:none;margin-top:8px;font-size:0.85rem;color:#6366f1;font-weight:600;"></div>

        {{-- Keyboard dictation tip (always visible, helpful for Nepal) --}}
        <p style="margin-top:8px;font-size:0.78rem;color:#6b7280;">
            💡 <strong>On mobile?</strong> Tap the text box, then use the <strong>🎤 mic button on your keyboard</strong> — it works in any language even without internet.
        </p>
    </div>
    @endif

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
                                $imageSlug = $variants->first()['image_slug'] ?? '';
                                $imageUrl = $variants->first()['image_url'] ?? null;
                                $imageSrc = $imageUrl ?: ($imageSlug ? '/images/products/' . $imageSlug . '.jpg' : null);
                                $sortedVariants = $variants->sortBy(fn($v) => $v['pack_size_grams'] ?? PHP_INT_MAX)->values();
                                $ruleSource = $sortedVariants->first(fn($v) => !empty($v['one_gram_mrp']));
                                $oneGramMrp = $ruleSource['one_gram_mrp'] ?? null;
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
                                 data-product-name="{{ strtolower($productName) }}">

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
    // ─── Print handler ────────────────────────────────────────────────────────
    document.addEventListener('livewire:initialized', () => {
        Livewire.on('print-price-list', () => {
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
                #voice-search-bar,
                .grid.grid-cols-1.md\\:grid-cols-3 { display: none !important; }

                body, html {
                    padding: 0 !important;
                    margin: 0 !important;
                    background: #fff !important;
                }

                #price-list-print-root {
                    display: block !important;
                }

                .product-grid {
                    display: grid !important;
                    grid-template-columns: 1fr 1fr !important;
                    gap: 0.75rem !important;
                }

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

    function priceListSearch(query) {
        const q = query.trim().toLowerCase();
        const clearBtn = document.getElementById('voice-clear-btn');
        const resultCount = document.getElementById('voice-result-count');

        if (clearBtn) clearBtn.style.display = q ? 'block' : 'none';

        const cards = document.querySelectorAll('.product-card');
        let matched = 0;

        cards.forEach(card => {
            const searchData = card.getAttribute('data-search') || '';
            const visible = !q || searchData.includes(q);
            card.style.display = visible ? '' : 'none';
            if (visible) matched++;
        });

        // Show/hide category sections that have no visible cards
        document.querySelectorAll('[data-category-section]').forEach(section => {
            const hasVisible = Array.from(section.querySelectorAll('.product-card'))
                .some(c => c.style.display !== 'none');
            section.style.display = (q && !hasVisible) ? 'none' : '';
        });

        // Result count
        if (resultCount) {
            if (q) {
                resultCount.style.display = 'block';
                resultCount.textContent = matched === 0
                    ? '❌ No products found for "' + query.trim() + '"'
                    : '✅ ' + matched + ' product' + (matched === 1 ? '' : 's') + ' found';
            } else {
                resultCount.style.display = 'none';
            }
        }

        // Auto-scroll to first match
        if (q && matched > 0) {
            const first = document.querySelector('.product-card[style=""], .product-card:not([style*="none"])');
            if (first) first.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }

        // Highlight matched cards
        cards.forEach(card => {
            card.style.boxShadow = (q && card.style.display !== 'none') ? '0 0 0 3px #6366f1' : '';
        });
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
