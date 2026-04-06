@php
    $apiKey = config('services.google_maps.api_key');
    $mapHeight = $getMapHeight();
    $defaultLocation = $getDefaultLocation();
    $defaultZoom = $getDefaultZoom();
    $isDraggable = $isDraggable();
    $showGeolocation = $getShowGeolocationButton();
    $latField = $getLatitudeField();
    $lngField = $getLongitudeField();
    $addressField = $getAddressField();
    $areaField = $getAreaField();
    $landmarkField = $getLandmarkField();
    $cityField = $getCityField();
    $stateField = $getStateField();
    $countryField = $getCountryField();
    $pincodeField = $getPincodeField();
    $statePath = $getStatePath();
@endphp

<x-dynamic-component
    :component="$getFieldWrapperView()"
    :field="$field"
>
    <div
        x-data="mapPickerComponent({
            state: $wire.{{ $applyStateBindingModifiers("\$entangle('{$statePath}')") }},
            apiKey: '{{ $apiKey }}',
            defaultLocation: {{ json_encode($defaultLocation) }},
            defaultZoom: {{ $defaultZoom }},
            draggable: {{ $isDraggable ? 'true' : 'false' }},
            showGeolocation: {{ $showGeolocation ? 'true' : 'false' }},
            latField: '{{ $latField }}',
            lngField: '{{ $lngField }}',
            addressField: '{{ $addressField }}',
            areaField: '{{ $areaField }}',
            landmarkField: '{{ $landmarkField }}',
            cityField: '{{ $cityField }}',
            stateField: '{{ $stateField }}',
            countryField: '{{ $countryField }}',
            pincodeField: '{{ $pincodeField }}'
        })"
        x-init="initMap()"
        wire:ignore
        class="w-full"
    >
        <!-- Error Message -->
        <div x-show="mapError" x-cloak class="mb-3 p-4 bg-danger-50 border border-danger-200 rounded-lg text-danger-700 dark:bg-danger-900/20 dark:border-danger-800 dark:text-danger-400">
            <div class="flex items-start gap-2">
                <svg class="h-5 w-5 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                </svg>
                <div>
                    <p class="font-semibold">Google Maps Failed to Load</p>
                    <p class="text-sm mt-1" x-text="mapError"></p>
                </div>
            </div>
        </div>

        <!-- Search Bar -->
        <div class="mb-3">
            <div class="relative">
                <input
                    x-ref="searchInput"
                    type="text"
                    placeholder="Search for a location..."
                    class="w-full px-4 py-2 pr-10 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-800 dark:text-white"
                />
                <svg class="absolute right-3 top-1/2 -translate-y-1/2 h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
            </div>
        </div>

        <!-- Geolocation Button -->
        <div x-show="showGeolocation" class="mb-3 flex gap-2">
            <button
                type="button"
                @click="getUserLocation()"
                class="inline-flex items-center gap-2 px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 disabled:opacity-50"
                :disabled="isLoadingLocation"
            >
                <svg x-show="!isLoadingLocation" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
                <svg x-show="isLoadingLocation" class="animate-spin h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span x-text="isLoadingLocation ? 'Getting location...' : 'Use My Location'"></span>
            </button>

            <button
                type="button"
                @click="clearLocation()"
                class="inline-flex items-center gap-2 px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 focus:ring-2 focus:ring-gray-500 focus:ring-offset-2"
            >
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
                Clear
            </button>
        </div>

        <!-- Map Container -->
        <div
            x-ref="map"
            style="height: {{ $mapHeight ?? '400px' }};"
            class="w-full rounded-lg border border-gray-300 dark:border-gray-600"
        ></div>

        <!-- Coordinates Display -->
        <div class="mt-3 text-sm text-gray-600 dark:text-gray-400">
            <span x-show="state">
                <strong>Selected:</strong>
                Lat: <span x-text="state ? state.split(',')[0] : ''"></span>,
                Lng: <span x-text="state ? state.split(',')[1] : ''"></span>
            </span>
            <span x-show="!state" class="text-gray-400">
                Click on the map or use your location to set coordinates
            </span>
        </div>
    </div>

    @once
        @push('scripts')
        <script>
            function mapPickerComponent(config) {
                return {
                    state: config.state,
                    apiKey: config.apiKey,
                    defaultLocation: config.defaultLocation,
                    defaultZoom: config.defaultZoom,
                    draggable: config.draggable,
                    showGeolocation: config.showGeolocation,
                    latField: config.latField,
                    lngField: config.lngField,
                    addressField: config.addressField,
                    areaField: config.areaField,
                    landmarkField: config.landmarkField,
                    cityField: config.cityField,
                    stateField: config.stateField,
                    countryField: config.countryField,
                    pincodeField: config.pincodeField,
                    map: null,
                    marker: null,
                    geocoder: null,
                    autocomplete: null,
                    isLoadingLocation: false,
                    mapError: null,

                    async initMap() {
                        try {
                            if (typeof google === 'undefined') {
                                await this.loadGoogleMapsAPI();
                            }

                            const location = this.state
                                ? {
                                    lat: parseFloat(this.state.split(',')[0]),
                                    lng: parseFloat(this.state.split(',')[1])
                                  }
                                : this.defaultLocation;

                            this.map = new google.maps.Map(this.$refs.map, {
                                center: location,
                                zoom: this.defaultZoom,
                                mapTypeControl: true,
                                streetViewControl: true,
                                fullscreenControl: true,
                            });

                            this.geocoder = new google.maps.Geocoder();

                            // Initialize Places Autocomplete
                            this.initAutocomplete();

                            if (this.state) {
                                this.addMarker(location);
                            }

                            this.map.addListener('click', (event) => {
                                this.updateLocation(event.latLng);
                            });
                        } catch (error) {
                            console.error('Map initialization error:', error);
                            this.mapError = 'Failed to initialize map. Please check your Google Maps API key and ensure Maps JavaScript API, Geocoding API, and Places API are enabled in Google Cloud Console.';
                        }
                    },

                    initAutocomplete() {
                        if (!this.$refs.searchInput) return;

                        try {
                            this.autocomplete = new google.maps.places.Autocomplete(this.$refs.searchInput, {
                                fields: ['address_components', 'geometry', 'name', 'formatted_address'],
                            });

                            this.autocomplete.addListener('place_changed', () => {
                                const place = this.autocomplete.getPlace();

                                if (!place.geometry || !place.geometry.location) {
                                    this.mapError = 'No details available for: ' + place.name;
                                    return;
                                }

                                this.mapError = null;
                                this.updateLocation(place.geometry.location);
                                this.map.setCenter(place.geometry.location);
                                this.map.setZoom(16);
                            });
                        } catch (error) {
                            console.error('Autocomplete initialization error:', error);
                        }
                    },

                    async loadGoogleMapsAPI() {
                        return new Promise((resolve, reject) => {
                            if (typeof google !== 'undefined') {
                                resolve();
                                return;
                            }

                            const script = document.createElement('script');
                            script.src = `https://maps.googleapis.com/maps/api/js?key=${this.apiKey}&libraries=places`;
                            script.async = true;
                            script.defer = true;
                            script.onload = () => {
                                if (typeof google === 'undefined') {
                                    reject(new Error('Google Maps API failed to load'));
                                } else {
                                    resolve();
                                }
                            };
                            script.onerror = (error) => {
                                this.mapError = 'Failed to load Google Maps. Please verify the API key is correct and has the required APIs enabled (Maps JavaScript API, Geocoding API, Places API).';
                                reject(error);
                            };
                            document.head.appendChild(script);
                        });
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

                    updateLocation(latLng) {
                        const lat = typeof latLng.lat === 'function' ? latLng.lat() : latLng.lat;
                        const lng = typeof latLng.lng === 'function' ? latLng.lng() : latLng.lng;

                        this.state = `${lat.toFixed(7)},${lng.toFixed(7)}`;

                        this.addMarker({ lat, lng });
                        this.map.panTo(latLng);

                        try {
                            if (this.latField) {
                                this.$wire.$set('data.' + this.latField, lat.toFixed(7));
                            }
                            if (this.lngField) {
                                this.$wire.$set('data.' + this.lngField, lng.toFixed(7));
                            }
                        } catch (error) {
                            console.error('Failed to update coordinates:', error);
                        }

                        this.reverseGeocode(latLng);
                    },

                    async reverseGeocode(latLng) {
                        try {
                            const result = await this.geocoder.geocode({ location: latLng });

                            if (result.results[0]) {
                                const addressComponents = result.results[0].address_components;
                                const formattedAddress = result.results[0].formatted_address;

                                let streetNumber = '';
                                let route = '';
                                let area = '';
                                let landmark = '';
                                let city = '';
                                let state = '';
                                let country = '';
                                let pincode = '';

                                addressComponents.forEach(component => {
                                    const types = component.types;

                                    if (types.includes('street_number')) {
                                        streetNumber = component.long_name;
                                    }
                                    if (types.includes('route')) {
                                        route = component.long_name;
                                    }
                                    if (types.includes('sublocality') || types.includes('sublocality_level_1') || types.includes('sublocality_level_2')) {
                                        if (!area) area = component.long_name;
                                    }
                                    if (types.includes('neighborhood')) {
                                        if (!area) area = component.long_name;
                                    }
                                    if (types.includes('locality')) {
                                        city = component.long_name;
                                    }
                                    if (types.includes('administrative_area_level_2')) {
                                        if (!city) city = component.long_name;
                                    }
                                    if (types.includes('administrative_area_level_1')) {
                                        state = component.long_name;
                                    }
                                    if (types.includes('country')) {
                                        country = component.long_name;
                                    }
                                    if (types.includes('postal_code')) {
                                        pincode = component.long_name;
                                    }
                                    if (types.includes('point_of_interest') || types.includes('establishment')) {
                                        if (!landmark) landmark = component.long_name;
                                    }
                                });

                                const addressParts = [];
                                if (streetNumber) addressParts.push(streetNumber);
                                if (route) addressParts.push(route);
                                const address = addressParts.join(' ') || formattedAddress.split(',')[0];

                                try {
                                    if (this.addressField && address) {
                                        this.$wire.$set('data.' + this.addressField, address);
                                    }
                                    if (this.areaField && area) {
                                        this.$wire.$set('data.' + this.areaField, area);
                                    }
                                    if (this.landmarkField && landmark) {
                                        this.$wire.$set('data.' + this.landmarkField, landmark);
                                    }
                                    if (this.cityField && city) {
                                        this.$wire.$set('data.' + this.cityField, city);
                                    }
                                    if (this.stateField && state) {
                                        this.$wire.$set('data.' + this.stateField, state);
                                    }
                                    if (this.countryField && country) {
                                        this.$wire.$set('data.' + this.countryField, country);
                                    }
                                    if (this.pincodeField && pincode) {
                                        this.$wire.$set('data.' + this.pincodeField, pincode);
                                    }
                                } catch (error) {
                                    console.error('Failed to update fields:', error);
                                }
                            }
                        } catch (error) {
                            console.error('Geocoding failed:', error);
                        }
                    },

                    getUserLocation() {
                        if (!navigator.geolocation) {
                            alert('Geolocation is not supported by your browser');
                            return;
                        }

                        this.isLoadingLocation = true;

                        navigator.geolocation.getCurrentPosition(
                            (position) => {
                                const latLng = {
                                    lat: position.coords.latitude,
                                    lng: position.coords.longitude
                                };

                                this.updateLocation(latLng);
                                this.map.setZoom(16);
                                this.isLoadingLocation = false;
                            },
                            (error) => {
                                console.error('Geolocation error:', error);
                                alert('Unable to get your location. Please check browser permissions.');
                                this.isLoadingLocation = false;
                            },
                            {
                                enableHighAccuracy: true,
                                timeout: 5000,
                                maximumAge: 0
                            }
                        );
                    },

                    clearLocation() {
                        this.state = null;

                        if (this.marker) {
                            this.marker.setMap(null);
                            this.marker = null;
                        }

                        if (this.latField) this.$wire.$set('data.' + this.latField, null);
                        if (this.lngField) this.$wire.$set('data.' + this.lngField, null);
                        if (this.addressField) this.$wire.$set('data.' + this.addressField, '');
                        if (this.cityField) this.$wire.$set('data.' + this.cityField, '');
                        if (this.stateField) this.$wire.$set('data.' + this.stateField, '');
                        if (this.pincodeField) this.$wire.$set('data.' + this.pincodeField, '');

                        this.map.setCenter(this.defaultLocation);
                        this.map.setZoom(this.defaultZoom);
                    },
                };
            }
        </script>
        @endpush
    @endonce
</x-dynamic-component>
