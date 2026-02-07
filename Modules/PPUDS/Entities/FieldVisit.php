<?php

namespace Modules\PPUDS\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Core\Entities\User;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class FieldVisit extends Model
{
    use LogsActivity;
    use SoftDeletes;

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setTable(config('ppuds.table_prefix') . 'field_visits');
    }

    protected $fillable = [
        'id',
        'student_company_id',
        'supervisor_id',
        'visiting_place',
        'visit_date',
        'visit_time',
        'visit_duration',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'visit_date' => 'date',
        // 'visit_time' => 'datetime:H:i', // Or just string, usually fine as string from DB
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly($this->getFillable())
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn(string $eventName) => "This field visit has been {$eventName}")
            ->useLogName(class_basename($this));
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function studentCompany(): BelongsTo
    {
        return $this->belongsTo(StudentCompany::class, 'student_company_id');
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }
}
