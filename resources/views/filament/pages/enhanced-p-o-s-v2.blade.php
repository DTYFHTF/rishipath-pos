<x-filament-panels::page>
    @php
        $session = $activeSessionKey && isset($sessions[$activeSessionKey]) ? $sessions[$activeSessionKey] : null;
        $wholesale = $this->isWholesale();
        $canWholesale = $this->canUseWholesale();
    @endphp

    <div class="pos-v2-page pos-stack" x-data="posSystem()" x-init="init()" @keydown.window="handleKeyboard($event)">
        <style>
            /*
             * This panel uses Filament's PRE-COMPILED stylesheet (no viteTheme),
             * so Tailwind only ships the subset of utilities Filament itself
             * uses — grid-cols-2/3, space-y-*, translate-x-*, max-h-80 and any
             * arbitrary value like min-h-[72px] are simply absent. That is why
             * the previous layout resorted to inline `width: 66%`.
             *
             * Everything the POS needs beyond that subset is defined here,
             * scoped to .pos-v2-page so no other admin page can be affected.
             */

            /* Layout: single column on phones, 2/3 + 1/3 from 1280px up */
            .pos-v2-page .pos-grid { display: grid; grid-template-columns: minmax(0, 1fr); gap: 0.75rem; }
            @media (min-width: 1280px) {
                .pos-v2-page .pos-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
                .pos-v2-page .pos-grid-main { grid-column: span 2 / span 2; }
            }

            /* Pack-size picker: 2 up on phones, more as space allows */
            .pos-v2-page .pos-packs { display: grid; gap: 0.5rem; grid-template-columns: repeat(2, minmax(0, 1fr)); }
            @media (min-width: 640px) { .pos-v2-page .pos-packs { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
            @media (min-width: 1024px) { .pos-v2-page .pos-packs { grid-template-columns: repeat(4, minmax(0, 1fr)); } }

            .pos-v2-page .pos-cols-2 { display: grid; gap: 0.5rem; grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .pos-v2-page .pos-cols-3 { display: grid; gap: 0.5rem; grid-template-columns: repeat(3, minmax(0, 1fr)); }

            /* Vertical rhythm */
            .pos-v2-page .pos-stack > * + * { margin-top: 0.75rem; }
            .pos-v2-page .pos-stack-sm > * + * { margin-top: 0.5rem; }
            .pos-v2-page .pos-stack-xs > * + * { margin-top: 0.375rem; }

            /* Dividers between list rows */
            .pos-v2-page .pos-divide > * + * { border-top: 1px solid rgb(0 0 0 / 0.08); }
            .dark .pos-v2-page .pos-divide > * + * { border-top-color: rgb(255 255 255 / 0.08); }

            /* Scroll caps */
            .pos-v2-page .pos-scroll-sm { max-height: 15rem; overflow-y: auto; }
            .pos-v2-page .pos-scroll-md { max-height: 20rem; overflow-y: auto; }
            .pos-v2-page .pos-scroll-lg { max-height: 26rem; overflow-y: auto; }

            /* Bits Filament's build does not ship */
            .pos-v2-page .pos-tap { min-height: 72px; }
            .pos-v2-page .pos-search-wrap { min-width: 12rem; }
            .pos-v2-page .pos-strike { text-decoration: line-through; }
            .pos-v2-page .pos-text-11 { font-size: 11px; line-height: 1.35; }
            .pos-v2-page .pos-text-10 { font-size: 10px; line-height: 1.3; }
            .pos-v2-page button[disabled] { cursor: not-allowed; }
            .pos-modal-panel { max-height: 85vh; overflow-y: auto; }
            @media (min-width: 640px) { .pos-modal-panel { max-width: 48rem; } }

            /* Wholesale switch */
            .pos-v2-page .pos-switch { position: relative; display: inline-flex; height: 1.75rem; width: 3rem; flex-shrink: 0; align-items: center; border-radius: 9999px; transition: background-color .15s; }
            .pos-v2-page .pos-switch-knob { display: inline-block; height: 1.25rem; width: 1.25rem; border-radius: 9999px; background: #fff; box-shadow: 0 1px 2px rgb(0 0 0 / .2); transition: transform .15s; transform: translateX(0.25rem); }
            .pos-v2-page .pos-switch[aria-checked="true"] .pos-switch-knob { transform: translateX(1.5rem); }

            /* Sticky checkout bar */
            .pos-checkout-bar {
                position: fixed; left: 0; right: 0; bottom: 0; z-index: 40;
                border-top: 1px solid rgb(0 0 0 / 0.1);
                background: rgba(255, 255, 255, 0.97);
                padding: 0.5rem 0.75rem;
                box-shadow: 0 -2px 12px rgb(0 0 0 / 0.08);
            }
            .dark .pos-checkout-bar { background: rgba(17, 24, 39, 0.97); border-top-color: rgb(255 255 255 / 0.1); }
            @media (min-width: 1024px) { .pos-checkout-bar { display: none; } }

            .dark .pos-search-item:hover,
            .dark .pos-customer-item:hover {
                background-color: rgba(55,65,81,1) !important;
                color: rgb(255 255 255 / 1) !important;
            }

            /* Reclaim horizontal space on phones/tablets. The Filament sidebar
               is intentionally NOT hidden — doing so also removes the hamburger
               drawer and strands the cashier on the POS page with no way out. */
            @media (max-width: 1024px) {
                body:has(.pos-v2-page) .fi-main {
                    padding-left: 0.5rem !important;
                    padding-right: 0.5rem !important;
                    max-width: 100% !important;
                }
                body:has(.pos-v2-page) .fi-page > section,
                body:has(.pos-v2-page) .fi-main-ctn {
                    padding-top: 0.5rem !important;
                }
                /* Room for the sticky checkout bar */
                body:has(.pos-v2-page) .fi-main {
                    padding-bottom: 5.5rem !important;
                }
            }

            /* Touch targets: iOS zooms the page when an input's font-size < 16px */
            @media (max-width: 640px) {
                .pos-v2-page input[type="text"],
                .pos-v2-page input[type="number"],
                .pos-v2-page select {
                    font-size: 16px !important;
                }
            }

            /* Kill the number-input spinners — they are unusable on touch and
               steal width from the quantity field. */
            .pos-qty::-webkit-outer-spin-button,
            .pos-qty::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
            .pos-qty { -moz-appearance: textfield; }
        </style>

        {{-- ── Session tabs ──────────────────────────────────────────────── --}}
        <div class="flex items-center gap-2 overflow-x-auto pb-1 -mx-1 px-1">
            @foreach($sessions as $key => $s)
                <button
                    wire:click="switchToSession('{{ $key }}')"
                    class="flex items-center gap-1.5 px-3 py-2 rounded-lg transition whitespace-nowrap text-sm shrink-0
                        {{ $activeSessionKey === $key
                            ? 'bg-primary-600 text-white shadow'
                            : 'bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700'
                        }}
                        {{ ($s['status'] ?? null) === 'parked' ? 'opacity-60' : '' }}"
                >
                    <x-heroicon-o-shopping-cart class="w-4 h-4" />
                    <span class="font-medium">{{ $s['name'] }}</span>
                    @if(count($s['cart']) > 0)
                        <span class="px-1.5 py-0.5 text-xs rounded-full bg-white/20">{{ count($s['cart']) }}</span>
                    @endif
                    @if(!empty($s['is_wholesale']))
                        <span class="px-1 pos-text-10 font-bold rounded bg-amber-400 text-amber-950">W</span>
                    @endif
                    @if(($s['status'] ?? null) === 'parked')
                        <x-heroicon-o-pause class="w-3 h-3" />
                    @endif
                </button>
            @endforeach

            @if(count($sessions) < 5)
                <button
                    wire:click="createSession"
                    class="flex items-center gap-1.5 px-3 py-2 rounded-lg border-2 border-dashed border-gray-300 dark:border-gray-600 text-gray-500 dark:text-gray-400 hover:border-primary-500 hover:text-primary-600 transition text-sm shrink-0"
                >
                    <x-heroicon-o-plus class="w-4 h-4" />
                    <span>New</span>
                </button>
            @endif
        </div>

        @if($session)
            {{-- ── Billing mode ──────────────────────────────────────────── --}}
            @if($canWholesale)
                <div class="flex flex-wrap items-center gap-2 rounded-lg border px-3 py-2
                    {{ $wholesale
                        ? 'border-amber-300 bg-amber-50 dark:border-amber-700/60 dark:bg-amber-900/20'
                        : 'border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900' }}">
                    <span class="text-lg leading-none">{{ $wholesale ? '🏷️' : '🛒' }}</span>
                    <div class="pos-search-wrap flex-1">
                        <div class="text-sm font-semibold {{ $wholesale ? 'text-amber-900 dark:text-amber-200' : 'text-gray-900 dark:text-gray-100' }}">
                            {{ $wholesale ? 'Wholesale bill (dealer rates)' : 'Retail bill (MRP)' }}
                        </div>
                        <div class="text-xs {{ $wholesale ? 'text-amber-700 dark:text-amber-300' : 'text-gray-500 dark:text-gray-400' }}">
                            {{ $wholesale
                                ? 'Priced at cost + 13%, matching the dealer price list.'
                                : 'Switch on when billing a retail store.' }}
                        </div>
                    </div>

                    {{-- Switch --}}
                    <button
                        type="button"
                        wire:click="toggleWholesale"
                        role="switch"
                        aria-checked="{{ $wholesale ? 'true' : 'false' }}"
                        class="pos-switch focus:outline-none focus:ring-2 focus:ring-offset-2 {{ $wholesale ? 'bg-amber-500 focus:ring-amber-500' : 'bg-gray-300 dark:bg-gray-600 focus:ring-primary-500' }}"
                    >
                        <span class="sr-only">Toggle wholesale billing</span>
                        <span class="pos-switch-knob"></span>
                    </button>
                </div>
            @endif

            <div class="pos-grid">

                {{-- ══ LEFT: search + cart ═══════════════════════════════════ --}}
                <div class="pos-grid-main pos-stack min-w-0">

                    {{-- ── Search ───────────────────────────────────────────── --}}
                    <x-filament::card class="!p-3">
                        @php $picked = $this->selectedProduct; @endphp

                        @if(! $picked)
                            {{-- Step 1: find the product --}}
                            <div class="flex items-center gap-2">
                                <div class="pos-search-wrap relative flex-1 min-w-0">
                                    <input
                                        type="text"
                                        wire:model.live.debounce.300ms="quickSearchInput"
                                        wire:keydown.enter="handleQuickInput"
                                        placeholder="Search or scan…"
                                        class="w-full px-3 py-2.5 rounded-lg border-2 border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 placeholder:text-gray-400 focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20"
                                        autofocus
                                        x-ref="searchInput"
                                        autocomplete="off"
                                        enterkeyhint="search"
                                    />
                                    <div wire:loading wire:target="quickSearchInput" class="absolute right-3 top-1/2 -translate-y-1/2">
                                        <svg class="animate-spin h-4 w-4 text-primary-600" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                        </svg>
                                    </div>
                                </div>

                                @if(strlen($quickSearchInput ?? '') > 0)
                                    <button
                                        wire:click="clearSearch"
                                        class="shrink-0 px-3 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 text-gray-500 hover:bg-gray-50 dark:hover:bg-gray-800"
                                        aria-label="Clear search"
                                    >
                                        <x-heroicon-o-x-mark class="w-5 h-5" />
                                    </button>
                                @endif
                            </div>

                            @php $results = $this->searchResults; @endphp

                            @if(strlen($quickSearchInput ?? '') >= 1)
                                <div class="mt-2 border border-gray-200 dark:border-gray-700 rounded-lg pos-divide pos-scroll-md">
                                    @forelse($results as $result)
                                        <button
                                            type="button"
                                            wire:click="selectProduct({{ $result['id'] }})"
                                            wire:key="product-{{ $result['id'] }}"
                                            class="w-full px-3 py-2.5 text-left hover:bg-gray-50 dark:hover:bg-gray-700 flex items-center gap-3 pos-search-item"
                                        >
                                            @if(!empty($result['image']))
                                                <img src="{{ Storage::url($result['image']) }}" alt="" class="w-11 h-11 object-cover rounded shrink-0">
                                            @else
                                                <div class="w-11 h-11 bg-gray-100 dark:bg-gray-700 rounded flex items-center justify-center shrink-0">
                                                    <x-heroicon-o-cube class="w-5 h-5 text-gray-400" />
                                                </div>
                                            @endif

                                            <div class="flex-1 min-w-0">
                                                <div class="font-medium text-gray-900 dark:text-gray-100 truncate">{{ $result['name'] }}</div>
                                                @if($result['other_names'])
                                                    <div class="text-xs text-blue-600 dark:text-blue-400 truncate">{{ $result['other_names'] }}</div>
                                                @endif
                                            </div>

                                            <div class="text-right shrink-0">
                                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                                    {{ $result['pack_count'] }} {{ Str::plural('size', $result['pack_count']) }}
                                                </div>
                                                @if($result['price_from'])
                                                    <div class="text-sm font-semibold text-green-600 dark:text-green-400">
                                                        from ₹{{ number_format($result['price_from'], 0) }}
                                                    </div>
                                                @endif
                                            </div>

                                            <x-heroicon-o-chevron-right class="w-4 h-4 text-gray-300 shrink-0" />
                                        </button>
                                    @empty
                                        <div class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
                                            <x-heroicon-o-magnifying-glass class="w-8 h-8 mx-auto mb-2 opacity-40" />
                                            <p class="text-sm">Nothing matches “{{ $quickSearchInput }}”</p>
                                            <p class="text-xs mt-1">Try the Nepali/Hindi name, SKU, or barcode.</p>
                                        </div>
                                    @endforelse
                                </div>
                            @endif
                        @else
                            {{-- Step 2: choose the pack size --}}
                            <div class="flex items-center gap-3">
                                <button
                                    wire:click="clearProductSelection"
                                    class="shrink-0 p-2 rounded-lg border border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800"
                                    aria-label="Back to search"
                                >
                                    <x-heroicon-o-arrow-left class="w-5 h-5" />
                                </button>

                                @if(!empty($picked['image']))
                                    <img src="{{ Storage::url($picked['image']) }}" alt="" class="w-11 h-11 object-cover rounded shrink-0">
                                @endif

                                <div class="flex-1 min-w-0">
                                    <div class="font-semibold text-gray-900 dark:text-gray-100 truncate">{{ $picked['name'] }}</div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">Pick a pack size</div>
                                </div>
                            </div>

                            <div class="mt-3 pos-packs">
                                @foreach($picked['variants'] as $variant)
                                    <button
                                        type="button"
                                        wire:click="addToCart({{ $variant['id'] }})"
                                        wire:key="variant-{{ $variant['id'] }}"
                                        class="pos-tap rounded-lg border-2 p-2.5 text-left transition flex flex-col justify-between
                                            {{ $variant['available_stock'] > 0
                                                ? 'border-gray-200 hover:border-primary-500 hover:bg-primary-50 dark:border-gray-700 dark:hover:bg-primary-900/20'
                                                : 'border-dashed border-gray-300 dark:border-gray-700 hover:border-amber-400' }}"
                                    >
                                        <div class="font-semibold text-sm text-gray-900 dark:text-gray-100">{{ $variant['pack_label'] }}</div>
                                        <div>
                                            <div class="font-bold text-green-600 dark:text-green-400">₹{{ number_format($variant['price'], 2) }}</div>
                                            @if($wholesale && $variant['retail_price'] > $variant['price'])
                                                <div class="pos-text-10 pos-strike text-gray-400">₹{{ number_format($variant['retail_price'], 0) }}</div>
                                            @endif
                                            <div class="pos-text-11 {{ $variant['available_stock'] > 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400' }}">
                                                {{ $variant['available_stock'] > 0 ? $variant['available_stock'].' in stock' : 'preorder' }}
                                            </div>
                                        </div>
                                    </button>
                                @endforeach
                            </div>
                        @endif

                        {{-- Shortcuts: desktop only, they mean nothing on a phone --}}
                        <div class="mt-2 hidden lg:flex flex-wrap gap-2 text-xs text-gray-500 dark:text-gray-400">
                            <span><kbd class="px-1.5 py-0.5 bg-white dark:bg-gray-800 rounded border border-gray-300 dark:border-gray-600 text-gray-900 dark:text-white shadow-sm">F1</kbd> New</span>
                            <span><kbd class="px-1.5 py-0.5 bg-white dark:bg-gray-800 rounded border border-gray-300 dark:border-gray-600 text-gray-900 dark:text-white shadow-sm">F2</kbd> Park</span>
                            <span><kbd class="px-1.5 py-0.5 bg-white dark:bg-gray-800 rounded border border-gray-300 dark:border-gray-600 text-gray-900 dark:text-white shadow-sm">F8</kbd> Complete</span>
                            <span><kbd class="px-1.5 py-0.5 bg-white dark:bg-gray-800 rounded border border-gray-300 dark:border-gray-600 text-gray-900 dark:text-white shadow-sm">F9</kbd> Clear</span>
                            <span><kbd class="px-1.5 py-0.5 bg-white dark:bg-gray-800 rounded border border-gray-300 dark:border-gray-600 text-gray-900 dark:text-white shadow-sm">Esc</kbd> Back</span>
                        </div>
                    </x-filament::card>

                    {{-- ── Cart ─────────────────────────────────────────────── --}}
                    <x-filament::card class="!p-3">
                        <div class="flex items-center justify-between mb-2">
                            <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                                Cart
                                @if(count($session['cart']) > 0)
                                    <span class="text-gray-400 font-normal">({{ count($session['cart']) }})</span>
                                @endif
                            </h3>
                            <button
                                wire:click="parkSession"
                                class="px-2.5 py-1.5 text-xs bg-yellow-100 dark:bg-yellow-900/30 text-yellow-700 dark:text-yellow-400 rounded-lg hover:bg-yellow-200 dark:hover:bg-yellow-900/50 transition"
                            >
                                <x-heroicon-o-pause class="w-3.5 h-3.5 inline mr-1" />Park
                            </button>
                        </div>

                        @if(empty($session['cart']))
                            <div class="py-10 text-center text-gray-400 dark:text-gray-500">
                                <x-heroicon-o-shopping-cart class="w-12 h-12 mx-auto mb-2 opacity-40" />
                                <p class="text-sm">Cart is empty</p>
                                <p class="text-xs">Search or scan a product to start</p>
                            </div>
                        @else
                            <div class="pos-stack-sm pos-scroll-lg">
                                @foreach($session['cart'] as $index => $item)
                                    <div wire:key="cart-{{ $activeSessionKey }}-{{ $index }}"
                                         class="p-2.5 bg-gray-50 dark:bg-gray-800 rounded-lg">
                                        {{-- Row 1: product + line total + remove --}}
                                        <div class="flex items-start gap-2">
                                            @if(!empty($item['image']))
                                                <img src="{{ Storage::url($item['image']) }}" alt="" class="w-9 h-9 object-cover rounded shrink-0">
                                            @else
                                                <div class="w-9 h-9 bg-gray-200 dark:bg-gray-700 rounded flex items-center justify-center shrink-0">
                                                    <x-heroicon-o-photo class="w-4 h-4 text-gray-400" />
                                                </div>
                                            @endif

                                            <div class="flex-1 min-w-0">
                                                <p class="font-medium text-sm text-gray-900 dark:text-gray-100 truncate">{{ $item['product_name'] }}</p>
                                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                                    {{ $item['variant_name'] }} · ₹{{ number_format($item['price'], 2) }}
                                                </p>
                                            </div>

                                            <div class="text-right shrink-0">
                                                <p class="font-semibold text-sm text-gray-900 dark:text-gray-100">
                                                    ₹{{ number_format($item['price'] * $item['quantity'], 2) }}
                                                </p>
                                            </div>

                                            <button
                                                wire:click="removeItem({{ $index }})"
                                                class="shrink-0 p-1.5 text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 rounded"
                                                aria-label="Remove item"
                                            >
                                                <x-heroicon-o-trash class="w-4 h-4" />
                                            </button>
                                        </div>

                                        {{-- Row 2: quantity stepper, own line so it never squeezes --}}
                                        <div class="mt-2 flex items-center gap-2">
                                            <button
                                                wire:click="updateQuantity({{ $index }}, {{ max(1, $item['quantity'] - 1) }})"
                                                class="w-9 h-9 flex items-center justify-center rounded-lg bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600"
                                                aria-label="Decrease quantity"
                                            >
                                                <x-heroicon-o-minus class="w-4 h-4" />
                                            </button>

                                            <input
                                                type="number"
                                                inputmode="numeric"
                                                wire:change="updateQuantity({{ $index }}, $event.target.value)"
                                                value="{{ $item['quantity'] }}"
                                                class="pos-qty w-16 h-9 px-2 text-center rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100"
                                                min="1"
                                            />

                                            <button
                                                wire:click="updateQuantity({{ $index }}, {{ $item['quantity'] + 1 }})"
                                                class="w-9 h-9 flex items-center justify-center rounded-lg bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600"
                                                aria-label="Increase quantity"
                                            >
                                                <x-heroicon-o-plus class="w-4 h-4" />
                                            </button>

                                            @if(($item['discount'] ?? 0) > 0)
                                                <span class="ml-auto text-xs text-green-600 dark:text-green-400">
                                                    -₹{{ number_format($item['discount'], 2) }}
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </x-filament::card>
                </div>

                {{-- ══ RIGHT: customer, totals, payment ══════════════════════ --}}
                <div class="pos-stack min-w-0">

                    {{-- ── Customer ─────────────────────────────────────────── --}}
                    <x-filament::card class="!p-3">
                        <div class="flex items-center justify-between mb-2">
                            <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Customer</label>
                            <button
                                wire:click="openCustomerModal"
                                class="text-xs px-2 py-1 bg-primary-600 text-white rounded hover:bg-primary-700 transition"
                            >+ New</button>
                        </div>

                        @if($session['customer_id'])
                            <div class="p-2.5 bg-blue-50 dark:bg-blue-900/30 border border-blue-200 dark:border-blue-700 rounded-lg flex items-center gap-2">
                                <span class="text-xl shrink-0">{{ !empty($session['customer_is_retail_store']) ? '🏪' : '👤' }}</span>
                                <div class="flex-1 min-w-0">
                                    <div class="font-medium text-sm text-gray-900 dark:text-gray-100 truncate">
                                        {{ $session['customer_name'] ?? 'Customer' }}
                                        @if(!empty($session['customer_is_retail_store']))
                                            <span class="ml-1 pos-text-10 bg-indigo-100 dark:bg-indigo-900 text-indigo-700 dark:text-indigo-200 px-1.5 py-0.5 rounded">Store</span>
                                        @endif
                                    </div>
                                    @if(!empty($session['customer_phone']))
                                        <div class="text-xs text-gray-500 dark:text-gray-400 truncate">{{ $session['customer_phone'] }}</div>
                                    @endif
                                </div>
                                <button type="button" wire:click="clearCustomer" class="shrink-0 p-1 text-red-500 hover:text-red-700" aria-label="Remove customer">✕</button>
                            </div>
                        @else
                            <div x-data="{ open: false }" class="relative" wire:key="customer-search-{{ $activeSessionKey }}">
                                <input
                                    type="text"
                                    wire:model.live.debounce.300ms="customerSearch"
                                    @focus="open = true"
                                    @click.away="open = false"
                                    @input="open = true"
                                    placeholder="🔍 Search customer or store…"
                                    class="w-full px-3 py-2.5 text-sm rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100"
                                    autocomplete="off"
                                />

                                <div wire:loading wire:target="customerSearch" class="absolute right-3 top-1/2 -translate-y-1/2">
                                    <svg class="animate-spin h-4 w-4 text-primary-600" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                    </svg>
                                </div>

                                <div x-show="open" x-transition x-cloak
                                     class="absolute z-50 w-full mt-1 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-lg pos-scroll-sm">
                                    <button type="button" wire:click="selectCustomer(null)" @click="open = false"
                                            class="w-full px-3 py-2.5 text-left text-sm hover:bg-gray-100 dark:hover:bg-gray-700 border-b border-gray-100 dark:border-gray-700 flex items-center gap-2 pos-customer-item">
                                        <span>🚶</span>
                                        <span class="font-medium text-gray-900 dark:text-gray-100">Walk-in</span>
                                    </button>

                                    @forelse($this->customers as $customer)
                                        <button type="button" wire:click="selectCustomer({{ $customer->id }})" @click="open = false"
                                                class="w-full px-3 py-2.5 text-left text-sm hover:bg-gray-100 dark:hover:bg-gray-700 flex items-center gap-2 pos-customer-item {{ $customer->retail_store_id ? 'border-l-2 border-indigo-400' : '' }}">
                                            <span>{{ $customer->retail_store_id ? '🏪' : '👤' }}</span>
                                            <div class="flex-1 min-w-0">
                                                <div class="font-medium text-gray-900 dark:text-gray-100 truncate">{{ $customer->name }}</div>
                                                <div class="text-xs text-gray-500 dark:text-gray-400 truncate">
                                                    {{ $customer->phone ?? 'No phone' }}@if($customer->city) · {{ $customer->city }}@endif
                                                </div>
                                            </div>
                                            @if($customer->retail_store_id)
                                                <span class="text-xs bg-indigo-100 dark:bg-indigo-900 text-indigo-700 dark:text-indigo-200 px-1.5 py-0.5 rounded shrink-0">Store</span>
                                            @elseif($customer->loyalty_points > 0)
                                                <span class="text-xs bg-yellow-100 dark:bg-yellow-900 text-yellow-800 dark:text-yellow-200 px-1.5 py-0.5 rounded shrink-0">{{ $customer->loyalty_points }} pts</span>
                                            @endif
                                        </button>
                                    @empty
                                        @if($customerSearch)
                                            <div class="px-3 py-3 text-xs text-gray-500 dark:text-gray-400 text-center">No results</div>
                                        @endif
                                    @endforelse
                                </div>
                            </div>
                        @endif
                    </x-filament::card>

                    {{-- ── Totals ───────────────────────────────────────────── --}}
                    <x-filament::card class="!p-3">
                        <div class="pos-stack-xs text-sm">
                            <div class="flex justify-between text-gray-600 dark:text-gray-400">
                                <span>Subtotal</span>
                                <span class="font-medium">₹{{ number_format($session['subtotal'] ?? 0, 2) }}</span>
                            </div>

                            @if($session['discount'] > 0)
                                <div class="flex justify-between text-green-600 dark:text-green-400">
                                    <span>Discount</span>
                                    <span class="font-medium">-₹{{ number_format($session['discount'], 2) }}</span>
                                </div>
                            @endif

                            @if(!empty($session['applied_reward_id']))
                                <div class="flex justify-between text-purple-600 dark:text-purple-400 items-center">
                                    <span>🎁 Reward</span>
                                    <span class="flex items-center gap-2">
                                        <span class="font-medium">-₹{{ number_format($session['reward_discount'] ?? 0, 2) }}</span>
                                        <button wire:click="removeReward" class="text-red-500 hover:text-red-700 text-xs">✕</button>
                                    </span>
                                </div>
                            @endif

                            <div class="flex justify-between text-gray-600 dark:text-gray-400">
                                <span>Tax</span>
                                <span class="font-medium">₹{{ number_format($session['tax'] ?? 0, 2) }}</span>
                            </div>

                            @if(($session['delivery_charge'] ?? 0) > 0)
                                <div class="flex justify-between text-amber-700 dark:text-amber-300">
                                    <span>Delivery</span>
                                    <span class="font-medium">₹{{ number_format($session['delivery_charge'], 2) }}</span>
                                </div>
                            @endif

                            <div class="pt-2 border-t-2 border-gray-200 dark:border-gray-700 flex justify-between text-lg font-bold text-gray-900 dark:text-gray-100">
                                <span>Total</span>
                                <span>₹{{ number_format($session['total'] ?? 0, 2) }}</span>
                            </div>

                            @if($wholesale)
                                <div class="pos-text-11 text-amber-700 dark:text-amber-300 text-right">dealer rates</div>
                            @endif
                        </div>

                        {{-- Payment method --}}
                        <div class="mt-3 pt-3 border-t border-gray-200 dark:border-gray-700">
                            <label class="block text-xs font-medium mb-2 text-gray-700 dark:text-gray-300">Payment</label>
                            @php
                                $paymentMethods = [
                                    'cash' => ['label' => 'Cash', 'icon' => 'banknotes'],
                                    'upi' => ['label' => 'QR', 'icon' => 'photo'],
                                    'credit' => ['label' => 'Credit', 'icon' => 'credit-card'],
                                ];
                            @endphp
                            <div class="pos-cols-3">
                                @foreach($paymentMethods as $method => $config)
                                    <button
                                        type="button"
                                        wire:click="selectPaymentMethod('{{ $method }}')"
                                        aria-pressed="{{ $session['payment_method'] === $method ? 'true' : 'false' }}"
                                        class="px-2 py-2.5 text-xs rounded-lg border-2 transition font-medium flex flex-col items-center justify-center gap-1
                                            {{ $session['payment_method'] === $method
                                                ? 'border-primary-600 bg-primary-50 dark:bg-primary-900/20 text-primary-700 dark:text-primary-400'
                                                : 'border-gray-300 dark:border-gray-600 hover:border-gray-400 text-gray-700 dark:text-gray-300' }}"
                                    >
                                        <x-dynamic-component :component="'heroicon-o-' . $config['icon']" class="w-4 h-4" />
                                        <span>{{ $config['label'] }}</span>
                                    </button>
                                @endforeach
                            </div>

                            <button
                                wire:click="toggleSplitPayment"
                                class="mt-2 w-full px-2 py-2 text-xs border-2 border-dashed rounded-lg transition font-medium text-gray-600 dark:text-gray-400
                                    {{ $showSplitPayment
                                        ? 'border-primary-500 bg-primary-50 dark:bg-primary-900/20 text-primary-600'
                                        : 'border-gray-300 dark:border-gray-600 hover:border-primary-500 hover:text-primary-600' }}"
                            >
                                <x-heroicon-o-squares-plus class="w-3.5 h-3.5 inline mr-1" />Split payment
                            </button>
                        </div>

                        @if($session['customer_id'] && empty($session['applied_reward_id']))
                            <button
                                wire:click="openRewardModal"
                                class="w-full mt-2 px-3 py-2 text-sm bg-purple-100 dark:bg-purple-900/30 text-purple-700 dark:text-purple-400 rounded-lg hover:bg-purple-200 transition font-medium flex items-center justify-center gap-2"
                            >
                                <x-heroicon-o-gift class="w-4 h-4" />Rewards
                            </button>
                        @endif
                    </x-filament::card>

                    {{-- ── Adjustments & payment detail ─────────────────────── --}}
                    <x-filament::card class="!p-3">
                        <div class="pos-cols-2">
                            <div>
                                <label class="block text-xs font-medium mb-1 text-gray-700 dark:text-gray-300">Delivery ₹</label>
                                <input
                                    type="number" inputmode="decimal" min="0" step="0.01"
                                    value="{{ $session['delivery_charge'] ?? 0 }}"
                                    wire:change="setDeliveryCharge($event.target.value)"
                                    placeholder="0.00"
                                    class="w-full px-2.5 py-2 text-sm rounded-lg border-2 border-amber-300 dark:border-amber-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:border-amber-500"
                                />
                            </div>
                            <div>
                                <label class="block text-xs font-medium mb-1 text-gray-700 dark:text-gray-300">Discount ₹</label>
                                <input
                                    type="number" inputmode="decimal" min="0" step="0.01"
                                    value="{{ $session['manual_discount'] ?? 0 }}"
                                    wire:change="setManualDiscount($event.target.value)"
                                    placeholder="0.00"
                                    class="w-full px-2.5 py-2 text-sm rounded-lg border-2 border-green-300 dark:border-green-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:border-green-500"
                                />
                            </div>
                        </div>

                        @if($session['payment_method'] === 'cash' && !$showSplitPayment)
                            <div class="mt-3">
                                <label class="block text-xs font-medium mb-1 text-gray-700 dark:text-gray-300">Amount received</label>
                                <input
                                    type="number" inputmode="decimal" step="0.01"
                                    wire:model.live.debounce.500ms="sessions.{{ $activeSessionKey }}.amount_received"
                                    placeholder="0.00"
                                    class="w-full px-3 py-2.5 text-sm rounded-lg border-2 border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:border-primary-500"
                                />
                                @if($session['amount_received'] > 0)
                                    <div class="mt-2 p-2 bg-green-50 dark:bg-green-900/20 rounded-lg flex justify-between text-green-700 dark:text-green-400">
                                        <span class="text-xs font-medium">Change</span>
                                        <span class="text-sm font-bold">₹{{ number_format(max(0, $session['amount_received'] - $session['total']), 2) }}</span>
                                    </div>
                                @endif
                            </div>
                        @endif

                        @if($session['payment_method'] === 'upi' && !$showSplitPayment)
                            <div class="mt-3 p-2.5 bg-gray-50 dark:bg-gray-900 rounded-lg">
                                <div class="flex justify-center mb-2">
                                    <img src="/images/fonepay.png" alt="Payment QR" class="max-h-36 object-contain rounded" />
                                </div>
                                <div class="p-2 bg-white dark:bg-gray-800 rounded border border-gray-200 dark:border-gray-700 flex items-start justify-between gap-2">
                                    <div class="pos-text-11 text-gray-900 dark:text-gray-100 leading-relaxed">
                                        <strong>SHUDDHIDHAM AYURVEDA &amp; YOGA WELLNESS SUPPLIERS</strong><br/>
                                        Global IME Bank Ltd<br/>10501010002776
                                    </div>
                                    <button type="button" class="shrink-0 text-sm px-2 py-1 rounded hover:bg-gray-100 dark:hover:bg-gray-700"
                                            onclick="navigator.clipboard.writeText('SHUDDHIDHAM AYURVEDA & YOGA WELLNESS SUPPLIERS, Global IME Bank Ltd, 10501010002776').then(()=>{alert('Copied')})">📋</button>
                                </div>
                            </div>
                        @endif

                        @if($showSplitPayment)
                            @php
                                $splitTotal = collect($splitPayments)->sum('amount');
                                $remaining = max(0, ($session['total'] ?? 0) - $splitTotal);
                            @endphp
                            <div class="mt-3">
                                <div class="flex items-center justify-between mb-2 pb-2 border-b border-gray-200 dark:border-gray-700 text-xs">
                                    <span class="font-medium text-gray-700 dark:text-gray-300">Split payment</span>
                                    <span class="text-right">
                                        <span class="text-primary-600 dark:text-primary-400">₹{{ number_format($splitTotal, 2) }}</span>
                                        <span class="text-gray-400"> / </span>
                                        <span class="text-orange-600 dark:text-orange-400">₹{{ number_format($remaining, 2) }} left</span>
                                    </span>
                                </div>

                                <div class="pos-stack-sm">
                                    @foreach($splitPayments as $index => $split)
                                        <div wire:key="split-{{ $index }}" class="border-2 border-gray-200 dark:border-gray-700 rounded-lg p-2 bg-white dark:bg-gray-800">
                                            <div class="flex items-center gap-1 mb-2">
                                                <select wire:model="splitPayments.{{ $index }}.method"
                                                        class="flex-1 px-2 py-1.5 text-xs rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800">
                                                    <option value="cash">💵 Cash</option>
                                                    <option value="upi">📱 QR</option>
                                                    <option value="bank_transfer">🏦 Bank</option>
                                                    <option value="cheque">📝 Cheque</option>
                                                </select>
                                                @if(count($splitPayments) > 1)
                                                    <button wire:click="removePaymentMethod({{ $index }})" class="p-1.5 text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 rounded">
                                                        <x-heroicon-o-trash class="w-3.5 h-3.5" />
                                                    </button>
                                                @endif
                                            </div>
                                            <input type="number" inputmode="decimal" wire:model="splitPayments.{{ $index }}.amount" step="0.01" placeholder="₹ Amount"
                                                   class="w-full px-2 py-1.5 text-sm font-bold rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 mb-1" />
                                            <input type="text" wire:model="splitPayments.{{ $index }}.reference" placeholder="Ref/Note"
                                                   class="w-full px-2 py-1.5 text-xs rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800" />
                                        </div>
                                    @endforeach
                                </div>

                                <button wire:click="addPaymentMethod"
                                        class="mt-2 w-full px-2 py-2 text-xs border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg hover:border-primary-500 hover:text-primary-600 transition font-medium">
                                    <x-heroicon-o-plus class="w-3.5 h-3.5 inline mr-1" />Add payment
                                </button>
                            </div>
                        @endif
                    </x-filament::card>

                    {{-- ── WhatsApp ─────────────────────────────────────────── --}}
                    @if(!empty($session['customer_id']) && !empty($session['customer_phone']))
                        <x-filament::card class="!p-3">
                            <label class="flex items-center cursor-pointer gap-3">
                                <input type="checkbox" wire:model="sendWhatsApp"
                                       class="h-5 w-5 text-primary-600 border-gray-300 rounded focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800" />
                                <span class="flex-1 min-w-0">
                                    <span class="block text-sm font-medium text-gray-700 dark:text-gray-300">Send receipt on WhatsApp</span>
                                    <span class="block text-xs text-gray-500 dark:text-gray-400 truncate">To: {{ $session['customer_phone'] }}</span>
                                </span>
                                <x-heroicon-o-chat-bubble-left-right class="w-5 h-5 text-green-600 dark:text-green-400 shrink-0" />
                            </label>
                        </x-filament::card>
                    @endif

                    {{-- ── Actions (desktop; phones use the sticky bar) ─────── --}}
                    <div class="hidden lg:block pos-stack-sm">
                        <button
                            wire:click="completeSale"
                            wire:loading.attr="disabled"
                            wire:target="completeSale"
                            {{ empty($session['cart']) ? 'disabled' : '' }}
                            class="w-full px-6 py-4 rounded-lg transition font-bold text-lg shadow-lg text-white disabled:opacity-50 disabled:cursor-not-allowed
                                {{ $wholesale ? 'bg-amber-600 hover:bg-amber-700' : 'bg-green-600 hover:bg-green-700' }}"
                        >
                            <x-heroicon-o-check-circle class="w-6 h-6 inline mr-2" />
                            <span wire:loading.remove wire:target="completeSale">
                                Complete {{ $wholesale ? 'Wholesale ' : '' }}Sale (F8)
                            </span>
                            <span wire:loading wire:target="completeSale">Processing…</span>
                        </button>

                        <button
                            wire:click="closeSession('{{ $activeSessionKey }}')"
                            class="w-full px-4 py-2 bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400 rounded-lg hover:bg-red-200 transition font-medium"
                        >Clear Cart (F9)</button>
                    </div>
                </div>
            </div>

            {{-- ── Sticky checkout bar (phone/tablet only) ───────────────── --}}
            <div class="pos-checkout-bar">
                <div class="flex items-center gap-2">
                    <div class="min-w-0">
                        <div class="pos-text-11 text-gray-500 dark:text-gray-400 leading-none">
                            {{ count($session['cart']) }} {{ Str::plural('item', count($session['cart'])) }}{{ $wholesale ? ' · wholesale' : '' }}
                        </div>
                        <div class="text-lg font-bold text-gray-900 dark:text-gray-100 leading-tight">
                            ₹{{ number_format($session['total'] ?? 0, 2) }}
                        </div>
                    </div>

                    <button
                        wire:click="completeSale"
                        wire:loading.attr="disabled"
                        wire:target="completeSale"
                        {{ empty($session['cart']) ? 'disabled' : '' }}
                        class="flex-1 px-4 py-3 rounded-lg font-bold text-white shadow disabled:opacity-50 disabled:cursor-not-allowed
                            {{ $wholesale ? 'bg-amber-600 active:bg-amber-700' : 'bg-green-600 active:bg-green-700' }}"
                    >
                        <span wire:loading.remove wire:target="completeSale">Complete Sale</span>
                        <span wire:loading wire:target="completeSale">Processing…</span>
                    </button>
                </div>
            </div>

            {{-- ── Reward modal ─────────────────────────────────────────── --}}
            @if($showRewardModal)
                <div class="fixed inset-0 bg-black/50 flex items-end sm:items-center justify-center z-[9999] p-0 sm:p-4" wire:click.self="$set('showRewardModal', false)">
                    <div class="pos-modal-panel bg-white dark:bg-gray-800 rounded-t-2xl sm:rounded-lg p-4 sm:p-6 w-full" role="dialog" aria-modal="true">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg sm:text-2xl font-bold text-gray-900 dark:text-gray-100">🎁 Rewards</h3>
                            <button wire:click="$set('showRewardModal', false)" class="p-1 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                                <x-heroicon-o-x-mark class="w-6 h-6" />
                            </button>
                        </div>

                        @php
                            $customer = $session['customer_id'] ? \App\Models\Customer::with('loyaltyTier')->find($session['customer_id']) : null;
                        @endphp

                        @if($customer)
                            <div class="mb-4 p-3 bg-gradient-to-r from-purple-50 to-blue-50 dark:from-purple-900/20 dark:to-blue-900/20 rounded-lg border border-purple-200 dark:border-purple-700 flex items-center justify-between">
                                <div>
                                    <div class="text-xs text-gray-600 dark:text-gray-400">Points</div>
                                    <div class="text-2xl font-bold text-purple-700 dark:text-purple-300">{{ number_format($customer->loyalty_points) }}</div>
                                </div>
                                @if($customer->loyaltyTier)
                                    <x-filament::badge color="{{ $customer->loyaltyTier->badge_color }}">{{ $customer->loyaltyTier->name }}</x-filament::badge>
                                @endif
                            </div>
                        @endif

                        <div class="pos-stack-sm">
                            @forelse($availableRewards as $reward)
                                <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-3 hover:border-purple-500 transition">
                                    <div class="flex items-start gap-3">
                                        @if($reward['image_url'])
                                            <img src="{{ Storage::url($reward['image_url']) }}" alt="" class="w-14 h-14 object-cover rounded-lg shrink-0">
                                        @else
                                            <div class="w-14 h-14 bg-gradient-to-br from-purple-400 to-pink-400 rounded-lg flex items-center justify-center text-2xl shrink-0">🎁</div>
                                        @endif

                                        <div class="flex-1 min-w-0">
                                            <h4 class="font-bold text-gray-900 dark:text-gray-100">{{ $reward['name'] }}</h4>
                                            <div class="flex flex-wrap items-center gap-x-3 gap-y-1 mt-1 text-xs">
                                                <span class="text-purple-600 dark:text-purple-400 font-medium">{{ $reward['points_required'] }} pts</span>
                                                <span class="text-green-600 dark:text-green-400 font-medium">
                                                    @if($reward['type'] === 'discount_percentage')
                                                        {{ $reward['discount_value'] }}% off
                                                    @elseif($reward['type'] === 'discount_fixed')
                                                        ₹{{ number_format($reward['discount_value'], 2) }} off
                                                    @else
                                                        {{ ucfirst($reward['type']) }}
                                                    @endif
                                                </span>
                                            </div>
                                        </div>

                                        <button wire:click="applyReward({{ $reward['id'] }})"
                                                class="shrink-0 px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition font-medium text-sm">
                                            Apply
                                        </button>
                                    </div>
                                </div>
                            @empty
                                <div class="text-center py-10">
                                    <x-heroicon-o-gift class="w-12 h-12 mx-auto mb-3 text-gray-300 dark:text-gray-600" />
                                    <p class="text-gray-600 dark:text-gray-400">No rewards available</p>
                                </div>
                            @endforelse
                        </div>
                    </div>
                </div>
            @endif
        @endif

        <script>
            function posSystem() {
                return {
                    init() {
                        this.$nextTick(() => {
                            // Autofocus steals the screen on a phone by popping the
                            // keyboard over the cart, so only focus on desktop.
                            if (window.matchMedia('(min-width: 1024px)').matches) {
                                this.$refs.searchInput?.focus();
                            }
                        });
                    },

                    handleKeyboard(event) {
                        if (event.key === 'F1') { event.preventDefault(); @this.call('createSession'); }
                        if (event.key === 'F2') { event.preventDefault(); @this.call('parkSession'); }
                        if (event.key === 'F8') { event.preventDefault(); @this.call('completeSale'); }

                        if (event.key === 'F9') {
                            event.preventDefault();
                            if (confirm('Clear the current cart?')) {
                                @this.call('closeSession', @this.activeSessionKey);
                            }
                        }

                        if (event.key === '/' && !event.target.matches('input, textarea, select')) {
                            event.preventDefault();
                            this.$refs.searchInput?.focus();
                        }

                        // Esc steps back out of the pack-size picker, then clears search.
                        if (event.key === 'Escape') {
                            if (@this.get('selectedProductId')) {
                                @this.call('clearProductSelection');
                            } else if (@this.get('quickSearchInput')) {
                                @this.call('clearSearch');
                            }
                        }
                    },
                };
            }
        </script>
    </div>
</x-filament-panels::page>
