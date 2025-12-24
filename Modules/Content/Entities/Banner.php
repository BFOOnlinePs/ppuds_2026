<?php

namespace Modules\Content\Entities;

use Astrotomic\Translatable\Translatable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Modules\Branch\Entities\Branch;
use Modules\Clinic\Enums\RoomStatus;
use Modules\Core\Entities\User;
use Modules\Core\Enums\ImageQuality;
use Modules\Core\Enums\ImageSize;
use Modules\Core\Services\ImageService;
use Modules\Items\Entities\Category;
use Modules\Items\Entities\Product;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

// use Modules\Clinic\Database\Factories\DiseaseFactory;

class Banner extends Model implements HasMedia
{
    use LogsActivity;
    use Translatable;
    use softDeletes;
    use InteractsWithMedia;

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setTable(config('content.table_prefix') . 'banners');
    }

    protected $fillable = [
        'id',
        'branch_id',
        'url',
        'active',
        'bannable_type',
        'bannable_id',
        'created_by',
    ];

    protected $casts = [
        'active' => 'boolean'
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

    public function registerAllMediaConversions(): void
    {
        $this->addMediaCollection('banner')
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
        $this->clearMediaCollection('banner');

        try {
            $originalName = $file->getClientOriginalName();
            $extension = $file->getClientOriginalExtension();
            $fileName = time() . '_' . Str::slug(pathinfo($originalName, PATHINFO_FILENAME)) . '.' . $extension;

            $media = $this
                ->addMedia($file)
                ->usingFileName($fileName)
                ->toMediaCollection('banner', 'banners');

            $size = ImageSize::MEDIUM;

            ImageService::optimize($media->getPath() , ImageQuality::HIGH->value);
            ImageService::resize($media->getPath() , $size->width(), $size->height());

            return $media;
        } catch (\Exception $e) {
            \Log::error('Error uploading banner image: ' . $e->getMessage());
            return null;
        }
    }

    public function getImageAttribute()
    {
        return $this->getFirstMediaUrl('banner');
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

    public function bannable()
    {
        return $this->morphTo();
    }

    public function bannableModel(): Attribute
    {
        return Attribute::make(
            get: function () {
                $map = [
                    Category::class => 'items_category',
                    Product::class => 'items_product',
                ];
                return $map[$this->bannable_type] ?? null;
            }
        );
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }
}
