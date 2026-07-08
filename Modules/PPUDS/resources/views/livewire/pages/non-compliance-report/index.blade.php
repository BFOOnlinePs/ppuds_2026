@php
    $records = $this->records();
@endphp

<div
    class="space-y-6"
    x-data="nonComplianceLocationMap({
        tilesUrl: 'https://tile.openstreetmap.de/{z}/{x}/{y}.png',
        cssUrl: @js(asset('css/dotswan/filament-map-picker/filament-map-picker-styles.css')),
        jsUrl: @js(asset('js/dotswan/filament-map-picker/filament-map-picker-scripts.js')),
        labels: {
            companyLocation: @js(__('Company Location')),
            studentCheckInLocation: @js(__('Student Check In Location')),
            company: @js(__('Company')),
            branch: @js(__('Branch')),
            date: @js(__('Date')),
            checkIn: @js(__('Check In Time')),
            distance: @js(__('Distance From Work')),
            latitude: @js(__('Latitude')),
            longitude: @js(__('Longitude')),
            loadError: @js(__('Map could not be loaded')),
        },
    })"
    x-on:keydown.escape.window="closeLocationMap()"
>
    {{ $this->form }}

    @if ($records->count() > 0)
        <div class="grid grid-cols-1 gap-4 2xl:grid-cols-2">
            @foreach ($records as $studentCompany)
                @php
                    $summary = $this->summary($studentCompany);
                    $absenceDates = $summary['absence_dates'] ?? [];
                    $lateAttendances = $summary['late_attendances'] ?? [];
                    $outsideWorkRangeAttendances = $summary['outside_work_range_attendances'] ?? [];
                @endphp

                <article class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
                    <div class="border-b border-gray-100 px-5 py-4 dark:border-gray-800">
                        <div class="space-y-4">
                            <div>
                                <h3 class="whitespace-normal break-words text-lg font-semibold leading-7 text-gray-950 dark:text-white">
                                    {{ $studentCompany->student?->name ?? __('Unknown Student') }}
                                </h3>

                                <div class="mt-3 grid grid-cols-1 gap-2 text-sm text-gray-600 dark:text-gray-300 sm:grid-cols-3">
                                    <div class="min-w-0">
                                        <div class="text-xs text-gray-400 dark:text-gray-500">{{ __('Student Number') }}</div>
                                        <div class="break-words font-medium text-gray-700 dark:text-gray-200">
                                            {{ $studentCompany->student?->studentProfile?->student_number ?? '-' }}
                                        </div>
                                    </div>
                                    <div class="min-w-0">
                                        <div class="text-xs text-gray-400 dark:text-gray-500">{{ __('Company') }}</div>
                                        <div class="break-words font-medium text-gray-700 dark:text-gray-200">
                                            {{ $studentCompany->company?->name ?? '-' }}
                                        </div>
                                    </div>
                                    <div class="min-w-0">
                                        <div class="text-xs text-gray-400 dark:text-gray-500">{{ __('Branch') }}</div>
                                        <div class="break-words font-medium text-gray-700 dark:text-gray-200">
                                            {{ $studentCompany->branch?->name ?? '-' }}
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 gap-2 sm:grid-cols-3">
                                <span class="inline-flex items-center justify-center rounded-md bg-red-50 px-3 py-1.5 text-xs font-medium text-red-700 ring-1 ring-inset ring-red-600/10 dark:bg-red-500/10 dark:text-red-300 dark:ring-red-500/20">
                                    {{ __('Total Non Compliance Days') }}: {{ (int) ($summary['total_non_compliance_days'] ?? 0) }}
                                </span>
                                <span class="inline-flex items-center justify-center rounded-md bg-amber-50 px-3 py-1.5 text-xs font-medium text-amber-700 ring-1 ring-inset ring-amber-600/10 dark:bg-amber-500/10 dark:text-amber-300 dark:ring-amber-500/20">
                                    {{ __('Total Late Hours') }}: {{ number_format((float) ($summary['total_late_hours'] ?? 0), 2) }}
                                </span>
                                <span class="inline-flex items-center justify-center rounded-md bg-blue-50 px-3 py-1.5 text-xs font-medium text-blue-700 ring-1 ring-inset ring-blue-600/10 dark:bg-blue-500/10 dark:text-blue-300 dark:ring-blue-500/20">
                                    {{ __('Outside Work Range') }}: {{ (int) ($summary['outside_work_range_days'] ?? 0) }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-5 px-5 py-4">
                        @if ((int) ($summary['outside_work_range_days'] ?? 0) > 0)
                            <section class="space-y-3">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <h4 class="text-sm font-semibold text-gray-900 dark:text-white">
                                        {{ __('Outside Work Range') }}
                                    </h4>
                                    <span class="inline-flex items-center rounded-md bg-blue-50 px-2 py-1 text-xs font-medium text-blue-700 ring-1 ring-inset ring-blue-600/10 dark:bg-blue-500/10 dark:text-blue-300 dark:ring-blue-500/20">
                                        {{ __('Allowed Range (meters)') }}: {{ number_format((int) ($summary['outside_work_range_distance_meters'] ?? 200)) }}
                                    </span>
                                </div>

                                <div class="max-h-80 space-y-2 overflow-y-auto">
                                    @foreach ($outsideWorkRangeAttendances as $attendance)
                                        @php
                                            $attendanceMapLocation = [
                                                'student' => $studentCompany->student?->name ?? '-',
                                                'company' => $studentCompany->company?->name ?? '-',
                                                'branch' => $studentCompany->branch?->name ?? '-',
                                                'date' => $attendance['date'] ?? '-',
                                                'check_in' => $attendance['check_in'] ?? '-',
                                                'distance_label' => $attendance['distance_label'] ?? '-',
                                                'attendance_latitude' => $attendance['attendance_latitude'] ?? null,
                                                'attendance_longitude' => $attendance['attendance_longitude'] ?? null,
                                                'branch_latitude' => $attendance['branch_latitude'] ?? null,
                                                'branch_longitude' => $attendance['branch_longitude'] ?? null,
                                            ];
                                            $hasMapCoordinates = filled($attendanceMapLocation['attendance_latitude'])
                                                && filled($attendanceMapLocation['attendance_longitude'])
                                                && filled($attendanceMapLocation['branch_latitude'])
                                                && filled($attendanceMapLocation['branch_longitude']);
                                        @endphp

                                        <div class="rounded-md border border-blue-100 bg-blue-50 px-3 py-2 text-sm dark:border-blue-500/20 dark:bg-blue-500/10">
                                            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                                <div class="font-medium text-blue-900 dark:text-blue-100">
                                                    {{ $attendance['date'] ?? '-' }}
                                                </div>

                                                <div class="flex w-full flex-wrap items-center gap-2 sm:w-auto sm:justify-end">
                                                    <div class="inline-flex w-fit items-center rounded-md bg-white px-2 py-1 text-xs font-semibold text-blue-800 ring-1 ring-inset ring-blue-600/10 dark:bg-blue-500/10 dark:text-blue-200 dark:ring-blue-500/20">
                                                        {{ __('Distance From Work') }}: {{ $attendance['distance_label'] ?? '-' }}
                                                    </div>

                                                    @if ($hasMapCoordinates)
                                                        <button
                                                            type="button"
                                                            x-on:click="openLocationMap(@js($attendanceMapLocation))"
                                                            class="inline-flex h-8 items-center gap-1.5 rounded-md bg-primary-600 px-2.5 text-xs font-semibold text-white shadow-sm transition hover:bg-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 dark:focus:ring-offset-gray-900"
                                                        >
                                                            @svg('heroicon-o-map-pin', 'h-4 w-4')
                                                            <span>{{ __('View Map') }}</span>
                                                        </button>
                                                    @endif
                                                </div>
                                            </div>

                                            <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-blue-800 dark:text-blue-200">
                                                <span>{{ __('Check In Time') }}: {{ $attendance['check_in'] ?? '-' }}</span>
                                                <span>{{ __('Latitude') }}: {{ $attendance['attendance_latitude'] ?? '-' }}</span>
                                                <span>{{ __('Longitude') }}: {{ $attendance['attendance_longitude'] ?? '-' }}</span>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </section>
                        @endif

                        @if ((int) ($summary['total_absence_days'] ?? 0) > 0)
                            <section class="space-y-3">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <h4 class="text-sm font-semibold text-gray-900 dark:text-white">
                                        {{ __('Non Attendance') }}
                                    </h4>
                                    <div class="flex flex-wrap gap-2">
                                        <span class="inline-flex items-center rounded-md bg-red-50 px-2 py-1 text-xs font-medium text-red-700 ring-1 ring-inset ring-red-600/10 dark:bg-red-500/10 dark:text-red-300 dark:ring-red-500/20">
                                            {{ __('Total Absence Days') }}: {{ (int) ($summary['total_absence_days'] ?? 0) }}
                                        </span>
                                        <span class="inline-flex items-center rounded-md bg-gray-50 px-2 py-1 text-xs font-medium text-gray-700 ring-1 ring-inset ring-gray-600/10 dark:bg-gray-800 dark:text-gray-300 dark:ring-gray-700">
                                            {{ __('Unexcused Absence Days') }}: {{ (int) ($summary['unexcused_absence_days'] ?? 0) }}
                                        </span>
                                    </div>
                                </div>

                                <div class="grid max-h-72 grid-cols-1 gap-2 overflow-y-auto sm:grid-cols-2 xl:grid-cols-3">
                                    @foreach ($absenceDates as $absence)
                                        <span class="flex min-w-0 items-center justify-between gap-3 rounded-md border border-red-100 bg-red-50 px-2.5 py-1.5 text-sm text-red-800 dark:border-red-500/20 dark:bg-red-500/10 dark:text-red-200">
                                            <span class="font-medium">{{ $absence['date'] ?? '-' }}</span>
                                            <span class="text-xs text-red-600 dark:text-red-300">{{ $absence['label'] ?? '-' }}</span>
                                        </span>
                                    @endforeach
                                </div>
                            </section>
                        @endif

                        @if ((int) ($summary['late_days'] ?? 0) > 0)
                            <section class="space-y-3">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <h4 class="text-sm font-semibold text-gray-900 dark:text-white">
                                        {{ __('Late Attendance') }}
                                    </h4>
                                    <span class="inline-flex items-center rounded-md bg-amber-50 px-2 py-1 text-xs font-medium text-amber-700 ring-1 ring-inset ring-amber-600/10 dark:bg-amber-500/10 dark:text-amber-300 dark:ring-amber-500/20">
                                        {{ __('Late Days') }}: {{ (int) ($summary['late_days'] ?? 0) }}
                                    </span>
                                </div>

                                <div class="max-h-80 space-y-2 overflow-y-auto">
                                    @foreach ($lateAttendances as $lateAttendance)
                                        <div class="rounded-md border border-amber-100 bg-amber-50 px-3 py-2 text-sm dark:border-amber-500/20 dark:bg-amber-500/10">
                                            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                                <div class="font-medium text-amber-900 dark:text-amber-100">
                                                    {{ $lateAttendance['date'] ?? '-' }}
                                                </div>
                                                <div class="inline-flex w-fit items-center rounded-md bg-white px-2 py-1 text-xs font-semibold text-amber-800 ring-1 ring-inset ring-amber-600/10 dark:bg-amber-500/10 dark:text-amber-200 dark:ring-amber-500/20">
                                                    {{ __('Duration') }}: {{ $lateAttendance['late_duration'] ?? '-' }}
                                                </div>
                                            </div>

                                            <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-amber-800 dark:text-amber-200">
                                                <span>{{ __('Expected Check In') }}: {{ $lateAttendance['expected_check_in'] ?? '-' }}</span>
                                                <span>{{ __('Check In Time') }}: {{ $lateAttendance['check_in'] ?? '-' }}</span>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </section>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>

        <div class="rounded-lg border border-gray-200 bg-white px-4 py-3 shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <x-filament::pagination
                :paginator="$records"
                extreme-links
            />
        </div>
    @else
        <div class="rounded-lg border border-gray-200 bg-white px-5 py-8 text-center text-sm text-gray-500 shadow-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-400">
            {{ __('No non compliance records found') }}
        </div>
    @endif

    <div
        x-cloak
        x-show="locationMapOpen"
        x-transition.opacity
        class="fixed inset-0 z-50 overflow-y-auto px-4 py-6 sm:px-6"
        role="dialog"
        aria-modal="true"
    >
        <div class="fixed inset-0 bg-gray-950/70" x-on:click="closeLocationMap()"></div>

        <div
            x-show="locationMapOpen"
            x-transition
            class="relative mx-auto flex min-h-full max-w-5xl items-center"
        >
            <div class="w-full overflow-hidden rounded-lg bg-white shadow-xl ring-1 ring-gray-950/10 dark:bg-gray-900 dark:ring-white/10">
                <div class="flex items-start justify-between gap-4 border-b border-gray-100 px-5 py-4 dark:border-gray-800">
                    <div class="min-w-0">
                        <h3 class="text-base font-semibold text-gray-950 dark:text-white">
                            {{ __('Outside Work Range') }}
                        </h3>
                        <p class="mt-1 truncate text-sm text-gray-500 dark:text-gray-400" x-text="locationMapTitle()"></p>
                    </div>

                    <button
                        type="button"
                        x-on:click="closeLocationMap()"
                        class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-md text-gray-500 transition hover:bg-gray-100 hover:text-gray-700 focus:outline-none focus:ring-2 focus:ring-primary-500 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-gray-200"
                    >
                        @svg('heroicon-o-x-mark', 'h-5 w-5')
                        <span class="sr-only">{{ __('Close') }}</span>
                    </button>
                </div>

                <div class="space-y-4 p-5">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="rounded-md border border-blue-100 bg-blue-50 p-3 text-sm dark:border-blue-500/20 dark:bg-blue-500/10">
                            <div class="mb-2 inline-flex items-center gap-2 font-semibold text-blue-900 dark:text-blue-100">
                                <span class="h-3 w-3 rounded-full bg-blue-600 ring-2 ring-white dark:ring-gray-900"></span>
                                {{ __('Company Location') }}
                            </div>

                            <div class="space-y-1 text-xs text-blue-800 dark:text-blue-200">
                                <div>{{ __('Company') }}: <span x-text="location?.company || '-'"></span></div>
                                <div>{{ __('Branch') }}: <span x-text="location?.branch || '-'"></span></div>
                                <div>{{ __('Latitude') }}: <span x-text="location?.branch_latitude || '-'"></span></div>
                                <div>{{ __('Longitude') }}: <span x-text="location?.branch_longitude || '-'"></span></div>
                            </div>
                        </div>

                        <div class="rounded-md border border-red-100 bg-red-50 p-3 text-sm dark:border-red-500/20 dark:bg-red-500/10">
                            <div class="mb-2 inline-flex items-center gap-2 font-semibold text-red-900 dark:text-red-100">
                                <span class="h-3 w-3 rounded-full bg-red-600 ring-2 ring-white dark:ring-gray-900"></span>
                                {{ __('Student Check In Location') }}
                            </div>

                            <div class="space-y-1 text-xs text-red-800 dark:text-red-200">
                                <div>{{ __('Date') }}: <span x-text="location?.date || '-'"></span></div>
                                <div>{{ __('Check In Time') }}: <span x-text="location?.check_in || '-'"></span></div>
                                <div>{{ __('Distance From Work') }}: <span x-text="location?.distance_label || '-'"></span></div>
                                <div>{{ __('Latitude') }}: <span x-text="location?.attendance_latitude || '-'"></span></div>
                                <div>{{ __('Longitude') }}: <span x-text="location?.attendance_longitude || '-'"></span></div>
                            </div>
                        </div>
                    </div>

                    <div class="relative overflow-hidden rounded-lg bg-gray-100 ring-1 ring-gray-950/10 dark:bg-gray-950 dark:ring-white/10">
                        <div
                            x-ref="locationMap"
                            wire:ignore
                            class="h-[58vh] min-h-[360px] w-full"
                        ></div>

                        <div
                            x-cloak
                            x-show="locationMapError"
                            class="absolute inset-x-4 top-4 rounded-lg bg-danger-50 px-4 py-3 text-sm font-medium text-danger-700 ring-1 ring-danger-600/20 dark:bg-danger-400/10 dark:text-danger-300 dark:ring-danger-400/20"
                            x-text="locationMapError"
                        ></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @once
        <style>
            .non-compliance-location-marker {
                filter: drop-shadow(0 8px 14px rgba(15, 23, 42, 0.24));
            }

            .non-compliance-location-tooltip {
                border: 0;
                border-radius: 6px;
                box-shadow: 0 8px 18px rgba(15, 23, 42, 0.16);
                font-family: inherit;
                font-size: 12px;
                font-weight: 700;
            }

            .non-compliance-location-popup {
                min-width: 220px;
                color: #111827;
                font-family: inherit;
            }

            .non-compliance-location-popup-title {
                margin-bottom: 8px;
                font-weight: 700;
            }

            .non-compliance-location-popup-row {
                display: flex;
                justify-content: space-between;
                gap: 12px;
                padding: 3px 0;
                font-size: 12px;
            }

            .non-compliance-location-popup-row span {
                color: #2563eb;
            }

            .non-compliance-location-popup-row strong {
                max-width: 140px;
                overflow: hidden;
                text-align: end;
                text-overflow: ellipsis;
                white-space: nowrap;
            }
        </style>
    @endonce

    @script
        <script>
            Alpine.data('nonComplianceLocationMap', (config) => ({
                locationMapOpen: false,
                locationMapError: null,
                location: null,
                map: null,
                markerLayer: null,
                markerRenderer: null,

                openLocationMap(location) {
                    this.location = location || null;
                    this.locationMapError = null;
                    this.locationMapOpen = true;

                    this.$nextTick(async () => {
                        await this.initLocationMap(this.$refs.locationMap);
                        this.renderLocationMap();
                    });
                },

                closeLocationMap() {
                    this.locationMapOpen = false;
                },

                locationMapTitle() {
                    if (! this.location) {
                        return '';
                    }

                    const student = this.location.student || '-';
                    const date = this.location.date || '-';

                    return `${student} - ${date}`;
                },

                async initLocationMap(element) {
                    let leaflet = await this.waitForLeaflet(10);

                    if (! leaflet) {
                        await this.loadLocationMapAssets();
                        leaflet = await this.waitForLeaflet(40);
                    }

                    if (! leaflet) {
                        this.locationMapError = config.labels.loadError;
                        return;
                    }

                    if (this.map) {
                        this.refreshLocationMapSize();
                        return;
                    }

                    this.map = leaflet.map(element, {
                        center: [32.2211, 35.2544],
                        zoom: 13,
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
                    this.refreshLocationMapSize();
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

                async loadLocationMapAssets() {
                    if (! document.querySelector('[data-non-compliance-location-map-css]')) {
                        const link = document.createElement('link');
                        link.rel = 'stylesheet';
                        link.href = config.cssUrl;
                        link.dataset.nonComplianceLocationMapCss = 'true';
                        document.head.appendChild(link);
                    }

                    if ((window.L || window.leaflet)?.map) {
                        return;
                    }

                    if (! window.nonComplianceLocationMapScriptPromise) {
                        window.nonComplianceLocationMapScriptPromise = new Promise((resolve, reject) => {
                            const script = document.createElement('script');
                            script.src = config.jsUrl;
                            script.dataset.nonComplianceLocationMapJs = 'true';
                            script.onload = resolve;
                            script.onerror = reject;
                            document.body.appendChild(script);
                        });
                    }

                    try {
                        await window.nonComplianceLocationMapScriptPromise;
                    } catch (error) {
                        console.error(error);
                    }
                },

                renderLocationMap() {
                    if (! this.map || ! this.markerLayer || ! this.location) {
                        return;
                    }

                    const leaflet = window.L || window.leaflet;
                    const points = this.locationPoints();

                    this.markerLayer.clearLayers();

                    if (points.length < 2) {
                        this.locationMapError = config.labels.loadError;
                        return;
                    }

                    const latLngs = [];
                    const bounds = leaflet.latLngBounds([]);

                    points.forEach((point) => {
                        const latLng = leaflet.latLng(point.lat, point.lng);
                        latLngs.push(latLng);
                        bounds.extend(latLng);

                        leaflet.circleMarker(latLng, {
                            className: 'non-compliance-location-marker',
                            renderer: this.markerRenderer,
                            radius: 8,
                            color: '#ffffff',
                            weight: 2,
                            opacity: 1,
                            fillColor: point.color,
                            fillOpacity: 0.95,
                        })
                            .bindTooltip(point.label, {
                                permanent: true,
                                direction: 'top',
                                offset: [0, -8],
                                className: 'non-compliance-location-tooltip',
                            })
                            .bindPopup(this.locationPopupContent(point))
                            .addTo(this.markerLayer);
                    });

                    leaflet.polyline(latLngs, {
                        color: '#2563eb',
                        weight: 3,
                        opacity: 0.75,
                        dashArray: '8 8',
                    }).addTo(this.markerLayer);

                    if (latLngs[0].equals(latLngs[1])) {
                        this.map.setView(latLngs[0], 16, { animate: false });
                    } else if (bounds.isValid()) {
                        this.map.fitBounds(bounds.pad(0.35), { maxZoom: 16, animate: false });
                    }

                    this.refreshLocationMapSize();
                },

                locationPoints() {
                    if (! this.location) {
                        return [];
                    }

                    return [
                        {
                            label: config.labels.companyLocation,
                            lat: Number(this.location.branch_latitude),
                            lng: Number(this.location.branch_longitude),
                            color: '#2563eb',
                        },
                        {
                            label: config.labels.studentCheckInLocation,
                            lat: Number(this.location.attendance_latitude),
                            lng: Number(this.location.attendance_longitude),
                            color: '#dc2626',
                        },
                    ].filter((point) => Number.isFinite(point.lat) && Number.isFinite(point.lng));
                },

                locationPopupContent(point) {
                    const row = (label, value) => `
                        <div class="non-compliance-location-popup-row">
                            <span>${this.escapeLocationMapValue(label)}</span>
                            <strong>${this.escapeLocationMapValue(value || '-')}</strong>
                        </div>
                    `;

                    return `
                        <div class="non-compliance-location-popup">
                            <div class="non-compliance-location-popup-title">${this.escapeLocationMapValue(point.label)}</div>
                            ${row(config.labels.company, this.location?.company)}
                            ${row(config.labels.branch, this.location?.branch)}
                            ${row(config.labels.date, this.location?.date)}
                            ${row(config.labels.checkIn, this.location?.check_in)}
                            ${row(config.labels.distance, this.location?.distance_label)}
                            ${row(config.labels.latitude, point.lat)}
                            ${row(config.labels.longitude, point.lng)}
                        </div>
                    `;
                },

                escapeLocationMapValue(value) {
                    return String(value)
                        .replaceAll('&', '&amp;')
                        .replaceAll('<', '&lt;')
                        .replaceAll('>', '&gt;')
                        .replaceAll('"', '&quot;')
                        .replaceAll("'", '&#039;');
                },

                refreshLocationMapSize() {
                    setTimeout(() => this.map?.invalidateSize(true), 180);
                },
            }));
        </script>
    @endscript
</div>
