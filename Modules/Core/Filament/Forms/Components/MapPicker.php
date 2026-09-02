<?php

namespace Modules\Core\Filament\Forms\Components;

use Closure;
use Dotswan\MapPicker\Fields\Map;

class MapPicker extends Map
{
    public string $view = 'core::filament.forms.components.map-picker';

    protected function setUp(): void
    {
        parent::setUp();

        $this
            // ============ الأساسيات ============
            ->columnSpanFull()
            ->label(__('Location On Map'))
            ->default(['lat' => 32.2211, 'lng' => 35.2544])
            ->defaultLocation(latitude: 32.2211, longitude: 35.2544) // نابلس
            ->tilesUrl(config('services.map.tiles_url')) // خريطة OSM المستضافة ذاتياً
            ->extraTileControl([
                'attribution' => '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
                'tileSize' => 256,
                'zoomOffset' => 0,
                'detectRetina' => false,
                'fallbackTilesUrl' => config('services.map.fallback_tiles_url'),
            ])
            ->markerColor("#22c55eff")

            // ============ الحالة الافتراضية (Marker Picker) ============
            ->draggable(true)
            ->clickable(false)
            ->showMarker(true)
            ->showMyLocationButton(true)
            ->showFullscreenControl(true)
            ->showZoomControl(true)
            ->geoMan(false) // <--- الرسم معطل افتراضياً

            // ============ الستايلات (جيد) ============
            ->extraStyles(['min-height: 50vh', 'border-radius: 10px'])
            ->minZoom(5)
            ->maxZoom(20)

            // ============ التفاعل والبيانات (مهم جداً) ============
            // Must sync on change, not on blur: clicking or dragging the
            // marker never blurs the map, so with `onBlur` the coordinates
            // reached the server late or not at all and the latitude /
            // longitude inputs below the map stayed empty.
            ->reactive()
            ->live()
            ->debounce(300)

            // ============ التلميحات والمساعدة ============
            // --- (مُعدل) ---
            ->helperText(__('Click on the map or move the marker to fill latitude and longitude.')) // 3.
            ->hint(__('Select location from map')) // 4.
            ->hintIcon('heroicon-o-information-circle')
            ->hintColor('primary')

            // ============ حفظ الإحداثيات (للنقطة + الدائرة) ============
            ->afterStateUpdated(function (callable $set, callable $get, ?array $state): void {
                if ($state) {
                    $set('latitude', $state['lat'] ?? null);
                    $set('longitude', $state['lng'] ?? null);
                    if (isset($state['radius'])) {
                        $set('radius', $state['radius']);
                    }
                }
            })

            // ============ تحميل البيانات (جيد) ============
            ->afterStateHydrated(function ($state, $record, callable $set): void {
                if ($record && $record->latitude && $record->longitude) {
                    $set('location', [
                        'lat' => (float) $record->latitude,
                        'lng' => (float) $record->longitude,
                        'radius' => $record->radius ?? null
                    ]);
                }
            });
    }

    public function fixedLocationMarker(Closure|bool $fixed = true): static
    {
        return $this->extraTileControl([
            'fixedLocationMarker' => $this->evaluate($fixed),
        ]);
    }

    // ======================================================
    // ============ الدوال المضافة (هنا الحل) ============
    // ======================================================

    /**
     * تفعيل وضع الرسم (GeoMan)
     * سيقوم بتعطيل الماركر الافتراضي وتغيير طريقة حفظ البيانات
     */
    public function enableDrawing(?string $stateName = null): static
    {
        $stateName = $stateName ?? $this->getName();

        $this
            ->geoMan(true)
            ->geoManEditable(true)
            ->geoManPosition('topleft')
            ->showMarker(true)
            ->draggable(true)
            ->clickable(false)

            // عند التعديل: خزّن نص JSON في حقل الـ state المحدد
            ->afterStateUpdated(function (callable $set, callable $get, ?array $state) use ($stateName): void {
                if ($state === null) {
                    $set($stateName, null);
                    return;
                }

                $geo = $state['geojson'] ?? null;

                if ($geo === null) {
                    $set($stateName, null);
                    return;
                }

                // خزّن كسلسلة JSON لتجنّب خطأ Livewire
                $set($stateName, is_string($geo) ? $geo : json_encode($geo));

                // لا نحتاج حفظ latitude/longitude عندما نستخدم GeoJSON
                $set('latitude', null);
                $set('longitude', null);
            })

            // عند التحميل: إذا كان الحقل في السجل نص JSON، فكّّه ومرّره للخريطة
            ->afterStateHydrated(function ($state, $record, callable $set) use ($stateName): void {

                // 💡 $state هو القيمة القادمة من fillForm (وهو نص JSON)
                if ($state && is_string($state)) {

                    // 1. فك تشفير النص
                    $decodedState = json_decode($state, true);

                    // 2. $decodedState أصبح الآن ['geojson' => {...}]
                    if ($decodedState && isset($decodedState['geojson'])) {

                        // 3. قم بتمرير المصفوفة إلى خاصية 'location' الداخلية للمكون
                        $set('location', $decodedState);

                    }
                }

                // (اختياري: كود احتياطي إذا لم يعمل fillForm لسبب ما)
                elseif ($record && $record->{$stateName} && is_object($record->{$stateName})) {
                    // $record->{$stateName} هو كائن Polygon
                    $geoJsonArray = $record->{$stateName}->toArray();
                    $featureCollection = [
                        'type' => 'FeatureCollection',
                        'features' => [
                            [
                                'type' => 'Feature',
                                'properties' => new \stdClass(),
                                'geometry' => $geoJsonArray,
                            ],
                        ],
                    ];
                    $set('location', ['geojson' => $featureCollection]);

                    // وقم أيضاً بتحديث الحالة لتكون نصاً للمستقبل
                    $set($stateName, json_encode(['geojson' => $featureCollection]));
                }
            });
        return $this;
    }


    /**
     * تفعيل رسم دائرة حول الماركر
     */
    public function enableRadius(bool $editable = true, int $defaultRadius = 1000): static
    {
        $this->drawCircleMarker(true, $editable, $defaultRadius); // (show, editable, defaultRadius)
        // لا نحتاج تعديل afterStateUpdated لأن الكود الأساسي يدعم 'radius'
        return $this;
    }

    /**
     * تفعيل وضع القراءة فقط
     */
    public function readOnly(): static
    {
        $this
            ->draggable(false)
            ->clickable(false)
            ->disabled(true)
            ->showMyLocationButton(false)
            ->showZoomControl(false)
            ->geoMan(false);

        return $this;
    }

    // ============ الدوال المساعدة التي أضفتها (ممتازة) ============

    public function useGoogleMaps(string $type = 'roadmap'): static
    {
        $urls = [
            'roadmap' => 'https://mt1.google.com/vt/lyrs=m&x={x}&y={y}&z={z}',
            'satellite' => 'https://mt1.google.com/vt/lyrs=s&x={x}&y={y}&z={z}',
            'hybrid' => 'https://mt1.google.com/vt/lyrs=y&x={x}&y={y}&z={z}',
            'terrain' => 'https://mt1.google.com/vt/lyrs=p&x={x}&y={y}&z={z}',
        ];
        $this->tilesUrl($urls[$type] ?? $urls['roadmap']);
        return $this;
    }

    public function darkMode(): static
    {
        $this->tilesUrl('https://cartodb-basemaps-{s}.global.ssl.fastly.net/dark_all/{z}/{x}/{y}.png');
        return $this;
    }
}
