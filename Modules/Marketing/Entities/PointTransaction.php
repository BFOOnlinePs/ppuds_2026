<?php

namespace Modules\Marketing\Entities;

use Astrotomic\Translatable\Translatable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Branch\Entities\Branch;
use Modules\Items\Entities\Category;
use Modules\Items\Entities\Product;
use Modules\Marketing\Enums\LoyaltyRuleType;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class PointTransaction extends Model implements HasMedia
{
    use LogsActivity;
    use InteractsWithMedia;

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setTable(config('marketing.table_prefix') . 'loyalty_rules');
    }

    protected $fillable = [
        'user_id',
        'points',
        'description',
        'transactionable_id',
        'transactionable_type',
        'created_by',
    ];

    protected $casts = [
        'points' => 'integer',
    ];

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

    public function pointable()
    {
        return $this->morphTo();
    }
}
