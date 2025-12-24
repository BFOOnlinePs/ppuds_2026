<?php

namespace Modules\Clinic\Entities;

use Astrotomic\Translatable\Translatable;
use Astrotomic\Translatable\Contracts\Translatable as TranslatableContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Entities\User;
use Modules\Customer\Entities\Customer;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Illuminate\Database\Eloquent\SoftDeletes;

class Reading extends Model
{
    use LogsActivity;
    use softDeletes;

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->setTable(config('clinic.table_prefix') . 'readings');
    }

    protected $fillable = [
        'customer_id',
        'weight',
        'fats',
        'muscles',
        'salts',
        'water',
        'bmi',
        'created_by',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly($this->getFillable())
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn(string $eventName) => "This model has been {$eventName}")
            ->useLogName(class_basename($this));
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }
}
