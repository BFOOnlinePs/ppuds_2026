<?php

namespace Modules\PPUDS\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Modules\Core\Entities\User;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Throwable;

class FieldVisit extends Model implements HasMedia
{
    use LogsActivity;
    use SoftDeletes;
    use InteractsWithMedia;

    public const ATTACHMENTS_COLLECTION = 'attachments';
    public const ATTACHMENTS_DISK = 'field_visits';

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

    public function registerMediaCollections(): void
    {
        $this
            ->addMediaCollection(self::ATTACHMENTS_COLLECTION)
            ->useDisk(self::ATTACHMENTS_DISK);
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
            $fileName = now()->format('YmdHis') . '_' . Str::random(8) . '_' . $baseName . '.' . $extension;
            $mediaName = blank($name) ? pathinfo($originalName, PATHINFO_FILENAME) : $name;

            return $this
                ->addMedia($file)
                ->usingName($mediaName)
                ->usingFileName($fileName)
                ->toMediaCollection(self::ATTACHMENTS_COLLECTION, self::ATTACHMENTS_DISK);
        } catch (Throwable $e) {
            Log::error('Error uploading field visit attachment: ' . $e->getMessage(), [
                'field_visit_id' => $this->getKey(),
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
