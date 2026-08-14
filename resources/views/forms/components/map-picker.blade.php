@php
    $apiKey = config('services.google_maps.api_key');
    $mapHeight = $getMapHeight();
    $defaultLocation = $getDefaultLocation();
    $defaultZoom = $getDefaultZoom();
    $isDraggable = $isDraggable();
    $showGeolocation = $getShowGeolocationButton();
    $statePath = $getStatePath();

    $targetFields = array_filter([
        'lat' => $getLatitudeField(),
        'lng' => $getLongitudeField(),
        'address' => $getAddressField(),
        'area' => $getAreaField(),
        'landmark' => $getLandmarkField(),
        'city' => $getCityField(),
        'state' => $getStateField(),
        'country' => $getCountryField(),
        'pincode' => $getPincodeField(),
        'googleUrl' => $getGoogleUrlField(),
    ]);
@endphp

<x-dynamic-component
    :component="$getFieldWrapperView()"
    :field="$field"
>
    <div
        x-data="mapPickerComponent({
            state: $wire.{{ $applyStateBindingModifiers("\$entangle('{$statePath}')") }},
            apiKey: @js($apiKey),
            defaultLocation: @js($defaultLocation),
            defaultZoom: {{ $defaultZoom }},
            draggable: {{ $isDraggable ? 'true' : 'false' }},
            showGeolocation: {{ $showGeolocation ? 'true' : 'false' }},
            fields: @js($targetFields),
        })"
        x-init="initMap()"
        wire:ignore
        class="w-full space-y-2"
    >
        {{-- Toolbar: search + actions on one compact row --}}
        <div class="flex flex-wrap items-center gap-2">
            <div class="relative min-w-[12rem] flex-1">
                <svg class="pointer-events-none absolute left-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
                <input
                    x-ref="searchInput"
                    type="text"
                    placeholder="Search a shop, street or landmark…"
                    class="w-full rounded-lg border border-gray-300 bg-white py-1.5 pl-8 pr-3 text-sm text-gray-900 shadow-sm focus:border-primary-500 focus:ring-1 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                />
            </div>

            <template x-if="showGeolocation">
                <button
                    type="button"
                    @click="getUserLocation()"
                    :disabled="isBusy"
                    class="inline-flex items-center gap-1.5 rounded-lg bg-primary-600 px-3 py-1.5 text-sm font-medium text-white shadow-sm hover:bg-primary-700 focus:ring-2 focus:ring-primary-500 focus:ring-offset-1 disabled:opacity-50"
                >
                    <svg x-show="!isLoadingLocation" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    <svg x-show="isLoadingLocation" x-cloak class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                    <span x-text="isLoadingLocation ? 'Locating…' : 'Use My Location'"></span>
                </button>
            </template>

            <button
                type="button"
                @click="clearLocation()"
                class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-800"
            >
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
                Clear
            </button>
        </div>

        {{-- Map --}}
        <div
            x-ref="map"
            style="height: {{ $mapHeight ?? '400px' }};"
            class="w-full overflow-hidden rounded-lg border border-gray-300 dark:border-gray-600"
        ></div>

        {{-- Single compact status line: coordinates, progress, warnings, errors --}}
        <div class="flex min-h-[1.5rem] flex-wrap items-center gap-x-3 gap-y-1 text-xs">
            <template x-if="state">
                <span class="inline-flex items-center gap-1 rounded-md bg-success-50 px-2 py-1 font-medium text-success-700 dark:bg-success-900/30 dark:text-success-300">
                    <svg class="h-3.5 w-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd"/></svg>
                    <span x-text="state"></span>
                </span>
            </template>

            <template x-if="!state && !mapError">
                <span class="text-gray-400">Tap the map, search above, or use your location.</span>
            </template>

            <template x-if="status">
                <span class="text-gray-500 dark:text-gray-400" x-text="status"></span>
            </template>

            <template x-if="approximate">
                <span class="inline-flex items-center gap-1 rounded-md bg-warning-50 px-2 py-1 font-medium text-warning-700 dark:bg-warning-900/30 dark:text-warning-300">
                    Nearest match used — check the address fields
                </span>
            </template>
        </div>

        {{-- Errors are shown, never swallowed into the console --}}
        <template x-if="mapError">
            <div class="rounded-lg border border-danger-200 bg-danger-50 p-3 text-xs text-danger-700 dark:border-danger-800 dark:bg-danger-900/20 dark:text-danger-300">
                <p class="font-semibold" x-text="mapError"></p>
                <p x-show="mapErrorHint" class="mt-1 opacity-90" x-text="mapErrorHint"></p>
            </div>
        </template>
    </div>

    @once
        @push('scripts')
        <script>
            // One shared loader promise so multiple pickers on a page never
            // inject the Maps script twice.
            window.__mapPickerLoader = window.__mapPickerLoader || null;

            function mapPickerComponent(config) {
                return {
                    state: config.state,
                    apiKey: config.apiKey,
                    defaultLocation: config.defaultLocation,
                    defaultZoom: config.defaultZoom,
                    draggable: config.draggable,
                    showGeolocation: config.showGeolocation,
                    fields: config.fields || {},

                    map: null,
                    marker: null,
                    geocoder: null,
                    places: null,
                    autocomplete: null,

                    isLoadingLocation: false,
                    isGeocoding: false,
                    status: '',
                    approximate: false,
                    mapError: null,
                    mapErrorHint: null,

                    get isBusy() {
                        return this.isLoadingLocation || this.isGeocoding;
                    },

                    async initMap() {
                        try {
                            await this.loadGoogleMapsAPI();

                            const location = this.parseState() || this.defaultLocation;

                            this.map = new google.maps.Map(this.$refs.map, {
                                center: location,
                                zoom: this.parseState() ? 17 : this.defaultZoom,
                                mapTypeControl: false,
                                streetViewControl: false,
                                fullscreenControl: true,
                                zoomControl: true,
                                gestureHandling: 'greedy',
                            });

                            this.geocoder = new google.maps.Geocoder();

                            if (google.maps.places) {
                                this.places = new google.maps.places.PlacesService(this.map);
                            }

                            this.initAutocomplete();

                            if (this.parseState()) {
                                this.addMarker(location);
                            }

                            this.map.addListener('click', (event) => {
                                this.updateLocation(event.latLng);
                            });

                            // The state can also change from outside the map —
                            // e.g. pasting a Google Maps link sets it server-side.
                            this.$watch('state', () => {
                                const point = this.parseState();

                                if (!point) return;

                                const current = this.marker?.getPosition();
                                if (current && Math.abs(current.lat() - point.lat) < 1e-6 && Math.abs(current.lng() - point.lng) < 1e-6) {
                                    return;
                                }

                                this.addMarker(point);
                                this.map.setCenter(point);
                                this.map.setZoom(17);
                            });
                        } catch (error) {
                            console.error('Map initialization error:', error);
                            this.fail(
                                'Google Maps could not be loaded.',
                                'Check that GOOGLE_MAPS_API_KEY is set and that Maps JavaScript API, Geocoding API and Places API are enabled with billing active.'
                            );
                        }
                    },

                    parseState() {
                        if (!this.state || typeof this.state !== 'string') return null;
                        const [lat, lng] = this.state.split(',').map(parseFloat);
                        if (Number.isNaN(lat) || Number.isNaN(lng)) return null;
                        return { lat, lng };
                    },

                    initAutocomplete() {
                        if (!this.$refs.searchInput || !google.maps.places) return;

                        try {
                            this.autocomplete = new google.maps.places.Autocomplete(this.$refs.searchInput, {
                                fields: ['address_components', 'geometry', 'name', 'formatted_address'],
                            });

                            this.autocomplete.addListener('place_changed', () => {
                                const place = this.autocomplete.getPlace();

                                if (!place.geometry || !place.geometry.location) {
                                    this.fail('No location details for "' + (place.name || 'that search') + '".', 'Try picking a suggestion from the dropdown.');
                                    return;
                                }

                                this.mapError = null;
                                this.map.setCenter(place.geometry.location);
                                this.map.setZoom(17);
                                // A picked place already carries its own address —
                                // prefer it over a reverse-geocode round trip.
                                this.updateLocation(place.geometry.location, place);
                            });
                        } catch (error) {
                            console.error('Autocomplete initialization error:', error);
                        }
                    },

                    loadGoogleMapsAPI() {
                        window.__mapPickerLoader = window.__mapPickerLoader || this.bootGoogleMaps();

                        return window.__mapPickerLoader;
                    },

                    bootGoogleMaps() {
                        if (typeof google !== 'undefined' && google.maps?.importLibrary) {
                            return this.importLibraries();
                        }

                        return new Promise((resolve, reject) => {
                            if (!this.apiKey) {
                                reject(new Error('Missing Google Maps API key'));
                                return;
                            }

                            // Resolve on Google's own `callback`, not the script's
                            // load event. Under `loading=async` the script fires
                            // onload while it is still a bootstrap stub, so the
                            // map was built a tick too early and died on
                            // "google.maps.Map is not a constructor" — losing the
                            // pin on every shop the field team added.
                            window.__mapPickerReady = () => resolve();

                            const script = document.createElement('script');
                            script.src = 'https://maps.googleapis.com/maps/api/js'
                                + '?key=' + encodeURIComponent(this.apiKey)
                                + '&libraries=places&loading=async&v=weekly'
                                + '&callback=__mapPickerReady';
                            script.async = true;
                            script.onerror = () => reject(new Error('Google Maps script blocked or unreachable'));
                            document.head.appendChild(script);
                        }).then(() => this.importLibraries());
                    },

                    /**
                     * `loading=async` resolves to a bootstrap stub: `google.maps`
                     * exists the moment the script loads, but the constructors do
                     * not until each library is imported. Loading the map straight
                     * off `onload` therefore died on "google.maps.Map is not a
                     * constructor", and every pin was silently lost. Awaiting the
                     * imports here is what makes the map deterministic — they also
                     * populate the classic `google.maps.*` namespace used below.
                     */
                    importLibraries() {
                        return Promise.all([
                            google.maps.importLibrary('maps'),
                            google.maps.importLibrary('marker'),
                            google.maps.importLibrary('geocoding'),
                            // Places only powers search and the landmark lookup —
                            // the map and the pin must still work on a key that
                            // has not enabled it.
                            google.maps.importLibrary('places').catch(() => null),
                        ]);
                    },

                    addMarker(location) {
                        if (this.marker) {
                            this.marker.setMap(null);
                        }

                        this.marker = new google.maps.Marker({
                            position: location,
                            map: this.map,
                            draggable: this.draggable,
                            animation: google.maps.Animation.DROP,
                        });

                        if (this.draggable) {
                            this.marker.addListener('dragend', (event) => {
                                this.updateLocation(event.latLng);
                            });
                        }
                    },

                    async updateLocation(latLng, place = null) {
                        const lat = typeof latLng.lat === 'function' ? latLng.lat() : latLng.lat;
                        const lng = typeof latLng.lng === 'function' ? latLng.lng() : latLng.lng;

                        this.state = lat.toFixed(7) + ',' + lng.toFixed(7);
                        this.approximate = false;
                        this.mapError = null;

                        this.addMarker({ lat, lng });
                        this.map.panTo({ lat, lng });

                        const values = {
                            lat: lat.toFixed(7),
                            lng: lng.toFixed(7),
                            googleUrl: 'https://www.google.com/maps?q=' + lat.toFixed(7) + ',' + lng.toFixed(7),
                        };

                        // Push coordinates immediately so they are never lost if
                        // the address lookup fails.
                        this.commit(values);

                        const details = await this.resolveAddress({ lat, lng }, place);

                        if (details) {
                            this.commit(details);
                        }
                    },

                    /**
                     * Build the address payload for a point.
                     *
                     * Google returns several results per point, ordered most
                     * specific (street address) to least (country). We walk all
                     * of them and take the first non-empty value for each field,
                     * so a point with no exact street still inherits the nearest
                     * known area / city / district rather than leaving it blank.
                     */
                    async resolveAddress(location, place = null) {
                        this.isGeocoding = true;
                        this.status = 'Looking up address…';

                        try {
                            const results = [];

                            if (place && place.address_components) {
                                results.push(place);
                            }

                            const response = await this.geocoder.geocode({ location });
                            results.push(...(response.results || []));

                            if (results.length === 0) {
                                this.status = '';
                                this.fail('No address found for this point.', 'Coordinates were still saved — fill the address fields manually.');
                                return null;
                            }

                            const picked = {
                                address: '', area: '', landmark: '',
                                city: '', state: '', country: '', pincode: '',
                            };

                            let usedFallbackResult = false;

                            results.forEach((result, index) => {
                                const before = JSON.stringify(picked);
                                this.absorb(picked, result);

                                // Anything filled from a result beyond the most
                                // specific one is a nearby approximation.
                                if (index > 0 && JSON.stringify(picked) !== before) {
                                    usedFallbackResult = true;
                                }
                            });

                            if (!picked.address) {
                                picked.address = (results[0].formatted_address || '').split(',')[0] || '';
                                usedFallbackResult = true;
                            }

                            if (!picked.landmark) {
                                const nearby = await this.nearestLandmark(location);
                                if (nearby) {
                                    picked.landmark = nearby;
                                    usedFallbackResult = true;
                                }
                            }

                            this.approximate = usedFallbackResult;
                            this.status = '';

                            // Never overwrite a filled field with an empty string.
                            return Object.fromEntries(
                                Object.entries(picked).filter(([, value]) => value !== '')
                            );
                        } catch (error) {
                            this.status = '';
                            this.reportGeocodeError(error);
                            return null;
                        } finally {
                            this.isGeocoding = false;
                        }
                    },

                    /** Copy any component we do not have yet out of one geocoder result. */
                    absorb(picked, result) {
                        let streetNumber = '';
                        let route = '';

                        (result.address_components || []).forEach((component) => {
                            const types = component.types;
                            const value = component.long_name;

                            if (types.includes('street_number')) streetNumber = value;
                            if (types.includes('route')) route = value;

                            if (!picked.area && (
                                types.includes('sublocality') ||
                                types.includes('sublocality_level_1') ||
                                types.includes('sublocality_level_2') ||
                                types.includes('neighborhood')
                            )) picked.area = value;

                            if (!picked.city && types.includes('locality')) picked.city = value;
                            if (!picked.city && types.includes('administrative_area_level_2')) picked.city = value;
                            if (!picked.state && types.includes('administrative_area_level_1')) picked.state = value;
                            if (!picked.country && types.includes('country')) picked.country = value;
                            if (!picked.pincode && types.includes('postal_code')) picked.pincode = value;

                            if (!picked.landmark && (
                                types.includes('point_of_interest') ||
                                types.includes('establishment') ||
                                types.includes('premise')
                            )) picked.landmark = value;
                        });

                        if (!picked.address) {
                            const street = [streetNumber, route].filter(Boolean).join(' ');
                            if (street) picked.address = street;
                        }

                        // Places results expose the business name directly.
                        if (!picked.landmark && result.name) picked.landmark = result.name;
                    },

                    /** Closest named place, used when the geocoder gives no landmark. */
                    nearestLandmark(location) {
                        if (!this.places) return Promise.resolve(null);

                        return new Promise((resolve) => {
                            this.places.nearbySearch(
                                { location, radius: 150, rankBy: google.maps.places.RankBy.PROMINENCE },
                                (results, status) => {
                                    resolve(
                                        status === google.maps.places.PlacesServiceStatus.OK && results && results[0]
                                            ? results[0].name
                                            : null
                                    );
                                }
                            );
                        });
                    },

                    /**
                     * Write every field in one Livewire round trip.
                     *
                     * Setting them one at a time fired a request per field and
                     * later writes could land on a stale form state.
                     */
                    commit(values) {
                        const entries = Object.entries(values)
                            .filter(([key]) => this.fields[key])
                            .map(([key, value]) => [this.fields[key], value]);

                        if (entries.length === 0) return;

                        try {
                            entries.forEach(([path, value]) => {
                                this.$wire.$set('data.' + path, value, false);
                            });

                            this.$wire.$commit();
                        } catch (error) {
                            console.error('Failed to update form fields:', error);
                            this.fail('Could not write the address into the form.', 'Reload the page and try again.');
                        }
                    },

                    getUserLocation() {
                        if (!navigator.geolocation) {
                            this.fail('Your browser does not support location access.');
                            return;
                        }

                        if (!window.isSecureContext) {
                            this.fail('Location needs a secure (HTTPS) connection.', 'Open this page over https and try again.');
                            return;
                        }

                        this.isLoadingLocation = true;
                        this.mapError = null;
                        this.status = 'Waiting for GPS…';

                        const onSuccess = (position) => {
                            this.isLoadingLocation = false;
                            this.status = '';
                            this.map.setZoom(17);
                            this.updateLocation({
                                lat: position.coords.latitude,
                                lng: position.coords.longitude,
                            });
                        };

                        const onError = (error, isRetry) => {
                            // A high-accuracy fix often times out indoors; fall
                            // back to a coarse fix before giving up.
                            if (!isRetry && error.code === error.TIMEOUT) {
                                this.status = 'Retrying with coarse location…';
                                navigator.geolocation.getCurrentPosition(
                                    onSuccess,
                                    (e) => onError(e, true),
                                    { enableHighAccuracy: false, timeout: 20000, maximumAge: 60000 }
                                );
                                return;
                            }

                            this.isLoadingLocation = false;
                            this.status = '';

                            const messages = {
                                1: ['Location permission was denied.', 'Allow location for this site in your browser settings, then try again.'],
                                2: ['Your location is unavailable right now.', 'Move somewhere with a clearer signal, or tap the map instead.'],
                                3: ['Timed out waiting for your location.', 'Try again, or tap your position on the map.'],
                            };

                            const [message, hint] = messages[error.code] || ['Could not get your location.', 'Tap your position on the map instead.'];
                            this.fail(message, hint);
                        };

                        navigator.geolocation.getCurrentPosition(
                            onSuccess,
                            (error) => onError(error, false),
                            { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 }
                        );
                    },

                    reportGeocodeError(error) {
                        console.error('Geocoding failed:', error);

                        const code = (error && (error.code || error.message)) || '';

                        if (String(code).includes('REQUEST_DENIED')) {
                            this.fail(
                                'Google rejected the address lookup (REQUEST_DENIED).',
                                'Enable the Geocoding API for this key in Google Cloud Console, allow this domain under the key\'s HTTP referrer restrictions, and make sure billing is active.'
                            );
                        } else if (String(code).includes('OVER_QUERY_LIMIT')) {
                            this.fail('Google Maps quota exceeded.', 'Billing is not active on the API key, or the daily quota is used up.');
                        } else if (String(code).includes('ZERO_RESULTS')) {
                            this.fail('No address exists for this point.', 'Coordinates were saved — fill the address fields manually.');
                        } else {
                            this.fail('Address lookup failed.', 'Coordinates were saved. Check the browser console for details.');
                        }
                    },

                    fail(message, hint = null) {
                        this.mapError = message;
                        this.mapErrorHint = hint;
                    },

                    clearLocation() {
                        this.state = null;
                        this.approximate = false;
                        this.mapError = null;
                        this.status = '';

                        if (this.marker) {
                            this.marker.setMap(null);
                            this.marker = null;
                        }

                        this.commit({
                            lat: null, lng: null, address: '', area: '', landmark: '',
                            city: '', state: '', pincode: '', googleUrl: '',
                        });

                        this.map.setCenter(this.defaultLocation);
                        this.map.setZoom(this.defaultZoom);
                    },
                };
            }
        </script>
        @endpush
    @endonce
</x-dynamic-component>
