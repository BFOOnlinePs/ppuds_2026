<?php

namespace Modules\PPUDS\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Modules\Core\Entities\User;
use Modules\Core\Enums\ImageQuality;
use Modules\Core\Enums\ImageSize;
use Modules\Core\Services\ImageService;
use Modules\PPUDS\Enums\StudentGender;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Throwable;

class StudentProfile extends Model implements HasMedia
{
    use InteractsWithMedia;
    use LogsActivity;
    use SoftDeletes;

    public const ATTACHMENTS_COLLECTION = 'attachments';

    public const ATTACHMENTS_DISK = 'student_profiles';

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setTable(config('ppuds.table_prefix').'student_profiles');
    }

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'user_id',
        'dob',
        'gender',
        'cv_status',
        'tawjihi_gpa',
        'student_number',
        'enrollment_year',
        'semester_level',
        'major_id',
        'linkedin_url',
        'behance_url',
        'github_url',
    ];

    protected $casts = [
        'dob' => 'date',
        'gender' => StudentGender::class,
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly($this->getFillable())
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $eventName) => "This model has been {$eventName} and value ")
            ->useLogName(class_basename($this));
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('cv')
            ->singleFile();

        $this->addMediaCollection(self::ATTACHMENTS_COLLECTION)
            ->useDisk(self::ATTACHMENTS_DISK);
    }

    public function addImage($file)
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

        $this->clearMediaCollection('cv');

        try {
            $originalName = $file->getClientOriginalName();
            $extension = $file->getClientOriginalExtension();
            $fileName = time().'_'.Str::slug(pathinfo($originalName, PATHINFO_FILENAME)).'.'.$extension;

            $media = $this
                ->addMedia($file)
                ->usingFileName($fileName)
                ->toMediaCollection('cv', 'student_profiles');

            $size = ImageSize::MEDIUM;

            ImageService::optimize($media->getPath(), ImageQuality::HIGH->value);
            ImageService::resize($media->getPath(), $size->width(), $size->height());

            return $media;
        } catch (\Exception $e) {
            Log::error('Error uploading student profile cv: '.$e->getMessage());

            return null;
        }
    }

    public function addAttachment($file, ?string $name = null): ?Media
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

        try {
            $originalName = $file->getClientOriginalName();
            $extension = strtolower($file->getClientOriginalExtension());
            $baseName = Str::slug(pathinfo($originalName, PATHINFO_FILENAME)) ?: 'attachment';
            $fileName = now()->format('YmdHis').'_'.Str::random(8).'_'.$baseName.'.'.$extension;
            $mediaName = blank($name) ? pathinfo($originalName, PATHINFO_FILENAME) : $name;

            return $this
                ->copyMedia($file)
                ->usingName($mediaName)
                ->usingFileName($fileName)
                ->toMediaCollection(self::ATTACHMENTS_COLLECTION, self::ATTACHMENTS_DISK);
        } catch (Throwable $e) {
            Log::error('Error uploading student profile attachment: '.$e->getMessage(), [
                'student_profile_id' => $this->getKey(),
            ]);

            return null;
        }
    }

    public function getAttachmentUrls(): Collection
    {
        return $this
            ->getMedia(self::ATTACHMENTS_COLLECTION)
            ->map(fn (Media $media): string => $media->getUrl());
    }

    public function getAttachmentItems(): Collection
    {
        return $this
            ->getMedia(self::ATTACHMENTS_COLLECTION)
            ->map(fn (Media $media): array => [
                'id' => $media->id,
                'url' => $media->getUrl(),
                'name' => $media->name,
                'file_name' => $media->file_name,
                'mime_type' => $media->mime_type,
                'size' => $media->size,
                'human_readable_size' => $media->human_readable_size,
                'is_image' => str_starts_with((string) $media->mime_type, 'image/'),
            ]);
    }

    public function getCvUrlAttribute()
    {
        return $this->getFirstMediaUrl('cv');
    }

    public function getUserDisplayHtmlAttribute(): HtmlString
    {
        return $this->user?->getUserDisplayHtmlAttribute() ?? new HtmlString('---');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function major(): BelongsTo
    {
        return $this->belongsTo(Major::class);
    }
}
