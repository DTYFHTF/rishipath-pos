@php
    $coverage   = $this->getCoverage();
    $recommended = $this->getRecommendedStores();
    $clusters   = $this->mode === 'area' ? $this->getClusters() : collect();
    $selected   = array_map('strval', $this->selectedStoreIds);
    $markers    = $this->getSelectedMarkers();
    $apiKey     = config('services.google_maps.api_key');
@endphp

<x-filament-panels::page>
    <div class="space-y-3">

        {{-- ── Coverage banner: shown only while stores are missing coordinates ── --}}
        @if($coverage['unmapped'] > 0)
            <div class="flex flex-wrap items-center gap-3 rounded-lg border border-warning-300 bg-warning-50 px-3 py-2 text-sm dark:border-warning-700/60 dark:bg-warning-900/20">
                <span class="text-lg leading-none">📍</span>
                <div class="flex-1 min-w-[16rem]">
                    <span class="font-semibold text-warning-800 dark:text-warning-200">
                        {{ $coverage['unmapped'] }} of {{ $coverage['total'] }} stores have no coordinates
                    </span>
                    <span class="text-warning-700 dark:text-warning-300">
                        — they can't be clustered or routed.
                        @if($coverage['linked_unmapped'] > 0)
                            {{ $coverage['linked_unmapped'] }} of them already have a Google Maps link.
                        @endif
                    </span>
                </div>
                @if($coverage['linked_unmapped'] > 0)
                    <x-filament::button
                        size="sm"
                        color="warning"
                        icon="heroicon-m-map-pin"
                        wire:click="resolveCoordinates"
                        wire:loading.attr="disabled"
                        wire:target="resolveCoordinates"
                    >
                        <span wire:loading.remove wire:target="resolveCoordinates">Map {{ $coverage['linked_unmapped'] }} stores from links</span>
                        <span wire:loading wire:target="resolveCoordinates">Reading links…</span>
                    </x-filament::button>
                @endif
            </div>
        @endif

        {{-- ── Toolbar ────────────────────────────────────────────────────────── --}}
        <x-filament::card class="!p-3">
            <div class="flex flex-wrap items-center gap-2">

                {{-- Mode switch --}}
                <div class="inline-flex rounded-lg border border-gray-300 p-0.5 dark:border-gray-600">
                    @foreach(['urgency' => 'By urgency', 'area' => 'By area'] as $value => $label)
                        <button
                            type="button"
                            wire:click="setMode('{{ $value }}')"
                            class="rounded-md px-3 py-1 text-sm font-medium transition
                                {{ $this->mode === $value
                                    ? 'bg-primary-600 text-white shadow-sm'
                                    : 'text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800' }}"
                        >{{ $label }}</button>
                    @endforeach
                </div>

                <div class="h-6 w-px bg-gray-200 dark:bg-gray-700"></div>

                {{-- Who to plan for. Prospects are included by default: they are
                     shops we are still converting, and every new store starts
                     life as one. --}}
                <div class="inline-flex rounded-lg border border-gray-300 p-0.5 dark:border-gray-600">
                    @foreach(['all' => 'All shops', 'customers' => 'Customers', 'prospects' => 'Prospects'] as $value => $label)
                        <button
                            type="button"
                            wire:click="setStatusScope('{{ $value }}')"
                            class="rounded-md px-3 py-1 text-sm font-medium transition
                                {{ $this->statusScope === $value
                                    ? 'bg-primary-600 text-white shadow-sm'
                                    : 'text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800' }}"
                        >{{ $label }}</button>
                    @endforeach
                </div>

                <div class="h-6 w-px bg-gray-200 dark:bg-gray-700"></div>

                @if($this->mode === 'urgency')
                    <label class="flex items-center gap-1.5 text-sm text-gray-600 dark:text-gray-400">
                        Stops
                        <input type="number" wire:model.live.debounce.400ms="storesPerDay" min="1" max="40"
                               class="w-16 rounded-md border-gray-300 bg-white px-2 py-1 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100" />
                    </label>
                @endif

                <label class="flex items-center gap-1.5 text-sm text-gray-600 dark:text-gray-400">
                    Visit every
                    <input type="number" wire:model.live.debounce.400ms="visitIntervalDays" min="7" max="365"
                           class="w-16 rounded-md border-gray-300 bg-white px-2 py-1 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100" />
                    days
                </label>

                <div class="ml-auto flex flex-wrap items-center gap-2">
                    {{-- Start-from-here: hands the browser's position to Livewire --}}
                    <div
                        x-data="{
                            busy: false,
                            locate() {
                                if (!navigator.geolocation) return;
                                this.busy = true;
                                navigator.geolocation.getCurrentPosition(
                                    (p) => { this.busy = false; $wire.setOrigin(p.coords.latitude, p.coords.longitude); },
                                    () => { this.busy = false; alert('Could not read your location. Check browser permissions.'); },
                                    { enableHighAccuracy: true, timeout: 15000 }
                                );
                            }
                        }"
                    >
                        @if($this->originLat !== null)
                            <button type="button" wire:click="clearOrigin"
                                    class="inline-flex items-center gap-1 rounded-lg bg-success-100 px-2.5 py-1.5 text-xs font-medium text-success-800 hover:bg-success-200 dark:bg-success-900/40 dark:text-success-300">
                                Starting from you ✕
                            </button>
                        @else
                            <button type="button" @click="locate()" :disabled="busy"
                                    class="inline-flex items-center gap-1 rounded-lg border border-gray-300 px-2.5 py-1.5 text-xs font-medium text-gray-600 hover:bg-gray-50 disabled:opacity-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-800">
                                <span x-text="busy ? 'Locating…' : 'Start from my location'"></span>
                            </button>
                        @endif
                    </div>

                    <x-filament::button size="sm" color="gray" icon="heroicon-m-arrow-path" wire:click="recalculate">
                        Recalculate
                    </x-filament::button>

                    <x-filament::button size="sm" color="success" icon="heroicon-m-map"
                                        wire:click="buildRoute" wire:loading.attr="disabled" wire:target="buildRoute">
                        Build Route
                    </x-filament::button>
                </div>
            </div>

            {{-- Compact counters --}}
            <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 border-t border-gray-100 pt-2 text-xs text-gray-500 dark:border-gray-800 dark:text-gray-400">
                <span><strong class="text-gray-900 dark:text-gray-100">{{ $coverage['total'] }}</strong> {{ ['all' => 'shops to visit', 'customers' => 'customers', 'prospects' => 'prospects'][$this->statusScope] }}</span>
                <span><strong class="text-success-600">{{ $coverage['mapped'] }}</strong> mapped</span>
                <span><strong class="text-danger-600">{{ $recommended->filter(fn($s) => $s['_days_since'] > $this->visitIntervalDays)->count() }}</strong> overdue</span>
                <span><strong class="text-primary-600">{{ count($selected) }}</strong> in today's plan</span>
                @if($this->mode === 'area')
                    <span><strong class="text-gray-900 dark:text-gray-100">{{ $clusters->count() }}</strong> areas</span>
                @endif
                @if(count($selected) > 0)
                    <button type="button" wire:click="clearSelection" class="text-gray-400 underline hover:text-gray-600">clear</button>
                @endif
            </div>
        </x-filament::card>

        {{-- ── Route result ───────────────────────────────────────────────────── --}}
        @if($this->routeLegs)
            <x-filament::card class="!p-3">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="text-lg leading-none">🗺️</span>
                    <div class="flex-1 min-w-[14rem]">
                        <div class="text-sm font-semibold text-gray-900 dark:text-gray-100">Route ready</div>
                        <div class="text-xs text-gray-500 dark:text-gray-400">{{ $this->routeSummary }}</div>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        @foreach($this->routeLegs as $leg)
                            <a href="{{ $leg['url'] }}" target="_blank" rel="noopener noreferrer"
                               class="inline-flex items-center gap-1.5 rounded-lg bg-success-600 px-3 py-1.5 text-xs font-medium text-white shadow-sm hover:bg-success-700">
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                {{ $leg['label'] }} · {{ count($leg['stops']) }} stops · {{ $leg['distance'] }} km
                            </a>
                        @endforeach
                    </div>
                </div>
            </x-filament::card>
        @endif

        {{-- ── Map preview of today's plan ────────────────────────────────────── --}}
        @if($markers && $apiKey)
            <x-filament::card class="!p-0 overflow-hidden">
                {{-- Keyed on the plan itself so Livewire rebuilds the map
                     whenever the selection or starting point changes. --}}
                <div
                    wire:key="planner-map-{{ md5(json_encode($markers).$this->originLat.$this->originLng) }}"
                    x-data="visitPlannerMap(@js($markers), @js($apiKey), @js($this->originLat !== null ? ['lat' => $this->originLat, 'lng' => $this->originLng] : null))"
                    x-init="init()"
                >
                    <div x-ref="map" class="h-72 w-full"></div>
                </div>
            </x-filament::card>
        @endif

        {{-- ══ AREA MODE ═══════════════════════════════════════════════════════ --}}
        @if($this->mode === 'area')
            <div class="grid gap-2 sm:grid-cols-2 xl:grid-cols-3">
                @forelse($clusters as $cluster)
                    @php $isActive = $this->activeCluster === $cluster['key']; @endphp
                    <div class="rounded-lg border p-3 transition
                        {{ $isActive
                            ? 'border-primary-500 bg-primary-50 dark:border-primary-500 dark:bg-primary-900/20'
                            : 'border-gray-200 bg-white hover:border-gray-300 dark:border-gray-700 dark:bg-gray-900' }}">

                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <div class="truncate text-sm font-semibold text-gray-900 dark:text-gray-100">
                                    {{ $cluster['name'] }}
                                </div>
                                <div class="mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-0.5 text-xs text-gray-500 dark:text-gray-400">
                                    <span class="font-medium text-gray-900 dark:text-gray-100">{{ $cluster['count'] }} shops</span>
                                    @if($cluster['overdue'] > 0)
                                        <span class="text-danger-600">{{ $cluster['overdue'] }} overdue</span>
                                    @endif
                                    @if($cluster['mapped'])
                                        <span>{{ $cluster['spread_km'] }} km across</span>
                                    @else
                                        <span class="text-warning-600">unmapped</span>
                                    @endif
                                </div>
                            </div>
                            <button
                                type="button"
                                wire:click="planCluster('{{ $cluster['key'] }}')"
                                class="shrink-0 rounded-md px-2 py-1 text-xs font-medium transition
                                    {{ $isActive
                                        ? 'bg-primary-600 text-white'
                                        : 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700' }}"
                            >{{ $isActive ? 'Planned' : 'Plan' }}</button>
                        </div>

                        {{-- Top shops in this cluster, kept short --}}
                        <ul class="mt-2 space-y-0.5 border-t border-gray-100 pt-2 text-xs dark:border-gray-800">
                            @foreach(array_slice($cluster['stores'], 0, 4) as $store)
                                <li class="flex items-center justify-between gap-2">
                                    <span class="truncate text-gray-700 dark:text-gray-300">{{ $store['store_name'] }}</span>
                                    <span class="shrink-0 {{ $store['_days_since'] > $this->visitIntervalDays ? 'text-danger-600 font-medium' : 'text-gray-400' }}">
                                        {{ $store['_days_since'] }}d
                                    </span>
                                </li>
                            @endforeach
                            @if($cluster['count'] > 4)
                                <li class="text-gray-400">+{{ $cluster['count'] - 4 }} more</li>
                            @endif
                        </ul>
                    </div>
                @empty
                    <div class="col-span-full rounded-lg border border-dashed border-gray-300 py-10 text-center text-sm text-gray-400 dark:border-gray-700">
                        No active stores to cluster yet.
                    </div>
                @endforelse
            </div>
        @endif

        {{-- ══ STORE LIST ══════════════════════════════════════════════════════ --}}
        <x-filament::card class="!p-0 overflow-hidden">
            <div class="flex items-center justify-between border-b border-gray-100 px-3 py-2 dark:border-gray-800">
                <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                    {{ $this->mode === 'area' ? 'All stores' : 'Recommended' }} — {{ now()->format('D, d M') }}
                </h2>
                <span class="text-xs text-gray-400">tick to add to today's plan</span>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500 dark:bg-gray-800/60 dark:text-gray-400">
                        <tr>
                            <th class="w-8 px-2 py-1.5"></th>
                            <th class="px-2 py-1.5 text-left font-medium">Store</th>
                            <th class="px-2 py-1.5 text-left font-medium">Area</th>
                            <th class="px-2 py-1.5 text-right font-medium">Last</th>
                            <th class="px-2 py-1.5 text-right font-medium">Next</th>
                            <th class="px-2 py-1.5 text-right font-medium">Avg</th>
                            <th class="px-2 py-1.5 text-right font-medium">Score</th>
                            <th class="w-10 px-2 py-1.5 text-center font-medium">Map</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach($recommended->take(80) as $i => $store)
                            @php
                                $isSelected = in_array((string) $store['id'], $selected, true);
                                $isOverdue  = $store['_days_since'] > $this->visitIntervalDays;
                                $mapUrl     = $store['_mapped']
                                    ? "https://maps.google.com/?q={$store['latitude']},{$store['longitude']}"
                                    : ($store['google_location_url'] ?: null);
                            @endphp
                            <tr class="{{ $isSelected ? 'bg-primary-50/70 dark:bg-primary-900/20' : 'hover:bg-gray-50 dark:hover:bg-gray-800/40' }}">
                                <td class="px-2 py-1.5">
                                    <input type="checkbox" value="{{ $store['id'] }}" wire:model.live="selectedStoreIds"
                                           class="rounded border-gray-300 text-primary-600 focus:ring-primary-500" />
                                </td>
                                <td class="px-2 py-1.5">
                                    <div class="flex items-center gap-1.5">
                                        <span class="font-medium text-gray-900 dark:text-gray-100">{{ $store['store_name'] }}</span>
                                        @unless($store['_mapped'])
                                            <span title="No coordinates — cannot be routed" class="rounded bg-warning-100 px-1 text-[10px] font-medium text-warning-700 dark:bg-warning-900/40 dark:text-warning-300">no pin</span>
                                        @endunless
                                    </div>
                                    @if(!empty($store['contact_person']))
                                        <div class="text-xs text-gray-400">{{ $store['contact_person'] }}</div>
                                    @endif
                                </td>
                                <td class="px-2 py-1.5 text-gray-600 dark:text-gray-400">
                                    {{ implode(', ', array_filter([$store['area'] ?? null, $store['city'] ?? null])) ?: '—' }}
                                </td>
                                <td class="px-2 py-1.5 text-right {{ $isOverdue ? 'font-semibold text-danger-600' : 'text-gray-600 dark:text-gray-400' }}">
                                    {{ $store['_days_since'] > 0 ? $store['_days_since'].'d' : 'today' }}
                                </td>
                                <td class="px-2 py-1.5 text-right text-gray-500">{{ $store['_next_visit'] ?? '—' }}</td>
                                <td class="px-2 py-1.5 text-right text-gray-700 dark:text-gray-300">
                                    {{ $store['_avg_order'] > 0 ? '₹'.number_format($store['_avg_order']) : '—' }}
                                </td>
                                <td class="px-2 py-1.5 text-right">
                                    <span class="inline-flex rounded-full px-1.5 py-0.5 text-xs font-semibold
                                        {{ $store['_score'] >= 60 ? 'bg-danger-100 text-danger-800 dark:bg-danger-900/50 dark:text-danger-300'
                                            : ($store['_score'] >= 35 ? 'bg-warning-100 text-warning-800 dark:bg-warning-900/50 dark:text-warning-300'
                                            : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400') }}">
                                        {{ $store['_score'] }}
                                    </span>
                                </td>
                                <td class="px-2 py-1.5 text-center">
                                    @if($mapUrl)
                                        <a href="{{ $mapUrl }}" target="_blank" rel="noopener noreferrer" title="Open in Maps" class="text-primary-600 hover:text-primary-800">📍</a>
                                    @else
                                        <span class="text-gray-300">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                @if($recommended->isEmpty())
                    <div class="py-12 text-center text-sm text-gray-400">
                        No active retail stores for this organisation. Add them under <strong>Retail Stores</strong> first.
                    </div>
                @endif
            </div>
        </x-filament::card>
    </div>

    @push('scripts')
    <script>
        function visitPlannerMap(markers, apiKey, origin) {
            return {
                markers, apiKey, origin,
                map: null,

                async init() {
                    await this.load();

                    const bounds = new google.maps.LatLngBounds();

                    this.map = new google.maps.Map(this.$refs.map, {
                        mapTypeControl: false,
                        streetViewControl: false,
                        gestureHandling: 'cooperative',
                    });

                    const info = new google.maps.InfoWindow();

                    this.markers.forEach((m) => {
                        const position = { lat: m.lat, lng: m.lng };
                        bounds.extend(position);

                        const marker = new google.maps.Marker({
                            position,
                            map: this.map,
                            label: { text: String(m.n), color: '#fff', fontSize: '11px', fontWeight: '600' },
                            title: m.name,
                        });

                        marker.addListener('click', () => {
                            info.setContent(
                                '<div style="font-size:12px"><strong>' + m.n + '. ' + this.escape(m.name) + '</strong>'
                                + (m.area ? '<br>' + this.escape(m.area) : '') + '</div>'
                            );
                            info.open(this.map, marker);
                        });
                    });

                    if (this.origin) {
                        bounds.extend(this.origin);
                        new google.maps.Marker({
                            position: this.origin,
                            map: this.map,
                            title: 'You are here',
                            icon: {
                                path: google.maps.SymbolPath.CIRCLE,
                                scale: 7,
                                fillColor: '#2563eb',
                                fillOpacity: 1,
                                strokeColor: '#fff',
                                strokeWeight: 2,
                            },
                        });
                    }

                    // Visit order, drawn as a dotted line between stops.
                    new google.maps.Polyline({
                        path: (this.origin ? [this.origin] : []).concat(this.markers.map((m) => ({ lat: m.lat, lng: m.lng }))),
                        map: this.map,
                        strokeOpacity: 0,
                        icons: [{
                            icon: { path: 'M 0,-1 0,1', strokeOpacity: 0.7, strokeWeight: 2, scale: 3 },
                            offset: '0',
                            repeat: '12px',
                        }],
                    });

                    this.map.fitBounds(bounds, 48);

                    if (this.markers.length === 1) {
                        google.maps.event.addListenerOnce(this.map, 'idle', () => this.map.setZoom(16));
                    }
                },

                load() {
                    // Reuse the map picker's loader promise when it exists so the
                    // Maps script is only ever injected once per page.
                    if (typeof google !== 'undefined' && google.maps) return Promise.resolve();

                    if (window.__mapPickerLoader) return window.__mapPickerLoader;

                    window.__mapPickerLoader = new Promise((resolve, reject) => {
                        const script = document.createElement('script');
                        script.src = 'https://maps.googleapis.com/maps/api/js?key='
                            + encodeURIComponent(this.apiKey) + '&libraries=places&loading=async&v=weekly';
                        script.async = true;
                        script.onload = resolve;
                        script.onerror = reject;
                        document.head.appendChild(script);
                    });

                    return window.__mapPickerLoader;
                },

                escape(value) {
                    const el = document.createElement('div');
                    el.textContent = value ?? '';
                    return el.innerHTML;
                },
            };
        }
    </script>
    @endpush
</x-filament-panels::page>
