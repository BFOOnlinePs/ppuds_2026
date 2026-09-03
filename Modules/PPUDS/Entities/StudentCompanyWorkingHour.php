<?php

namespace Modules\PPUDS\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Branch\Enums\WeekDay;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class StudentCompanyWorkingHour extends Model
{
    use LogsActivity;

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setTable(config('ppuds.table_prefix') . 'student_company_working_hours');
    }

    protected $fillable = [
        'student_company_id',
        'day',
        'start_time',
        'end_time',
        'is_closed',
    ];

    protected $casts = [
        'day'           => WeekDay::class,
        'is_closed'     => 'boolean',
        'start_time'    => 'datetime:H:i',
        'end_time'      => 'datetime:H:i',
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

    public function studentCompany(): BelongsTo
    {
        return $this->belongsTo(StudentCompany::class, 'student_company_id');
    }
}
