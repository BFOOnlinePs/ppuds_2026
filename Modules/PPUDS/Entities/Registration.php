<?php

namespace Modules\PPUDS\Entities;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\Core\Entities\User;
use Modules\Core\Enums\ImageQuality;
use Modules\Core\Enums\ImageSize;
use Modules\Core\Services\ImageService;
use Modules\PPUDS\Enums\SemesterType;
use Modules\PPUDS\Enums\TrainingStatus;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Modules\PPUDS\Entities\Course;

class Registration extends Model implements HasMedia
{
    use LogsActivity;
    use softDeletes;
    use InteractsWithMedia;

    /**
     * ملفا التقرير النهائي. العرض التقديمي إجباري وملف بايثون اختياري،
     * وكلاهما ملف واحد يُستبدل عند رفع نسخة جديدة كما هو حال final_file.
     */
    public const PRESENTATION_COLLECTION = 'final_presentation';

    public const CODE_COLLECTION = 'final_code';

    public const FINAL_FILES_DISK = 'registers';


    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setTable(config('ppuds.table_prefix') . 'registrations');
    }

    protected $fillable = [
        'id',
        'student_id',
        'course_id',
        'grade',
        'semester',
        'year',
        'supervisor_id',
        'university_score',
        'company_score',
        'created_by',
    ];

    public $casts = [
        'semester' => SemesterType::class,
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

    public function registerMediaConversions(?Media $media = null): void
    {
        $this
            ->addMediaConversion('final_file')
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
            !$file instanceof \Illuminate\Http\UploadedFile &&
            !($file instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile)
        ) {
            return null;
        }

        // مسح الصورة السابقة
        $this->clearMediaCollection('final_file');

        try {
            $originalName = $file->getClientOriginalName();
            $extension = $file->getClientOriginalExtension();
            $fileName = time() . '_' . Str::slug(pathinfo($originalName, PATHINFO_FILENAME)) . '.' . $extension;

            $media = $this
                ->addMedia($file)
                ->usingFileName($fileName)
                ->toMediaCollection('final_file', 'registers');

            if (str_starts_with($media->mime_type, 'image/')) {
                $size = ImageSize::MEDIUM;

                ImageService::optimize($media->getPath(), ImageQuality::HIGH->value);
                ImageService::resize($media->getPath(), $size->width(), $size->height());
            }

            return $media;
        } catch (\Exception $e) {
            Log::error('Error uploading registration image: ' . $e->getMessage());
            return null;
        }
    }

    public function getImageAttribute()
    {
        return $this->getFirstMediaUrl('final_file');
    }

    /**
     * العرض التقديمي الإجباري للتقرير النهائي (ppt / pptx).
     */
    public function addPresentation($file): ?Media
    {
        return $this->addFinalReportFile($file, self::PRESENTATION_COLLECTION, 'presentation');
    }

    /**
     * ملف بايثون الاختياري المرافق للتقرير النهائي.
     */
    public function addCode($file): ?Media
    {
        return $this->addFinalReportFile($file, self::CODE_COLLECTION, 'code');
    }

    /**
     * منطق مشترك للملفين: ملف واحد لكل مجموعة يُستبدل عند الرفع، وبلا تحويلات
     * صور لأن العرض التقديمي وملف الشيفرة ليسا صوراً.
     */
    protected function addFinalReportFile($file, string $collection, string $label): ?Media
    {
        if (is_array($file)) {
            $file = reset($file);
        }

        if (
            ! $file instanceof \Illuminate\Http\UploadedFile &&
            ! ($file instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile)
        ) {
            return null;
        }

        $this->clearMediaCollection($collection);

        try {
            $originalName = $file->getClientOriginalName();
            $extension = strtolower($file->getClientOriginalExtension());
            $baseName = Str::slug(pathinfo($originalName, PATHINFO_FILENAME)) ?: $label;
            $fileName = now()->format('YmdHis') . '_' . Str::random(8) . '_' . $baseName . '.' . $extension;

            return $this
                ->addMedia($file)
                ->usingFileName($fileName)
                ->toMediaCollection($collection, self::FINAL_FILES_DISK);
        } catch (\Exception $e) {
            Log::error('Error uploading final report ' . $label . ': ' . $e->getMessage(), [
                'registration_id' => $this->getKey(),
            ]);

            return null;
        }
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class, 'course_id');
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }

    public function finalReport(): HasOne
    {
        return $this->hasOne(FinalReport::class, 'registration_id');
    }

    public function studentCompany(): HasOne
    {
        return $this->hasOne(StudentCompany::class, 'registration_id')
            ->orderByRaw('(status = ?) desc', [TrainingStatus::AVAILABLE->value])
            ->orderByDesc('id');
    }
}
