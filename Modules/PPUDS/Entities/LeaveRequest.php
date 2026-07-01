<?php

namespace Modules\PPUDS\Entities;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
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
        $this
            ->addMediaConversion('attachment_file')
            ->fit(Fit::Contain, 300, 300)
            ->nonQueued();
    }

    public function addImage($file)
    {
        if (is_array($file)) {
            $file = reset($file);
        }

        if (
            !$file instanceof \Illuminate\Http\UploadedFile &&
            !($file instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile)
        ) {
            return null;
        }

        $this->clearMediaCollection('attachment_file');

        try {
            $originalName = $file->getClientOriginalName();
            $extension = $file->getClientOriginalExtension();
            $fileName = time() . '_' . Str::slug(pathinfo($originalName, PATHINFO_FILENAME)) . '.' . $extension;

            $media = $this
                ->addMedia($file)
                ->usingFileName($fileName)
                ->toMediaCollection('attachment_file', 'leave_requests');

            $size = ImageSize::MEDIUM;

            ImageService::optimize($media->getPath(), ImageQuality::HIGH->value);
            ImageService::resize($media->getPath(), $size->width(), $size->height());

            return $media;
        } catch (\Exception $e) {
            Log::error('Error uploading attachment file: ' . $e->getMessage());
            return null;
        }
    }


    public function getAttachmentFileAttribute()
    {
        return $this->getFirstMediaUrl('attachment_file');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function studentCompany(): BelongsTo
    {
        return $this->belongsTo(StudentCompany::class, 'student_company_id');
    }

    public function scopeForUniversitySupervisor(Builder $query, int $supervisorId): Builder
    {
        return $query->whereHas('studentCompany.registration', function (Builder $registrationQuery) use ($supervisorId) {
            $registrationQuery->where('supervisor_id', $supervisorId);
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
