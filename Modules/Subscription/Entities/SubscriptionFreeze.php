<?php

namespace Modules\Subscription\Entities;

use Astrotomic\Translatable\Translatable;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Entities\User;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class SubscriptionFreeze extends Model
{
    use LogsActivity;
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setTable(config('subscription.table_prefix') . 'subscription_freezes');
    }

    protected $fillable = [
        'subscription_id',
        'start_date',
        'end_date',
        'reason',
        'created_by'
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
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

    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }
}
