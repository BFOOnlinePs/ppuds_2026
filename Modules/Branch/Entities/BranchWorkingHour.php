<?php

namespace Modules\Branch\Entities;

use Illuminate\Database\Eloquent\Model;
use Modules\Branch\Enums\WeekDay;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class BranchWorkingHour extends Model
{
    use LogsActivity;

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setTable(config('branch.table_prefix') . 'branch_working_hours');
    }

    protected $fillable = [
        'branch_id',
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

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }
}
