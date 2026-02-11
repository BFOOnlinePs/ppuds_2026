<?php

namespace Modules\PPUDS\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use LeaveRequestStatus;
use LeaveRequestType;
use Modules\Core\Entities\User;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class LeaveRequest extends Model
{
    use LogsActivity;
    use SoftDeletes;

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setTable(config('ppuds.table_prefix') . 'leave_requests');
    }

    protected $fillable = [
        'id',
        'student_company_id',
        'type',
        'start_at',
        'end_at',
        'reason',
        'company_approval',
        'university_approval',
        'status',
        'rejection_reason',
        'created_by',
    ];

    protected $casts = [
        'type'  => LeaveRequestType::class,
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'company_approval' => LeaveRequestStatus::class,
        'university_approval' => LeaveRequestStatus::class,
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

    public function getDurationAttribute()
    {
        return $this->start_at->diffForHumans($this->end_at, true);
    }

    public function isFullyApproved(): bool
    {
        return $this->company_approval === LeaveRequestStatus::APPROVED
            && $this->university_approval === LeaveRequestStatus::APPROVED;
    }
}
