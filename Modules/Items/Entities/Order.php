<?php

namespace Modules\Items\Entities;

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
use Modules\Delivery\Entities\CustomerAddress;
use Modules\Items\Enums\AddonType;
use Modules\Items\Enums\DeliveryType;
use Modules\Items\Enums\OrderStatus;
use Modules\Items\Enums\PaymentMethod;
use Modules\Items\Enums\PaymentStatus;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

// use Modules\Items\Database\Factories\CategoryFactory;

class Order extends Model
{
    use SoftDeletes;
    use LogsActivity;

    /**
     * The attributes that are mass assignable.
     */

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setTable(config('items.table_prefix') . 'orders');
    }

    protected $fillable = [
        'id',
        'user_id',
        'branch_id',
        'city_id',
        'district_id',
        'order_number',
        'sub_total',
        'discount',
        'delivery_fee',
        'total',
        'status',
        'payment_method',
        'payment_status',
        'notes',
        'delivery_address',
        'contact_phone',
        'delivery_type',
        'created_by',
    ];

    protected $casts = [
        'status' => OrderStatus::class,
        'payment_method' => PaymentMethod::class,
        'payment_status' => PaymentStatus::class,
        'delivery_type'  => DeliveryType::class
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


    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function user():BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function branch():BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function deliveryAddress(): BelongsTo
    {
        return $this->belongsTo(CustomerAddress::class, 'delivery_address', 'id');
    }
}
