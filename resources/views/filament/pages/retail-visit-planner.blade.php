<x-filament-panels::page>
    <div class="space-y-6">

        {{-- ── Controls ─────────────────────────────────────────────────────── --}}
        <x-filament::card>
            <div class="flex flex-wrap items-end gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Stores per Day
                    </label>
                    <input
                        type="number"
                        wire:model="storesPerDay"
                        min="1" max="30"
                        class="w-24 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-gray-100 shadow-sm focus:outline-none focus:ring-2 focus:ring-primary-500"
                    />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Visit Interval (days)
                    </label>
                    <input
                        type="number"
                        wire:model="visitIntervalDays"
                        min="7" max="365"
                        class="w-28 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-gray-100 shadow-sm focus:outline-none focus:ring-2 focus:ring-primary-500"
                    />
                </div>
                <x-filament::button wire:click="recalculate" icon="heroicon-o-arrow-path">
                    Recalculate
                </x-filament::button>
                <x-filament::button
                    wire:click="buildRoute"
                    color="success"
                    icon="heroicon-o-map-pin"
                >
                    Build Route
                </x-filament::button>
                @if($routeUrl)
                    <a href="{{ $routeUrl }}" target="_blank" rel="noopener noreferrer">
                        <x-filament::button color="warning" icon="heroicon-o-arrow-top-right-on-square">
                            Open in Google Maps
                        </x-filament::button>
                    </a>
                @endif
            </div>
        </x-filament::card>

        @php
            $recommended = $this->getRecommendedStores();
            $today       = $recommended->whereIn('id', array_map('intval', $this->selectedStoreIds));
            $totalScore  = $today->sum('_score');
        @endphp

        {{-- ── Summary Strip ────────────────────────────────────────────────── --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
            <x-filament::card class="!p-4">
                <div class="text-xs text-gray-500 dark:text-gray-400">Total Stores</div>
                <div class="text-2xl font-bold text-primary-600">{{ $recommended->count() }}</div>
                <div class="text-xs text-gray-400">active stores in org</div>
            </x-filament::card>
            <x-filament::card class="!p-4">
                <div class="text-xs text-gray-500 dark:text-gray-400">Selected Today</div>
                <div class="text-2xl font-bold text-success-600">{{ count($this->selectedStoreIds) }}</div>
                <div class="text-xs text-gray-400">stores in today's plan</div>
            </x-filament::card>
            <x-filament::card class="!p-4">
                <div class="text-xs text-gray-500 dark:text-gray-400">Overdue Stores</div>
                <div class="text-2xl font-bold text-danger-600">
                    {{ $recommended->filter(fn($s) => $s['_days_since'] > $this->visitIntervalDays)->count() }}
                </div>
                <div class="text-xs text-gray-400">not visited in {{ $this->visitIntervalDays }}+ days</div>
            </x-filament::card>
            <x-filament::card class="!p-4">
                <div class="text-xs text-gray-500 dark:text-gray-400">Avg Order Value</div>
                <div class="text-2xl font-bold text-info-600">
                    ₹{{ number_format($recommended->avg('_avg_order'), 0) }}
                </div>
                <div class="text-xs text-gray-400">across selected stores</div>
            </x-filament::card>
        </div>

        {{-- ── Recommended Store Table ────────────────────────────────────────── --}}
        <x-filament::card>
            <div class="mb-3 flex items-center justify-between">
                <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100">
                    Recommended Stores — {{ now()->format('D, d M Y') }}
                </h2>
                <span class="text-xs text-gray-500">
                    Ranked by urgency score · check/uncheck to customise today's plan
                </span>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                    <thead>
                        <tr class="bg-gray-50 dark:bg-gray-800">
                            <th class="px-3 py-2 text-left font-medium text-gray-500 dark:text-gray-400 w-8">#</th>
                            <th class="px-3 py-2 text-left font-medium text-gray-500 dark:text-gray-400 w-8">✓</th>
                            <th class="px-3 py-2 text-left font-medium text-gray-500 dark:text-gray-400">Store</th>
                            <th class="px-3 py-2 text-left font-medium text-gray-500 dark:text-gray-400">Area / City</th>
                            <th class="px-3 py-2 text-right font-medium text-gray-500 dark:text-gray-400">Last Visit</th>
                            <th class="px-3 py-2 text-right font-medium text-gray-500 dark:text-gray-400">Next Visit</th>
                            <th class="px-3 py-2 text-right font-medium text-gray-500 dark:text-gray-400">Avg Order</th>
                            <th class="px-3 py-2 text-right font-medium text-gray-500 dark:text-gray-400">Score</th>
                            <th class="px-3 py-2 text-center font-medium text-gray-500 dark:text-gray-400">Map</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach($recommended->take(50) as $i => $store)
                            @php
                                $isSelected = in_array((string) $store['id'], $this->selectedStoreIds);
                                $isOverdue  = $store['_days_since'] > $this->visitIntervalDays;
                                $mapUrl     = null;
                                if (!empty($store['latitude']) && !empty($store['longitude'])) {
                                    $mapUrl = "https://maps.google.com/?q={$store['latitude']},{$store['longitude']}";
                                } elseif (!empty($store['google_location_url'])) {
                                    $mapUrl = $store['google_location_url'];
                                }
                            @endphp
                            <tr
                                class="
                                    {{ $isSelected ? 'bg-success-50 dark:bg-success-900/20' : 'hover:bg-gray-50 dark:hover:bg-gray-800/50' }}
                                    {{ $i >= $this->storesPerDay ? 'opacity-50' : '' }}
                                "
                            >
                                <td class="px-3 py-2 text-gray-400 text-xs">{{ $i + 1 }}</td>
                                <td class="px-3 py-2">
                                    <input
                                        type="checkbox"
                                        value="{{ $store['id'] }}"
                                        wire:model="selectedStoreIds"
                                        class="rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                                    />
                                </td>
                                <td class="px-3 py-2">
                                    <div class="font-medium text-gray-900 dark:text-gray-100">
                                        {{ $store['store_name'] }}
                                    </div>
                                    @if(!empty($store['contact_person']))
                                        <div class="text-xs text-gray-500">{{ $store['contact_person'] }}</div>
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-gray-600 dark:text-gray-400">
                                    {{ implode(', ', array_filter([$store['area'] ?? null, $store['city'] ?? null])) ?: '—' }}
                                </td>
                                <td class="px-3 py-2 text-right">
                                    @if($store['_days_since'] > 0)
                                        <span class="{{ $isOverdue ? 'text-danger-600 font-semibold' : 'text-gray-600 dark:text-gray-400' }}">
                                            {{ $store['_days_since'] }}d ago
                                        </span>
                                    @else
                                        <span class="text-success-600">Today</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-right text-gray-600 dark:text-gray-400">
                                    {{ $store['_next_visit'] ?? '—' }}
                                </td>
                                <td class="px-3 py-2 text-right text-gray-700 dark:text-gray-300">
                                    {{ $store['_avg_order'] > 0 ? '₹' . number_format($store['_avg_order'], 0) : '—' }}
                                </td>
                                <td class="px-3 py-2 text-right">
                                    <span class="
                                        inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold
                                        {{ $store['_score'] >= 60 ? 'bg-danger-100 text-danger-800 dark:bg-danger-900 dark:text-danger-300' :
                                           ($store['_score'] >= 35 ? 'bg-warning-100 text-warning-800 dark:bg-warning-900 dark:text-warning-300' :
                                            'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300') }}
                                    ">
                                        {{ $store['_score'] }}
                                    </span>
                                </td>
                                <td class="px-3 py-2 text-center">
                                    @if($mapUrl)
                                        <a href="{{ $mapUrl }}" target="_blank" rel="noopener noreferrer"
                                           class="text-primary-600 hover:text-primary-800 dark:text-primary-400"
                                           title="Open in Maps"
                                        >
                                            📍
                                        </a>
                                    @else
                                        <span class="text-gray-300">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                @if($recommended->count() === 0)
                    <div class="py-12 text-center text-gray-400">
                        No active retail stores found for this organisation.
                        Add stores in <strong>Retail Stores</strong> first.
                    </div>
                @endif
            </div>
        </x-filament::card>

        {{-- ── Route Preview ──────────────────────────────────────────────────── --}}
        @if($routeUrl)
            <x-filament::card>
                <div class="flex items-center gap-3">
                    <div class="text-2xl">🗺️</div>
                    <div>
                        <div class="font-semibold text-gray-900 dark:text-gray-100">Route Ready</div>
                        <div class="text-sm text-gray-500">
                            {{ count($this->selectedStoreIds) }} stops selected, ordered for minimal travel.
                        </div>
                    </div>
                    <a href="{{ $routeUrl }}" target="_blank" rel="noopener noreferrer" class="ml-auto">
                        <x-filament::button color="success" icon="heroicon-o-arrow-top-right-on-square">
                            Open Google Maps Route
                        </x-filament::button>
                    </a>
                </div>
            </x-filament::card>
        @endif

    </div>
</x-filament-panels::page>
