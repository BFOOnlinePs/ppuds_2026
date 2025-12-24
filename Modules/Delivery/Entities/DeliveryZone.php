<?php

namespace Modules\Delivery\Entities;

use Astrotomic\Translatable\Translatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use MatanYadaev\EloquentSpatial\Objects\Polygon;
use MatanYadaev\EloquentSpatial\Traits\HasSpatial;
use Modules\Branch\Entities\Branch;
use Modules\Core\Entities\User;
use Modules\Marketing\Enums\LoyaltyRuleType;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\MediaLibrary\InteractsWithMedia;
use Astrotomic\Translatable\Contracts\Translatable as TranslatableContract;

class DeliveryZone extends Model implements TranslatableContract
{
    use LogsActivity;
    use Translatable;
    use HasSpatial;

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setTable(config('delivery.table_prefix') . 'delivery_zones');
    }

    protected $fillable = [
        'branch_id',
        'zone_area',
        'delivery_pricing_id',
        'is_active',
        'created_by'
    ];

    protected $casts = [
        'zone_area' => Polygon::class
    ];

    public $translatedAttributes = [
        'name',
        'description',
    ];

    protected $spatialFields = [
        'zone_area'
    ];

    public $translationForeignKey = 'delivery_zone_id';

    public $useTranslationFallback = true;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly($this->getFillable())
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn(string $eventName) => "This model has been {$eventName} and value ")
            ->useLogName(class_basename($this));
    }

    protected static function booted()
    {
        parent::booted();

        static::created(function ($model) {
            $locale = app()->getLocale();
            $translationData = request()->only($model->translatedAttributes);

            if (!empty($translationData)) {
                $model->translateOrNew($locale)->fill($translationData);
                $model->save();
            }
        });
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function deliveryPricing(): BelongsTo
    {
        return $this->belongsTo(DeliveryPricing::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
//    public function setZoneAreaAttribute($value)
//    {
//        // 1. تحقق أن القيمة هي مصفوفة وأنها تحتوي على مفتاح 'geojson'
//        if (!is_array($value) || !isset($value['geojson'])) {
//            $this->attributes['zone_area'] = null;
//            return;
//        }
//
//        $geojson = $value['geojson'];
//
//        // 2. تحقق من أنها FeatureCollection وتحتوي على elements
//        if ($geojson['type'] !== 'FeatureCollection' || empty($geojson['features'])) {
//            $this->attributes['zone_area'] = null;
//            return;
//        }
//
//        // 3. احصل على الـ geometry من أول feature
//        $geometry = $geojson['features'][0]['geometry'] ?? null;
//
//        if (!$geometry) {
//            $this->attributes['zone_area'] = null;
//            return;
//        }
//
//        // 4. التحقق الأهم: هل هي مساحة مغلقة (Polygon)؟
//        if ($geometry['type'] !== 'Polygon') {
//            // أرجع خطأ validation واضح للمستخدم
//            throw ValidationException::withMessages([
//                'zone_area' => __('The delivery zone must be a closed area (Polygon).'),
//            ]);
//        }
//
//        // 5. الآن نحن متأكدون أنها Polygon، قم بترميزها
//        $geoJsonString = json_encode($geometry);
//
//        if ($geoJsonString === false || $geoJsonString === 'null') {
//            $this->attributes['zone_area'] = null;
//            return;
//        }
//
//        // 6. جهزها للحفظ في قاعدة البيانات باستخدام دالة SQL
//        $this->attributes['zone_area'] = DB::raw("ST_GeomFromGeoJSON('$geoJsonString')");
//    }

    public function setZoneAreaAttribute($value)
    {
        if (empty($value)) {
            $this->attributes['zone_area'] = null;
            return;
        }

        $decoded = is_string($value) ? json_decode($value, true) : $value;

        // ... (كود التحقق من الـ geometry كما هو عندك) ...
        if (isset($decoded['type']) && $decoded['type'] === 'FeatureCollection' && !empty($decoded['features'][0]['geometry'])) {
            $geometry = $decoded['features'][0]['geometry'];
        } elseif (isset($decoded['type']) && $decoded['type'] === 'Polygon') {
            $geometry = $decoded;
        } elseif (isset($decoded['geometry'])) {
            $geometry = $decoded['geometry'];
        } else {
            $this->attributes['zone_area'] = null;
            return;
        }

        $geoJsonString = json_encode($geometry);

        if ($geoJsonString === false) {
            $this->attributes['zone_area'] = null;
            return;
        }

        // 💡💡 الحل الصحيح لإصدارك 💡💡
        // نستخدم ST_SRID() لتعيين الرقم 4326 بدلاً من تمريره
        $this->attributes['zone_area'] = DB::raw("ST_GeomFromGeoJSON('{$geoJsonString}')");    }
}
