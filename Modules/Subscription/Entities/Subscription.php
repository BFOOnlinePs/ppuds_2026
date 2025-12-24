<?php

namespace Modules\Subscription\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Entities\User;
use Modules\Customer\Entities\Customer;
use Modules\Subscription\Enums\Status;
use Spatie\Activitylog\LogOptions;

// use Modules\subscription\Database\Factories\SubscriptionFactory;

class Subscription extends Model
{
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setTable(config('subscription.table_prefix') . 'subscriptions');
    }

    protected $fillable = [
        'customer_id',
        'plan_id',
        'start_date',
        'end_date',
        'status',
        'paid_amount',
        'remaining_visits',
        'created_by',
    ];

    protected $casts = [
        'status' => Status::class,
        'start_date' => 'datetime',
        'end_date' => 'datetime',
    ];

    public $translatedAttributes = [
        'name',
        'description'
    ];

    public $useTranslationFallback = true;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly($this->getFillable())
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn(string $eventName) => "This model has been {$eventName} and value ")
            ->useLogName(class_basename($this));
    }

    protected static function booted()
    {
        parent::booted();

        static::created(function ($model) {
            $locale = app()->getLocale();
            $translationData = request()->only($model->translatedAttributes);

            if (!empty($translationData)) {
                $model->translateOrNew($locale)->fill($translationData);
                $model->save();
            }
        });
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function freezes(): HasMany
    {
        return $this->hasMany(SubscriptionFreeze::class);
    }

    public function activeFreeze()
    {
        return $this->hasOne(SubscriptionFreeze::class)->whereNull('end_date');
    }
}
