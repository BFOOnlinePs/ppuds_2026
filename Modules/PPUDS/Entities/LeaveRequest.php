<?php

namespace Modules\PPUDS\Entities;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Modules\Core\Entities\User;
use Modules\Core\Enums\ImageQuality;
use Modules\Core\Enums\ImageSize;
use Modules\Core\Services\ImageService;
use Modules\PPUDS\Enums\LeaveRequestStatus;
use Modules\PPUDS\Enums\LeaveRequestType;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class LeaveRequest extends Model implements HasMedia
{
    use LogsActivity;
    use SoftDeletes;
    use InteractsWithMedia;

    public const ATTACHMENT_COLLECTION = 'attachment_file';
    public const ATTACHMENT_DISK = 'leave_requests';

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
        'rejection_reason',
        'company_supervisor_id',
        'university_supervisor_id',
        'company_supervisor_comment',
        'university_supervisor_comment',
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

    public function registerMediaConversions(?Media $media = null): void
    {
        if ($media === null || ! str_starts_with((string) $media->mime_type, 'image/')) {
            return;
        }

        $this
            ->addMediaConversion(self::ATTACHMENT_COLLECTION)
            ->fit(Fit::Contain, 300, 300)
            ->nonQueued();
    }

    public function addAttachment($file): ?Media
    {
        if (is_array($file)) {
            $file = reset($file);
        }

        if (
            ! $file instanceof UploadedFile &&
            ! $file instanceof TemporaryUploadedFile
        ) {
            return null;
        }

        $this->clearMediaCollection(self::ATTACHMENT_COLLECTION);

        try {
            $originalName = $file->getClientOriginalName();
            $extension = $file->getClientOriginalExtension();
            $baseName = Str::slug(pathinfo($originalName, PATHINFO_FILENAME)) ?: 'attachment';
            $fileName = time() . '_' . $baseName . '.' . $extension;

            $media = $this
                ->addMedia($file)
                ->usingFileName($fileName)
                ->toMediaCollection(self::ATTACHMENT_COLLECTION, self::ATTACHMENT_DISK);

            if (str_starts_with((string) $media->mime_type, 'image/')) {
                $this->optimizeAttachmentImage($media);
            }

            return $media;
        } catch (Throwable $e) {
            Log::error('Error uploading attachment file: ' . $e->getMessage());
            return null;
        }
    }

    protected function optimizeAttachmentImage(Media $media): void
    {
        try {
            $size = ImageSize::MEDIUM;

            ImageService::optimize($media->getPath(), ImageQuality::HIGH->value);
            ImageService::resize($media->getPath(), $size->width(), $size->height());
        } catch (Throwable $e) {
            Log::warning('Leave request attachment image optimization skipped: ' . $e->getMessage(), [
                'media_id' => $media->id,
            ]);
        }
    }

    public function addImage($file): ?Media
    {
        return $this->addAttachment($file);
    }

    public function getAttachmentFileAttribute(): ?string
    {
        return $this->getFirstMediaUrl(self::ATTACHMENT_COLLECTION) ?: null;
    }

    public function getAttachmentFileNameAttribute(): ?string
    {
        return $this->getFirstMedia(self::ATTACHMENT_COLLECTION)?->file_name;
    }

    public function getAttachmentFileMimeTypeAttribute(): ?string
    {
        return $this->getFirstMedia(self::ATTACHMENT_COLLECTION)?->mime_type;
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function studentCompany(): BelongsTo
    {
        return $this->belongsTo(StudentCompany::class, 'student_company_id');
    }

    public function scopeForUniversitySupervisor(Builder $query, int|array $supervisorId): Builder
    {
        $supervisorIds = collect(Arr::wrap($supervisorId))
            ->filter(fn(mixed $value): bool => filled($value))
            ->map(fn(mixed $value): int => (int) $value)
            ->values();

        if ($supervisorIds->isEmpty()) {
            return $query;
        }

        return $query->whereHas('studentCompany.registration', function (Builder $registrationQuery) use ($supervisorIds): void {
            if ($supervisorIds->count() === 1) {
                $registrationQuery->where('supervisor_id', $supervisorIds->first());

                return;
            }

            $registrationQuery->whereIn('supervisor_id', $supervisorIds->all());
        });
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

    public function companySupervisor()
    {
        return $this->belongsTo(User::class, 'company_supervisor_id');
    }

    public function universitySupervisor()
    {
        return $this->belongsTo(User::class, 'university_supervisor_id');
    }
}
