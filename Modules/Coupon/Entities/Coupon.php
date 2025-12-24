<?php

namespace Modules\Coupon\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Entities\User;
use Modules\Coupon\Enums\CouponType;
use Modules\Reels\Enums\ReelStatus;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Modules\Coupon\Entities\CouponUsage;

class Coupon extends Model
{
    use LogsActivity;


    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setTable(config('coupon.table_prefix') . 'coupons');
    }

    protected $fillable = [
        'id',
        'code',
        'type',
        'value',
        'max_uses',
        'current_uses',
        'min_order_amount',
        'starts_at',
        'expires_at',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'type' => CouponType::class,
        'is_active' => 'boolean',
        'value' => 'decimal:2',
        'min_order_amount' => 'decimal:2',
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

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function couponables() : HasMany
    {
        return $this->hasMany(Couponable::class);
    }

    public function usages(): HasMany
    {
        return $this->hasMany(CouponUsage::class);
    }
}
