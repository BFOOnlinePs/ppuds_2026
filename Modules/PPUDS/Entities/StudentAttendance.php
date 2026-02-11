<?php

namespace Modules\PPUDS\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Core\Entities\User;
use Modules\PPUDS\Enums\AttendanceStatus;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class StudentAttendance extends Model
{
    use LogsActivity;
    use softDeletes;
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setTable(config('ppuds.table_prefix') . 'student_attendances');
    }

    protected $fillable = [
        'id',
        'student_company_id',
        'attendance_date',
        'check_in',
        'check_in_latitude',
        'check_in_longitude',
        'check_out',
        'check_out_latitude',
        'check_out_longitude',
        'status',
        'description',
        'created_by',
    ];

    protected $casts = [
        // 1. تحويل التواريخ تلقائياً إلى Carbon Instances
        'attendance_date' => 'date',
        'check_in'        => 'datetime',
        'check_out'       => 'datetime',

        'status'          => AttendanceStatus::class,

        'check_in_latitude'   => 'float',
        'check_in_longitude'  => 'float',
        'check_out_latitude'  => 'float',
        'check_out_longitude' => 'float',
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

    public function studentCompany()
    {
        return $this->belongsTo(StudentCompany::class, 'student_company_id');
    }

    public function studentReport()
    {
        return $this->hasOne(StudentReport::class, 'student_attendance_id');
    }
}
