@php
    $summary = $this->summary;
    $groups = $this->groups;
@endphp

<x-filament-panels::page>
    <div class="price-review space-y-3">
        <style>
            /* Filament ships a precompiled stylesheet, so grid-cols-*, space-y-*
               and arbitrary values are unavailable here. Scoped to this page. */
            .price-review .pr-stack > * + * { margin-top: 0.75rem; }
            .price-review .pr-cols { display: grid; gap: 0.5rem; grid-template-columns: repeat(2, minmax(0, 1fr)); }
            @media (min-width: 768px) { .price-review .pr-cols { grid-template-columns: repeat(4, minmax(0, 1fr)); } }
            .price-review .pr-row {
                display: grid;
                grid-template-columns: 5rem 6rem 6rem 5rem 1fr;
                gap: 0.6rem;
                align-items: center;
                padding: 0.4rem 0.15rem;
            }
            .price-review .pr-head {
                font-size: 0.68rem; font-weight: 600; letter-spacing: 0.06em; text-transform: uppercase;
            }
            .price-review .pr-divide > * + * { border-top: 1px solid rgb(0 0 0 / 0.07); }
            .dark .price-review .pr-divide > * + * { border-top-color: rgb(255 255 255 / 0.08); }
            .price-review .pr-num { font-variant-numeric: tabular-nums; text-align: right; }
            .price-review .pr-wrap { overflow-x: auto; }
            .price-review .pr-inner { min-width: 34rem; }
        </style>

        {{-- ── Summary ──────────────────────────────────────────────────── --}}
        <div class="pr-cols">
            <x-filament::card class="!p-3">
                <div class="text-xs text-gray-500 dark:text-gray-400">Products drifted</div>
                <div class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ $summary['products'] }}</div>
            </x-filament::card>
            <x-filament::card class="!p-3">
                <div class="text-xs text-gray-500 dark:text-gray-400">Pack prices</div>
                <div class="text-2xl font-bold text-primary-600">{{ $summary['variants'] }}</div>
            </x-filament::card>
            <x-filament::card class="!p-3">
                <div class="text-xs text-gray-500 dark:text-gray-400">Under-priced</div>
                <div class="text-2xl font-bold text-warning-600">{{ $summary['up'] }}</div>
                <div class="text-xs text-gray-400">cost rose, price didn't</div>
            </x-filament::card>
            <x-filament::card class="!p-3">
                <div class="text-xs text-gray-500 dark:text-gray-400">Over-priced</div>
                <div class="text-2xl font-bold text-success-600">{{ $summary['down'] }}</div>
                <div class="text-xs text-gray-400">cost fell, price didn't</div>
            </x-filament::card>
        </div>

        {{-- ── Controls ─────────────────────────────────────────────────── --}}
        <x-filament::card class="!p-3">
            <div class="flex flex-wrap items-center gap-2">
                <input
                    type="search"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Search a product…"
                    class="flex-1 min-w-[12rem] rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
                />
                <div class="inline-flex rounded-lg border border-gray-300 p-0.5 dark:border-gray-600">
                    @foreach(['all' => 'All', 'up' => 'Under-priced', 'down' => 'Over-priced'] as $value => $label)
                        <button
                            type="button"
                            wire:click="$set('filter', '{{ $value }}')"
                            class="rounded-md px-3 py-1 text-sm font-medium transition
                                {{ $this->filter === $value
                                    ? 'bg-primary-600 text-white shadow-sm'
                                    : 'text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800' }}"
                        >{{ $label }}</button>
                    @endforeach
                </div>
            </div>
        </x-filament::card>

        {{-- ── Drifted products ─────────────────────────────────────────── --}}
        @forelse($groups as $group)
            @php $product = $group['product']; @endphp
            <x-filament::card class="!p-3" wire:key="pr-{{ $product->id }}">
                <div class="flex flex-wrap items-start justify-between gap-2 mb-2">
                    <div class="min-w-0">
                        <div class="font-semibold text-gray-900 dark:text-gray-100">{{ $product->name }}</div>
                        <div class="text-xs text-gray-500 dark:text-gray-400">
                            cost ₹{{ number_format($group['cost_per_kg']) }}/kg
                            @if($group['markup'] !== null)
                                · markup {{ number_format(($group['markup'] - 1) * 100) }}% (fixed)
                            @else
                                · markup {{ number_format((\App\Services\PackPricing::RETAIL_MARKUP_BULK - 1) * 100) }}% from
                                {{ number_format(\App\Services\PackPricing::BULK_THRESHOLD_GRAMS / 1000, 1) }}kg,
                                {{ number_format((\App\Services\PackPricing::RETAIL_MARKUP_SMALL - 1) * 100) }}% below
                            @endif
                            · {{ count($group['rows']) }} {{ Str::plural('pack', count($group['rows'])) }} to review
                        </div>
                    </div>

                    <x-filament::button
                        size="sm"
                        color="primary"
                        icon="heroicon-m-check"
                        wire:click="applyProduct({{ $product->id }})"
                        wire:confirm="Reprice all {{ count($group['rows']) }} packs of {{ $product->name }}?"
                    >
                        Apply all
                    </x-filament::button>
                </div>

                <div class="pr-wrap">
                    <div class="pr-inner pr-divide">
                        <div class="pr-row pr-head text-gray-500 dark:text-gray-400">
                            <span>Pack</span>
                            <span class="pr-num">Now</span>
                            <span class="pr-num">Should be</span>
                            <span class="pr-num">Gap</span>
                            <span></span>
                        </div>

                        @foreach($group['rows'] as $row)
                            <div class="pr-row" wire:key="pr-v-{{ $row['variant_id'] }}">
                                <span class="text-sm text-gray-600 dark:text-gray-400">{{ $row['pack'] }}</span>
                                <span class="pr-num text-sm text-gray-500">₹{{ number_format($row['current']) }}</span>
                                <span class="pr-num text-sm font-semibold text-gray-900 dark:text-gray-100">
                                    ₹{{ number_format($row['derived']) }}
                                </span>
                                <span class="pr-num text-sm font-medium {{ $row['gap'] > 0 ? 'text-warning-600' : 'text-success-600' }}">
                                    {{ $row['gap'] > 0 ? '+' : '' }}{{ number_format($row['gap'] * 100, 1) }}%
                                </span>
                                <span class="flex items-center justify-end gap-2">
                                    <button
                                        type="button"
                                        wire:click="applyVariant({{ $row['variant_id'] }})"
                                        class="rounded-md bg-primary-50 px-2 py-1 text-xs font-medium text-primary-700 hover:bg-primary-100 dark:bg-primary-900/30 dark:text-primary-300"
                                    >Apply</button>
                                    <button
                                        type="button"
                                        wire:click="lockVariant({{ $row['variant_id'] }})"
                                        wire:confirm="Keep ₹{{ number_format($row['current']) }} as a deliberate override? It won't be suggested again."
                                        class="rounded-md border border-gray-300 px-2 py-1 text-xs font-medium text-gray-600 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-800"
                                    >Keep</button>
                                </span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </x-filament::card>
        @empty
            <x-filament::card>
                <div class="py-12 text-center">
                    <div class="text-3xl mb-2">✓</div>
                    <div class="font-medium text-gray-900 dark:text-gray-100">Every price matches its cost</div>
                    <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        @if($this->search !== '' || $this->filter !== 'all')
                            Nothing matches this filter — clear it to see the full queue.
                        @else
                            Nothing has drifted. This page fills up when a purchase lands at a new cost.
                        @endif
                    </div>
                </div>
            </x-filament::card>
        @endforelse
    </div>
</x-filament-panels::page>
