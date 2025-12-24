<?php

namespace Modules\Items\Entities;

use Astrotomic\Translatable\Contracts\Translatable as TranslatableContract;
use Astrotomic\Translatable\Translatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Modules\Content\Entities\Banner;
use Modules\Core\Entities\User;
use Modules\Core\Enums\ImageQuality;
use Modules\Core\Enums\ImageSize;
use Modules\Core\Enums\ImageWidth;
use Modules\Core\Services\ImageService;
use Modules\Items\Enums\AddonOptionStatus;
use Modules\Items\Enums\CategoryStatus;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

// use Modules\Items\Database\Factories\CategoryFactory;

class AddonOption extends Model implements TranslatableContract, HasMedia
{
    use InteractsWithMedia;
    use Translatable;
    use SoftDeletes;
    use LogsActivity;

    /**
     * The attributes that are mass assignable.
     */

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setTable(config('items.table_prefix') . 'addon_options');
    }

    protected $fillable = [
        'id',
        'addon_id',
        'price',
        'is_default',
        'status',
        'is_quantifiable',
        'created_by',
    ];

    protected $casts = [
        'status' => AddonOptionStatus::class,
    ];

    public $translatedAttributes = [
        'name',
        'description',
    ];

    public $useTranslationFallback = true;

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

    protected static function booted()
    {
        parent::booted();

        static::created(function ($model) {
            $locale = app()->getLocale();
            $translationData = request()->only($model->translatedAttributes);

            if (!empty($translationData)) {
                $model->translateOrNew($locale)->fill($translationData);
                $model->save();
            }
        });
    }

    public function registerAllMediaConversions(): void
    {
        $this->addMediaCollection('addon_option')
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/gif', 'image/webp']);
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
        $this->clearMediaCollection('addon_option');

        try {
            $originalName = $file->getClientOriginalName();
            $extension = $file->getClientOriginalExtension();
            $fileName = time() . '_' . Str::slug(pathinfo($originalName, PATHINFO_FILENAME)) . '.' . $extension;

            $media = $this
                ->addMedia($file)
                ->usingFileName($fileName)
                ->toMediaCollection('addon_option', 'addon_option');

            $size = ImageSize::MEDIUM;

            ImageService::optimize($media->getPath() , ImageQuality::HIGH->value);
            ImageService::resize($media->getPath() , $size->width(), $size->height());

            return $media;
        } catch (\Exception $e) {
            \Log::error('Error uploading product image: ' . $e->getMessage());
            return null;
        }
    }

    public function addGalleryImage($file)
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

        try {
            foreach ($file as $image) {
                $originalName = $image->getClientOriginalName();
                $extension = $image->getClientOriginalExtension();
                $fileName = time() . '_' . Str::slug(pathinfo($originalName, PATHINFO_FILENAME)) . '.' . $extension;

                $media = $this
                    ->addMedia($image)
                    ->usingFileName($fileName)
                    ->toMediaCollection('addon_option', 'items');
            }
        } catch (\Exception $e) {
            \Log::error('Error uploading addon image gallary: ' . $e->getMessage());
            return null;
        }
    }

    public function getImageAttribute()
    {
        return $this->getFirstMediaUrl('addon_option');
    }

    public function addon(): BelongsTo
    {
        return $this->belongsTo(Addon::class);
    }
}
