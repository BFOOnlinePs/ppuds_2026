<?php

namespace Modules\Core\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * One file in the shared media library the admin uploads to directly, rather
 * than through a record that owns it. Spatie's media library is the storage
 * layer, so this model only carries what Spatie does not keep: the alt text
 * and the uploader.
 *
 * Deliberately not soft deleting: removing a file from the library has to
 * free the disk space too, and Spatie only detaches media on a real delete.
 */
class MediaAsset extends Model implements HasMedia
{
    use InteractsWithMedia;
    use LogsActivity;

    /** The single collection every library file is stored in. */
    public const COLLECTION = 'library';

    protected $table = 'media_assets';

    protected $fillable = [
        'alt_text',
        'created_by',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly($this->getFillable())
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName(class_basename($this));
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    protected static function booted()
    {
        parent::booted();

        static::creating(function ($model) {
            if (! $model->created_by) {
                $model->created_by = auth()->id();
            }
        });
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::COLLECTION)
            ->singleFile()
            ->useDisk('media');
    }

    /**
     * Only the grid thumbnail is a conversion. The uploaded file is never
     * optimised or resized — the full size original is the point of the
     * library, and it is what every consumer links to.
     */
    public function registerMediaConversions(?Media $media = null): void
    {
        $this
            ->addMediaConversion('thumb')
            ->fit(Fit::Contain, 400, 400)
            ->nonQueued();
    }

    /**
     * Stores one uploaded file, keeping the original untouched. Image
     * dimensions are read once here and kept as custom properties, so listing
     * the library never has to open the files again.
     */
    public function addFile($file): ?Media
    {
        if (is_array($file)) {
            $file = reset($file);
        }

        if (
            ! $file instanceof UploadedFile &&
            ! ($file instanceof TemporaryUploadedFile)
        ) {
            return null;
        }

        try {
            $originalName = $file->getClientOriginalName();
            $extension = $file->getClientOriginalExtension();
            $baseName = pathinfo($originalName, PATHINFO_FILENAME);
            $fileName = time() . '_' . Str::slug($baseName) . '.' . $extension;

            $dimensions = @getimagesize($file->getRealPath());

            return $this
                ->addMedia($file)
                ->usingName($baseName)
                ->usingFileName($fileName)
                ->withCustomProperties($dimensions ? [
                    'width' => $dimensions[0],
                    'height' => $dimensions[1],
                ] : [])
                ->toMediaCollection(self::COLLECTION, 'media');
        } catch (\Exception $e) {
            Log::error('Error uploading media library file: ' . $e->getMessage());

            return null;
        }
    }

    public function getFileAttribute(): ?Media
    {
        return $this->getFirstMedia(self::COLLECTION);
    }

    /** The full size original, which is what the library hands out. */
    public function getUrlAttribute(): ?string
    {
        return $this->getFirstMediaUrl(self::COLLECTION) ?: null;
    }

    /**
     * Grid preview: the thumbnail when it was generated, the original
     * otherwise — conversions are skipped for anything that is not an image.
     */
    public function getPreviewUrlAttribute(): ?string
    {
        $media = $this->file;

        if (! $media) {
            return null;
        }

        return $media->hasGeneratedConversion('thumb')
            ? $media->getUrl('thumb')
            : $media->getUrl();
    }

    public function getIsImageAttribute(): bool
    {
        return Str::startsWith((string) $this->file?->mime_type, 'image/');
    }

    public function getDimensionsAttribute(): ?string
    {
        $media = $this->file;
        $width = $media?->getCustomProperty('width');
        $height = $media?->getCustomProperty('height');

        return $width && $height ? $width . '×' . $height : null;
    }
}
