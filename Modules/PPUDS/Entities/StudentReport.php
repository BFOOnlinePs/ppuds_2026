<?php

namespace Modules\PPUDS\Entities;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Core\Entities\User;
use Modules\Core\Enums\ImageQuality;
use Modules\Core\Enums\ImageSize;
use Modules\Core\Services\ImageService;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class StudentReport extends Model implements HasMedia
{
    use LogsActivity;
    use softDeletes;
    use InteractsWithMedia;
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setTable(config('ppuds.table_prefix') . 'student_reports');
    }

    protected $fillable = [
        'id',
        'student_attendance_id',
        'report_text',
        'company_feedback',
        'academic_feedback',
        'submit_latitude',
        'submit_longitude',
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

    public function registerMediaConversions(?Media $media = null): void
    {
        $this
            ->addMediaConversion('file_report')
            ->fit(Fit::Contain, 300, 300)
            ->optimize()
            ->nonQueued();
    }

    // في ملف Modules\PPUDS\Entities\StudentReport.php

    public function addImage($files)
    {
        // 1. تحويل المدخل إلى مصفوفة دائماً للتعامل الموحد
        if (!is_array($files)) {
            $files = [$files];
        }

        foreach ($files as $file) {
            // التحقق من صحة الملف (سواء كان كائن رفع أو مسار نصي من Livewire)
            if (
                !is_string($file) &&
                !$file instanceof \Illuminate\Http\UploadedFile &&
                !($file instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile)
            ) {
                continue;
            }

            try {
                // تحديد اسم الملف
                if (is_string($file)) {
                    // إذا كان مساراً (String Path)
                    $extension = pathinfo($file, PATHINFO_EXTENSION);
                    $fileName = time() . '_' . \Illuminate\Support\Str::random(10) . '.' . $extension;
                } else {
                    // إذا كان كائناً (UploadedFile)
                    $originalName = $file->getClientOriginalName();
                    $extension = $file->getClientOriginalExtension();
                    $fileName = time() . '_' . \Illuminate\Support\Str::slug(pathinfo($originalName, PATHINFO_FILENAME)) . '.' . $extension;
                }

                // 2. الإضافة للمكتبة (بدون حذف السابق)
                $media = $this
                    ->addMedia($file)
                    ->usingFileName($fileName)
                    ->toMediaCollection('file_report', 'student_reports');

                // 3. تطبيق التحسينات الخاصة بك (Resize & Optimize)
                $size = ImageSize::MEDIUM; // تأكد من استدعاء الكلاس الصحيح

                // تطبيق السيرفس الخاص بك
                ImageService::optimize($media->getPath(), ImageQuality::HIGH->value);
                ImageService::resize($media->getPath(), $size->width(), $size->height());

            } catch (\Exception $e) {
                \Log::error('Error uploading file report : ' . $e->getMessage());
            }
        }
    }

    public function getMultipleImage()
    {
        return $this->getMedia('file_report')->map(function ($media) {
            return $media->getUrl();
        });
    }

    public function getImageAttribute()
    {
        return $this->getFirstMediaUrl('file_report');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function studentAttendance()
    {
        return $this->belongsTo(StudentAttendance::class, 'student_attendance_id');
    }

    public function scopeToday(): Builder
    {
        return $this->whereDate('created_at', now()->toDateString());
    }
}
