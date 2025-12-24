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

class CustomerAddress extends Model
{
    use LogsActivity;
    use softDeletes;

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setTable(config('delivery.table_prefix') . 'customer_addresses');
    }

    protected $fillable = [
        'id',
        'user_id',
        'label',
        'latitude',
        'longitude',
        'address_details',
        'delivery_instructions',
        'is_default',
        'contact_phone'
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
