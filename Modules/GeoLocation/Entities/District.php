<?php

namespace Modules\GeoLocation\Entities;

use Astrotomic\Translatable\Translatable;
use Astrotomic\Translatable\Contracts\Translatable as TranslatableContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Support\Str;
use Modules\Core\Entities\Currency;
use Modules\Core\Entities\User;
use Modules\GeoLocation\Enums\DistrictType;
use Modules\Items\Entities\Product;
use Modules\Items\Enums\AttributeType;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\MediaLibrary\InteractsWithMedia;

// use Modules\Items\Database\Factories\AttributeFactory;

class District extends Model implements TranslatableContract
{
    use LogsActivity;
    use Translatable;

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setTable(config('geolocation.table_prefix') . 'districts');
    }

    protected $fillable = [
        'city_id',
        'latitude',
        'longitude',
        'type',
    ];

    protected $casts = [
        'type' => DistrictType::class,
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
    ];

    public $translatedAttributes = [
        'name',
    ];

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

    public function getAttributeName(): string
    {
        return $this->translate('name');
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function governorate(): HasOneThrough
    {
        return $this->hasOneThrough(
            Governorate::class,
            City::class,
            'id', // city.id
            'id', // governorate.id
            'city_id', // district.city_id
            'governorate_id' // city.governorate_id
        );
    }

    public function country(): HasOneThrough
    {
        return $this->hasOneThrough(
            Country::class,
            Governorate::class,
            'id', // governorate.id (through city->governorate)
            'id', // country.id
            'city_id', // district.city_id (we need to go through city first)
            'country_id' // governorate.country_id
        );
    }
}
