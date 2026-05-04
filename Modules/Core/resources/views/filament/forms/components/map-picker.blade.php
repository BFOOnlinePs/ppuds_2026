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

            if (config.fixedLocationMarker) {
                config.clickable = true;
            }

            const picker = mapPicker($wire, config);

            if (config.fixedLocationMarker) {
                const createMap = picker.createMap;

                picker.createMap = function (element) {
                    createMap.call(this, element);
                    this.map?.off('click');
                };

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

            return picker;
        })()"
        x-init="async () => {
            do {
                await (new Promise(resolve => setTimeout(resolve, 100)));
            } while (!$refs.map);

            attach($refs.map);
        }"
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
