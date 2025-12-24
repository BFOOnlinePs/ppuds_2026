<?php

namespace Modules\Delivery\Entities;

use Astrotomic\Translatable\Translatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Branch\Entities\Branch;
use Modules\Core\Entities\User;
use Modules\Marketing\Enums\LoyaltyRuleType;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\MediaLibrary\InteractsWithMedia;
use Astrotomic\Translatable\Contracts\Translatable as TranslatableContract;

class DeliveryPricing extends Model implements TranslatableContract
{
    use LogsActivity;
    use softDeletes;
    use Translatable;

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setTable(config('delivery.table_prefix') . 'delivery_pricings');
    }

    protected $fillable = [
        'base_fee',
        'price_per_km',
        'created_by'
    ];

    public $translatedAttributes = [
        'name',
        'description',
    ];

    public $translationForeignKey = 'delivery_id';

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

    public function pricable(): MorphTo
    {
        return $this->morphTo();
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function deliveryFeeTiers(): HasMany
    {
        return $this->hasMany(DeliveryFeeTier::class , 'delivery_pricing_id');
    }

    public function deliveryZones(): HasMany
    {
        return $this->hasMany(DeliveryZone::class);
    }
}
