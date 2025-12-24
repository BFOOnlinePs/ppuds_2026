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
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class DeliveryFeeTier extends Model
{
    use LogsActivity;
    use softDeletes;

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setTable(config('delivery.table_prefix') . 'delivery_fee_tiers');
    }

    protected $fillable = [
        'delivery_pricing_id',
        'min_distance_km',
        'extra_charge',
        'created_by'
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

    public function deliveryPricing(): BelongsTo
    {
        return $this->belongsTo(DeliveryPricing::class, 'delivery_pricing_id');
    }
}
