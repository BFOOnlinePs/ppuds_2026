<?php

namespace Modules\Clinic\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Clinic\Enums\CustomerProgramStatus;
use Modules\Core\Entities\User;
use Modules\Customer\Entities\Customer;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProgramCustomer extends Model
{
    use LogsActivity;
    use softDeletes;

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setTable(config('clinic.table_prefix') . 'customer_programs');
    }

    protected $fillable = [
        'customer_id',
        'program_id',
        'start_date',
        'status',
        'created_by',
    ];

    protected $casts = [
        'status' => CustomerProgramStatus::class,
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

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function days(): HasMany
    {
        return $this->hasMany(ProgramDay::class , 'program_customer_id');
    }
}
