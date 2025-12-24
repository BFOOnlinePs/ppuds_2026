<?php

namespace Modules\Clinic\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Entities\User;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProgramDay extends Model
{
    use LogsActivity;
    use softDeletes;

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setTable(config('clinic.table_prefix') . 'program_days');
    }

    protected $fillable = [
        'program_id',
        'program_customer_id',
        'day_number',
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


    public function dayMeals(): HasMany
    {
        return $this->hasMany(DayMeal::class, 'program_day_id');
    }

    public function programCustomer(): BelongsTo
    {
        return $this->belongsTo(ProgramCustomer::class, 'program_customer_id');
    }
}
