<?php

namespace Modules\GeoLocation\Entities;

use Astrotomic\Translatable\Translatable;
use Astrotomic\Translatable\Contracts\Translatable as TranslatableContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Modules\Core\Entities\Currency;
use Modules\Core\Entities\User;
use Modules\GeoLocation\Enums\CapitalType;
use Modules\GeoLocation\Enums\CityType;
use Modules\Items\Entities\Product;
use Modules\Items\Enums\AttributeType;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\MediaLibrary\InteractsWithMedia;

// use Modules\Items\Database\Factories\AttributeFactory;

class City extends Model implements TranslatableContract
{
    use LogsActivity;
    use Translatable;

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setTable(config('geolocation.table_prefix') . 'cities');
    }

    protected $fillable = [
        'governorate_id',
        'latitude',
        'longitude',
        'population',
        'type',
        'is_capital',
        'capital_type',
    ];

    protected $casts = [
        'type' => CityType::class,
        'capital_type' => CapitalType::class
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

    public function governorate(): BelongsTo
    {
        return $this->belongsTo(Governorate::class);
    }

    public function districts(): HasMany
    {
        return $this->hasMany(District::class);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function scopeCapitals($query)
    {
        return $query->where('is_capital', true);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }
}
