<?php

namespace Modules\Items\Entities;

use Astrotomic\Translatable\Translatable;
use Astrotomic\Translatable\Contracts\Translatable as TranslatableContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Modules\Core\Entities\User;
use Modules\Items\Enums\AttributeType;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

// use Modules\Items\Database\Factories\AttributeFactory;

class Brand extends Model implements TranslatableContract , HasMedia
{
    use HasFactory;
    use LogsActivity;
    use Translatable;
    use InteractsWithMedia;
    /**
     * The attributes that are mass assignable.
     */

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setTable(config('items.table_prefix') . 'brands');
    }

    protected $fillable = [
        'slug',
        'created_by',
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

    protected static function booted()
    {
        parent::booted();

        static::creating(function ($model) {
            if (empty($model->slug)) {
                $model->slug = $model->generateUniqueSlug();
            }
        });

        static::created(function ($model) {
            $locale = app()->getLocale();
            $translationData = request()->only($model->translatedAttributes);

            if (!empty($translationData)) {
                $model->translateOrNew($locale)->fill($translationData);
                $model->save();
            }
        });
    }

    public function generateUniqueSlug()
    {
        $slug = Str::slug($this->name);
        $count = static::where('slug', 'like', $slug . '%')->count();
        return $count ? $slug . '-' . $count : $slug;
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('brand')
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/gif', 'image/webp']);
    }

/*************  ✨ Windsurf Command ⭐  *************/
    /**
     * Upload a brand image.
     *
     * @param array|\Illuminate\Http\UploadedFile|\Livewire\Features\SupportFileUploads\TemporaryUploadedFile $file
     * @return \Spatie\MediaLibrary\MediaCollections\Models\Media|null
     */
/*******  70a8a3b8-825d-4a6c-8087-cf20646cda83  *******/    public function addImage($file)
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
        $this->clearMediaCollection('brand');

        try {
            $originalName = $file->getClientOriginalName();
            $extension = $file->getClientOriginalExtension();
            $fileName = time() . '_' . Str::slug(pathinfo($originalName, PATHINFO_FILENAME)) . '.' . $extension;

            $media = $this
                ->addMedia($file)
                ->usingFileName($fileName)
                ->toMediaCollection('brand', 'items');

            return $media;
        } catch (\Exception $e) {
            \Log::error('Error uploading brand image: ' . $e->getMessage());
            return null;
        }
    }

    public function getImageAttribute()
    {
        return $this->getFirstMediaUrl('brand');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'brand_id');
    }
}
