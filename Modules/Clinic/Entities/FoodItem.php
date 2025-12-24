<?php

namespace Modules\Clinic\Entities;

use Astrotomic\Translatable\Translatable;
use Astrotomic\Translatable\Contracts\Translatable as TranslatableContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Entities\User;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Illuminate\Database\Eloquent\SoftDeletes;

class FoodItem extends Model implements TranslatableContract
{
    use LogsActivity;
    use Translatable;
    use softDeletes;

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->setTable(config('clinic.table_prefix') . 'food_items');
    }

    protected $fillable = [
        'food_category_id',
        'created_by',
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
            ->setDescriptionForEvent(fn(string $eventName) => "This model has been {$eventName}")
            ->useLogName(class_basename($this));
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // The problematic booted() method has been removed from here.

    public function foodCategory(): BelongsTo
    {
        return $this->belongsTo(FoodCategory::class, 'food_category_id');
    }

    public function servingSizes(): HasMany
    {
        // The relationship is now clean and correct.
        return $this->hasMany(ServingSize::class, 'food_item_id');
    }
}
