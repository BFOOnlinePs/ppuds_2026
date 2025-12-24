<?php

namespace Modules\Coupon\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Modules\Core\Entities\User;
use Modules\Coupon\Entities\Coupon;
use Modules\Coupon\Enums\CouponType;
use Modules\Items\Entities\Order;
use Modules\Reels\Enums\ReelStatus;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class CouponUsage extends Model
{
    use LogsActivity;

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setTable(config('coupon.table_prefix') . 'coupon_usages');
    }

    protected $fillable = [
        'id',
        'coupon_id',
        'user_id',
        'order_id',
        'discount_amount',
    ];
    protected $casts = [
        'discount_amount' => 'decimal:2',
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

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class, 'coupon_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
