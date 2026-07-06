<?php

namespace Modules\PPUDS\Entities;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\Branch\Entities\Branch;
use Modules\Core\Entities\User;
use Modules\Core\Enums\ImageQuality;
use Modules\Core\Enums\ImageSize;
use Modules\Core\Services\ImageService;
use Modules\PPUDS\Enums\AttendanceStatus;
use Modules\PPUDS\Enums\TrainingStatus;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class StudentCompany extends Model implements HasMedia
{
    use InteractsWithMedia;
    use LogsActivity;
    use SoftDeletes;

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setTable(config('ppuds.table_prefix').'students_companies');
    }

    protected $fillable = [
        'id',
        'registration_id',
        'student_id',
        'company_id',
        'branch_id',
        'department_id',
        'status',
        'created_by',
    ];

    protected $casts = [
        'status' => TrainingStatus::class,
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly($this->getFillable())
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $eventName) => "This student company record has been {$eventName}")
            ->useLogName(class_basename($this));
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this
            ->addMediaConversion('preview') // اسم الكونفيجن للعرض المصغر
            ->fit(Fit::Contain, 300, 300)
            ->nonQueued();
    }

    public function addImage($file)
    {
        if (is_array($file)) {
            $file = reset($file);
        }

        // التحقق من نوع الملف
        if (
            ! $file instanceof \Illuminate\Http\UploadedFile &&
            ! ($file instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile)
        ) {
            return null;
        }

        // مسح الملف السابق (إذا كنت تريد ملفاً واحداً فقط لكل متابعة)
        // إذا كان مسموحاً بأكثر من ملف، قم بإزالة هذا السطر
        $this->clearMediaCollection('student_company_files');

        try {
            $originalName = $file->getClientOriginalName();
            $extension = $file->getClientOriginalExtension();
            $fileName = time().'_'.Str::slug(pathinfo($originalName, PATHINFO_FILENAME)).'.'.$extension;

            $media = $this
                ->addMedia($file)
                ->usingFileName($fileName)
                ->toMediaCollection('student_company_files', 'students_companies'); // Disk name can be changed

            // تطبيق خدمة ضغط وتحجيم الصور الخاصة بكم
            // ملاحظة: هذا الكود يعمل بشكل ممتاز مع الصور، تأكد أنه لا يسبب مشاكل مع ملفات PDF
            if (str_starts_with($media->mime_type, 'image/')) {
                $size = ImageSize::MEDIUM;

                ImageService::optimize($media->getPath(), ImageQuality::HIGH->value);
                ImageService::resize($media->getPath(), $size->width(), $size->height());
            }

            return $media;
        } catch (\Exception $e) {
            Log::error('Error uploading student company file: '.$e->getMessage());

            return null;
        }
    }

    public function getImageAttribute()
    {
        return $this->getFirstMediaUrl('student_company_files');
    }

    public function registration(): BelongsTo
    {
        return $this->belongsTo(Registration::class, 'registration_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(CompanyDepartment::class, 'department_id');
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(StudentAttendance::class, 'student_company_id');
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class, 'student_company_id');
    }

    public function fieldVisits(): HasMany
    {
        return $this->hasMany(FieldVisit::class, 'student_company_id');
    }

    public function scopeWithAttendanceDays($query)
    {
        $table = $this->getTable();

        return $query->addSelect([
            'attendance_days' => StudentAttendance::query()
                ->selectRaw('COUNT(DISTINCT attendance_date)')
                ->whereColumn('student_company_id', "{$table}.id")
                ->whereNotNull('attendance_date')
                ->whereNotNull('check_in'),
        ]);
    }

    public function scopeWhereAttendanceDays(Builder $query, string $operator, int $days): Builder
    {
        $allowedOperators = ['>=', '<=', '=', '>', '<'];

        if (! in_array($operator, $allowedOperators, true)) {
            $operator = '=';
        }

        $table = $this->getTable();
        $attendanceTable = (new StudentAttendance())->getTable();

        return $query->whereRaw(
            "(
                SELECT COUNT(DISTINCT {$attendanceTable}.attendance_date)
                FROM {$attendanceTable}
                WHERE {$attendanceTable}.student_company_id = {$table}.id
                    AND {$attendanceTable}.attendance_date IS NOT NULL
                    AND {$attendanceTable}.check_in IS NOT NULL
                    AND {$attendanceTable}.deleted_at IS NULL
            ) {$operator} ?",
            [$days]
        );
    }

    public function scopeWithActualWorkingHours($query)
    {
        $table = $this->getTable();

        return $query->addSelect([
            'actual_working_hours' => StudentAttendance::selectRaw(

                'ROUND(COALESCE(SUM(TIMESTAMPDIFF(MINUTE, check_in, check_out)), 0) / 60, 2)'
            )
                ->whereColumn('student_company_id', "{$table}.id")
                ->whereNotNull('check_in')
                ->whereNotNull('check_out'),
        ]);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'student_company_id');
    }

    public function surveyAnswers(): HasMany
    {
        return $this->hasMany(SurveyAnswer::class, 'student_company_id');
    }

    public function scopeWithTotalPaymentAmount($query)
    {
        return $query->withSum('payments as total_payment_amount', 'payment_value');
    }

    public function scopeWithTotalPaymentSummary($query)
    {
        return $query->with(['payments.currency']);
    }

    public function scopeWithAttendaceLeavesDays($query)
    {
        return $query->withCount([
            'attendances as total_attendance_leaves_days' => function (Builder $query) {
                $query->where('status', AttendanceStatus::DISCREPANCY);
            },
        ]);
    }
}
