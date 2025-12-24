<?php

namespace Modules\Marketing\Entities;

use Astrotomic\Translatable\Translatable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Modules\Branch\Entities\Branch;
use Modules\Core\Entities\User;
use Modules\Core\Enums\ImageQuality;
use Modules\Core\Enums\ImageSize;
use Modules\Core\Services\ImageService;
use Modules\Items\Entities\Category;
use Modules\Items\Entities\Product;
use Modules\Marketing\Enums\LoyaltyRuleType;
use Modules\Marketing\Enums\LoyaltyTierKey;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Astrotomic\Translatable\Contracts\Translatable as TranslatableContract;

// use Modules\Clinic\Database\Factories\DiseaseFactory;

class LoyaltyTier extends Model implements HasMedia, TranslatableContract
{
    use LogsActivity;
    use Translatable;
    use softDeletes;
    use InteractsWithMedia;

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setTable(config('marketing.table_prefix') . 'loyalty_tiers');
    }

    protected $fillable = [
        'key',
        'min_points',
        'created_by',
    ];

    protected $casts = [
        'min_points'    => 'integer',
        'key'           => LoyaltyTierKey::class
    ];

    public $translatedAttributes = [
        'name',
        'description',
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

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
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

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('loyalty_tier_icon')
            ->singleFile()
            ->useFallbackUrl(url('/images/default-tier-icon.svg')); // ضع مسار أيقونة افتراضية
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'loyalty_tier_id');
    }
}
