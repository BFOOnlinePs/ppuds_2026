<?php

namespace Modules\Coupon\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Modules\Core\Entities\User;
use Modules\Coupon\Enums\CouponType;
use Modules\Reels\Enums\ReelStatus;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Couponable extends Model
{
    use LogsActivity;

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setTable(config('coupon.table_prefix') . 'couponables');
    }

    protected $fillable = [
        'id',
        'coupon_id',
        'couponable_id',
        'couponable_type',
        'exclusion_type',
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

    public function couponable(): MorphTo
    {
        return $this->morphTo();
    }
}
