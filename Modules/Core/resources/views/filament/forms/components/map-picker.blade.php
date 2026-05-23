@once
    <link
        rel="stylesheet"
        href="{{ asset('css/dotswan/filament-map-picker/filament-map-picker-styles.css') }}"
        data-filament-map-picker-css
    />
    <script
        src="{{ asset('js/dotswan/filament-map-picker/filament-map-picker-scripts.js') }}"
        data-filament-map-picker-js
    ></script>
@endonce

<x-filament-forms::field-wrapper
    :id="$getId()"
    :label="$getLabel()"
    :label-sr-only="$isLabelHidden()"
    :helper-text="$getHelperText()"
    :hint="$getHint()"
    :required="$isRequired()"
    :state-path="$getStatePath()"
>
    <div
        x-data="(() => {
            const config = {{ $getMapConfig() }};
            const cssUrl = @js(asset('css/dotswan/filament-map-picker/filament-map-picker-styles.css'));
            const jsUrl = @js(asset('js/dotswan/filament-map-picker/filament-map-picker-scripts.js'));

            if (config.fixedLocationMarker) {
                config.clickable = true;
            }

            return {
                picker: null,

                async initMap() {
                    await this.ensureMapAssets();
                    await this.waitForMapPicker();

                    if (! window.mapPicker) {
                        return;
                    }

                    const picker = window.mapPicker($wire, config);
                    this.preparePicker(picker);
                    this.picker = picker;

                    this.$nextTick(() => this.picker.attach(this.$refs.map));
                },

                async ensureMapAssets() {
                    if (! document.querySelector('[data-filament-map-picker-css]')) {
                        const link = document.createElement('link');
                        link.rel = 'stylesheet';
                        link.href = cssUrl;
                        link.dataset.filamentMapPickerCss = 'true';
                        document.head.appendChild(link);
                    }

                    if (window.mapPicker || document.querySelector('[data-filament-map-picker-js]')) {
                        return;
                    }

                    await new Promise((resolve) => {
                        const script = document.createElement('script');
                        script.src = jsUrl;
                        script.dataset.filamentMapPickerJs = 'true';
                        script.onload = resolve;
                        script.onerror = resolve;
                        document.body.appendChild(script);
                    });
                },

                async waitForMapPicker() {
                    for (let attempt = 0; attempt < 80; attempt++) {
                        if (window.mapPicker) {
                            return;
                        }

                        if (attempt === 10 && document.readyState !== 'loading') {
                            document.dispatchEvent(new Event('DOMContentLoaded'));
                        }

                        await new Promise((resolve) => setTimeout(resolve, 100));
                    }
                },

                preparePicker(picker) {
                    const createMap = picker.createMap;
                    const installTileFallback = (mapPicker) => {
                        if (! mapPicker.tile || mapPicker.tile.__fallbackAttached) {
                            return;
                        }

                        mapPicker.tile.__fallbackAttached = true;
                        mapPicker.tile.on('tileerror', () => {
                            if (mapPicker.__fallbackApplied || ! mapPicker.map || ! config.fallbackTilesUrl) {
                                return;
                            }

                            const leaflet = window.L || window.leaflet;

                            if (! leaflet) {
                                return;
                            }

                            mapPicker.__fallbackApplied = true;
                            mapPicker.map.removeLayer(mapPicker.tile);
                            mapPicker.tile = leaflet.tileLayer(config.fallbackTilesUrl, {
                                attribution: config.attribution,
                                minZoom: config.minZoom,
                                maxZoom: config.maxZoom,
                                tileSize: 256,
                                zoomOffset: 0,
                                detectRetina: false,
                            }).addTo(mapPicker.map);

                            setTimeout(() => mapPicker.map?.invalidateSize(true), 120);
                        });
                    };

                    picker.createMap = function (element) {
                        createMap.call(this, element);

                        if (config.fixedLocationMarker) {
                            this.map?.off('click');
                        }

                        installTileFallback(this);
                    };

                    if (config.fixedLocationMarker) {
                        picker.fetchCurrentLocation = function () {
                            if (! ('geolocation' in navigator)) {
                                alert('Geolocation is not supported by this browser.');
                                return;
                            }

                            navigator.geolocation.getCurrentPosition((position) => {
                                const leaflet = window.L || window.leaflet;
                                const currentPosition = new leaflet.LatLng(position.coords.latitude, position.coords.longitude);

                                this.setCoordinates(currentPosition);
                                this.map.flyTo(currentPosition, config.controls.zoom);
                                this.updateMarker();
                            }, (error) => {
                                console.error('Error fetching current location:', error);
                            }, {
                                enableHighAccuracy: true,
                            });
                        };
                    }

                    picker.attach = function (element) {
                        const refresh = () => setTimeout(() => this.map?.invalidateSize(true), 120);
                        const isVisible = () => element.offsetWidth > 0 && element.offsetHeight > 0;
                        const createWhenVisible = () => {
                            if (! isVisible()) {
                                return;
                            }

                            if (! this.map) {
                                this.createMap(element);
                            }

                            refresh();
                        };

                        createWhenVisible();

                        const observer = new IntersectionObserver((entries) => {
                            entries.forEach((entry) => {
                                if (entry.isIntersecting) {
                                    createWhenVisible();
                                }
                            });
                        }, { threshold: 0 });

                        observer.observe(element);
                        setTimeout(createWhenVisible, 250);
                        setTimeout(createWhenVisible, 650);
                    };
                },
            };
        })()"
        x-init="initMap()"
        wire:ignore
    >
        <div
            x-ref="map"
            class="w-full"
            style="min-height: 30vh; {{ $getExtraStyle() }}"
        ></div>
        <input type="text" id="{{ $getId() }}_fmrest" style="display:none"/>
    </div>
</x-filament-forms::field-wrapper>
