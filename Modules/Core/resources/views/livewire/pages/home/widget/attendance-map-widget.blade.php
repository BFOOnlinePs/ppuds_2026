<div>
    @if ($this->canView())
        <x-filament::modal
            id="attendance-map-modal"
            icon="heroicon-o-map"
            sticky-header
            width="7xl"
        >
            <x-slot name="heading">
                {{ __('Attendance Map') }}
            </x-slot>

            <x-slot name="description">
                {{ __('Attendance locations for selected dates') }}
            </x-slot>

            @if ($isOpen)
                <div
                    x-data="attendanceMapModal({
                        points: [],
                        center: { lat: 32.2211, lng: 35.2544 },
                        modalId: 'attendance-map-modal',
                        tilesUrl: 'https://tile.openstreetmap.de/{z}/{x}/{y}.png',
                        cssUrl: @js(asset('css/dotswan/filament-map-picker/filament-map-picker-styles.css')),
                        jsUrl: @js(asset('js/dotswan/filament-map-picker/filament-map-picker-scripts.js')),
                        labels: {
                            company: @js(__('Company')),
                            branch: @js(__('Branch Name')),
                            date: @js(__('Date')),
                            checkIn: @js(__('Check In Time')),
                            checkOut: @js(__('Check Out Time')),
                            status: @js(__('Status')),
                            loadError: @js(__('Map could not be loaded')),
                        },
                    })"
                    x-init="$nextTick(async () => { await init($refs.map); $wire.loadAttendancePoints(); })"
                    x-on:attendance-map-points-updated.window="setPoints($event.detail.points, $event.detail.center)"
                    x-on:open-modal.window="if ($event.detail.id === modalId) refreshSize()"
                    class="space-y-4"
                    wire:key="attendance-map-content"
                >
                    <div class="grid gap-3 rounded-lg bg-gray-50 p-3 ring-1 ring-gray-950/5 dark:bg-gray-950/40 dark:ring-white/10 sm:grid-cols-[1fr_1fr_auto_auto] sm:items-end">
                        <label class="space-y-1">
                            <span class="text-xs font-medium text-gray-600 dark:text-gray-400">{{ __('From Date') }}</span>
                            <input
                                type="date"
                                wire:model="fromDate"
                                class="w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                            >
                        </label>

                        <label class="space-y-1">
                            <span class="text-xs font-medium text-gray-600 dark:text-gray-400">{{ __('Until Date') }}</span>
                            <input
                                type="date"
                                wire:model="toDate"
                                class="w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                            >
                        </label>

                        <x-filament::button
                            type="button"
                            icon="heroicon-o-funnel"
                            wire:click="applyFilters"
                            wire:loading.attr="disabled"
                            wire:target="applyFilters"
                        >
                            {{ __('Apply Filter') }}
                        </x-filament::button>

                        <x-filament::button
                            type="button"
                            color="gray"
                            icon="heroicon-o-arrows-pointing-out"
                            x-on:click="toggleFullscreen()"
                        >
                            {{ __('Fullscreen Map') }}
                        </x-filament::button>
                    </div>

                    <div class="flex flex-wrap items-center gap-4 text-xs font-medium text-gray-600 dark:text-gray-300">
                        <span>
                            {{ __('Shown Points') }}:
                            <span x-text="points.length"></span>
                        </span>
                        <span class="inline-flex items-center gap-2">
                            <span class="h-3 w-3 rounded-full bg-red-600 ring-2 ring-white dark:ring-gray-900"></span>
                            {{ __('Checked In Only') }}
                        </span>
                        <span class="inline-flex items-center gap-2">
                            <span class="h-3 w-3 rounded-full bg-green-600 ring-2 ring-white dark:ring-gray-900"></span>
                            {{ __('Checked In And Out') }}
                        </span>
                    </div>

                    <div class="attendance-map-shell relative overflow-hidden rounded-lg bg-gray-100 ring-1 ring-gray-950/10 dark:bg-gray-950 dark:ring-white/10" x-ref="mapShell">
                        <div
                            x-ref="map"
                            wire:ignore
                            class="attendance-map-canvas h-[62vh] min-h-[420px] w-full sm:h-[68vh]"
                        ></div>

                        <div
                            x-cloak
                            x-show="error"
                            class="absolute inset-x-4 top-4 rounded-lg bg-danger-50 px-4 py-3 text-sm font-medium text-danger-700 ring-1 ring-danger-600/20 dark:bg-danger-400/10 dark:text-danger-300 dark:ring-danger-400/20"
                            x-text="error"
                        ></div>

                        <div
                            x-cloak
                            x-show="! error && points.length === 0"
                            class="absolute bottom-4 left-4 right-4 rounded-lg bg-white/95 px-4 py-3 text-sm font-medium text-gray-600 shadow-sm ring-1 ring-gray-950/10 dark:bg-gray-900/95 dark:text-gray-300 dark:ring-white/10"
                        >
                            {{ __('No attendance locations found for selected dates') }}
                        </div>
                    </div>
                </div>
            @endif
        </x-filament::modal>

        @once
            <style>
                .attendance-map-point {
                    filter: drop-shadow(0 8px 14px rgba(15, 23, 42, 0.24));
                }

                .attendance-map-popup {
                    min-width: 220px;
                    color: #111827;
                    font-family: inherit;
                }

                .attendance-map-popup-title {
                    margin-bottom: 8px;
                    font-weight: 700;
                }

                .attendance-map-popup-title a {
                    color: #2563eb;
                    text-decoration: none;
                }

                .attendance-map-popup-title a:hover {
                    text-decoration: underline;
                }

                .attendance-map-popup-row {
                    display: flex;
                    justify-content: space-between;
                    gap: 12px;
                    padding: 3px 0;
                    font-size: 12px;
                }

                .attendance-map-popup-row span {
                    color: #EE4B2B;
                }

                .attendance-map-popup-row strong {
                    max-width: 140px;
                    overflow: hidden;
                    text-align: end;
                    text-overflow: ellipsis;
                    white-space: nowrap;
                }

                .attendance-map-shell:fullscreen {
                    background: #ffffff;
                    padding: 16px;
                }

                .attendance-map-shell:fullscreen .attendance-map-canvas {
                    height: calc(100vh - 32px) !important;
                }
            </style>
        @endonce

        @script
            <script>
                Alpine.data('attendanceMapModal', (config) => ({
                    map: null,
                    markerLayer: null,
                    markerRenderer: null,
                    renderFrame: null,
                    renderToken: 0,
                    modalId: config.modalId,
                    points: config.points || [],
                    center: config.center || { lat: 32.2211, lng: 35.2544 },
                    error: null,

                    async init(element) {
                        let leaflet = await this.waitForLeaflet(10);

                        if (! leaflet) {
                            await this.loadMapAssets();
                            leaflet = await this.waitForLeaflet(40);
                        }

                        if (! leaflet) {
                            this.error = config.labels.loadError;
                            return;
                        }

                        this.map = leaflet.map(element, {
                            center: [this.center.lat, this.center.lng],
                            zoom: 9,
                            zoomControl: true,
                            scrollWheelZoom: true,
                            preferCanvas: true,
                        });

                        leaflet.tileLayer(config.tilesUrl, {
                            minZoom: 3,
                            maxZoom: 20,
                            attribution: '&copy; OpenStreetMap',
                        }).addTo(this.map);

                        this.markerRenderer = leaflet.canvas({ padding: 0.5 });
                        this.markerLayer = leaflet.layerGroup().addTo(this.map);
                        this.setPoints(this.points, this.center);
                        this.refreshSize();
                    },

                    async waitForLeaflet(maxAttempts) {
                        for (let attempt = 0; attempt < maxAttempts; attempt++) {
                            const leaflet = window.L || window.leaflet;

                            if (leaflet?.map && leaflet?.tileLayer) {
                                return leaflet;
                            }

                            await new Promise((resolve) => setTimeout(resolve, 100));
                        }

                        return null;
                    },

                    async loadMapAssets() {
                        if (! document.querySelector('[data-attendance-map-css]')) {
                            const link = document.createElement('link');
                            link.rel = 'stylesheet';
                            link.href = config.cssUrl;
                            link.dataset.attendanceMapCss = 'true';
                            document.head.appendChild(link);
                        }

                        if ((window.L || window.leaflet)?.map) {
                            return;
                        }

                        if (! window.attendanceMapScriptPromise) {
                            window.attendanceMapScriptPromise = new Promise((resolve, reject) => {
                                const script = document.createElement('script');
                                script.src = config.jsUrl;
                                script.dataset.attendanceMapJs = 'true';
                                script.onload = resolve;
                                script.onerror = reject;
                                document.body.appendChild(script);
                            });
                        }

                        try {
                            await window.attendanceMapScriptPromise;
                        } catch (error) {
                            console.error(error);
                        }
                    },

                    setPoints(points, center) {
                        this.points = Array.isArray(points) ? points : [];
                        this.center = center || this.center;

                        if (! this.map || ! this.markerLayer) {
                            return;
                        }

                        const leaflet = window.L || window.leaflet;
                        this.renderToken += 1;
                        const token = this.renderToken;

                        if (this.renderFrame) {
                            cancelAnimationFrame(this.renderFrame);
                            this.renderFrame = null;
                        }

                        this.markerLayer.clearLayers();

                        const bounds = leaflet.latLngBounds([]);
                        const batchSize = 500;
                        let index = 0;
                        let added = 0;

                        const finish = () => {
                            if (token !== this.renderToken) {
                                return;
                            }

                            if (added && bounds.isValid()) {
                                this.map.fitBounds(bounds.pad(0.35), { maxZoom: 12, animate: false });
                            } else {
                                this.map.setView([this.center.lat, this.center.lng], 9, { animate: false });
                            }

                            this.refreshSize();
                        };

                        const renderBatch = () => {
                            if (token !== this.renderToken) {
                                return;
                            }

                            const end = Math.min(index + batchSize, this.points.length);

                            for (; index < end; index++) {
                                const latLng = this.addAttendancePoint(leaflet, this.points[index]);

                                if (latLng) {
                                    bounds.extend(latLng);
                                    added++;
                                }
                            }

                            if (index < this.points.length) {
                                this.renderFrame = requestAnimationFrame(renderBatch);
                                return;
                            }

                            this.renderFrame = null;
                            finish();
                        };

                        renderBatch();
                    },

                    addAttendancePoint(leaflet, point) {
                        const lat = Number(point.lat);
                        const lng = Number(point.lng);

                        if (! Number.isFinite(lat) || ! Number.isFinite(lng)) {
                            return null;
                        }

                        const latLng = leaflet.latLng(lat, lng);
                        const color = point.color || '#dc2626';

                        const marker = leaflet.circleMarker(latLng, {
                            className: 'attendance-map-point',
                            renderer: this.markerRenderer,
                            radius: 7,
                            color: '#ffffff',
                            weight: 2,
                            opacity: 1,
                            fillColor: color,
                            fillOpacity: 0.95,
                        })
                            .addTo(this.markerLayer);

                        marker.on('click', () => {
                            if (! marker.getPopup()) {
                                marker.bindPopup(this.popupContent(point));
                            }

                            marker.openPopup();
                        });

                        return latLng;
                    },

                    popupContent(point) {
                        const student = this.escape(point.student || '-');
                        const title = point.student_url
                            ? `<a href="${this.escape(point.student_url)}">${student}</a>`
                            : student;

                        const row = (label, value) => `
                            <div class="attendance-map-popup-row">
                                <span>${this.escape(label)}</span>
                                <strong>${this.escape(value || '-')}</strong>
                            </div>
                        `;

                        return `
                            <div class="attendance-map-popup">
                                <div class="attendance-map-popup-title">${title}</div>
                                ${row(config.labels.company, point.company)}
                                ${row(config.labels.branch, point.branch)}
                                ${row(config.labels.date, point.date)}
                                ${row(config.labels.checkIn, point.check_in)}
                                ${row(config.labels.checkOut, point.check_out)}
                                ${row(config.labels.status, point.status)}
                            </div>
                        `;
                    },

                    escape(value) {
                        return String(value)
                            .replaceAll('&', '&amp;')
                            .replaceAll('<', '&lt;')
                            .replaceAll('>', '&gt;')
                            .replaceAll('"', '&quot;')
                            .replaceAll("'", '&#039;');
                    },

                    refreshSize() {
                        setTimeout(() => this.map?.invalidateSize(true), 180);
                    },

                    async toggleFullscreen() {
                        const target = this.$refs.mapShell;

                        try {
                            if (! document.fullscreenElement) {
                                await target.requestFullscreen();
                            } else {
                                await document.exitFullscreen();
                            }
                        } catch (error) {
                            console.error(error);
                        }

                        this.refreshSize();
                    },
                }));
            </script>
        @endscript
    @endif
</div>
