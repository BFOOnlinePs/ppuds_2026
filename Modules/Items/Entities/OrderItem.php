<?php

namespace Modules\Items\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Core\Entities\User;
use Modules\Items\Enums\AddonType;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class OrderItem extends Model
{
    use SoftDeletes;
    use LogsActivity;

    /**
     * The attributes that are mass assignable.
     */

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setTable(config('items.table_prefix') . 'order_items');
    }

    protected $fillable = [
        'id',
        'order_id',
        'product_id',
        'price',
        'quantity',
        'total_price',
        'notes',
        'created_by',
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

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class , 'product_id' , 'id');
    }

    public function addonOptions(): BelongsToMany
    {
        return $this->belongsToMany(AddonOption::class, config('items.table_prefix') . 'addon_option_order_item')
            ->withPivot(['quantity','price']);
    }
}
