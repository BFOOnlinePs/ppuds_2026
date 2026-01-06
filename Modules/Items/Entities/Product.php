<?php

namespace Modules\Items\Entities;

use Astrotomic\Translatable\Contracts\Translatable as TranslatableContract;
use Astrotomic\Translatable\Translatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Modules\Branch\Entities\Branch;
use Modules\Content\Entities\Banner;
use Modules\Core\Entities\User;
use Modules\Core\Enums\ImageQuality;
use Modules\Core\Enums\ImageSize;
use Modules\Core\Enums\ImageWidth;
use Modules\Core\Services\ImageService;
use Modules\Coupon\Interfaces\Discountable;
use Modules\Items\Enums\CategoryStatus;
use Modules\Items\Enums\ProductStatus;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

// use Modules\Items\Database\Factories\ProductFactory;

class Product extends Model implements TranslatableContract, HasMedia, Discountable
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

        $this->setTable(config('items.table_prefix') . 'products');
    }

    protected $fillable = [
        'id',
        'slug',
        'sku',
        'base_price',
        'sale_price',
        'stock_quantity',
        'manage_stock',
        'in_stock',
        'weight',
        'status',
        'is_featured',
        'is_digital',
        'meta_data',
        'parent_id',
        'brand_id',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'status' => ProductStatus::class,
        'meta_data' => 'array',
        'tags' => 'array',
    ];

    public $translatedAttributes = [
        'name',
        'description',
        'short_description',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly($this->getFillable()) // تسجيل الحقول القابلة للتعبئة فقط
            ->logOnlyDirty() // تسجيل الحقول المُعدّلة فقط
            ->dontSubmitEmptyLogs() // عدم حفظ سجلات فارغة
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

        static::creating(function ($model) {
            // تعيين SKU قبل الحفظ
            if (empty($model->sku)) {
                $model->sku = self::generateUniqueSku();
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

    public function registerAllMediaConversions(): void
    {
        $this->addMediaCollection('product')
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/gif', 'image/webp']);

        $this->addMediaCollection('product_gallery')

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
        $this->clearMediaCollection('product');

        try {
            $originalName = $file->getClientOriginalName();
            $extension = $file->getClientOriginalExtension();
            $fileName = time() . '_' . Str::slug(pathinfo($originalName, PATHINFO_FILENAME)) . '.' . $extension;

            $media = $this
                ->addMedia($file)
                ->usingFileName($fileName)
                ->toMediaCollection('product', 'items');

            $size = ImageSize::MEDIUM;

            ImageService::optimize($media->getPath() , ImageQuality::HIGH->value);
            ImageService::resize($media->getPath() , $size->width(), $size->height());

            return $media;
        } catch (\Exception $e) {
            \Log::error('Error uploading product image: ' . $e->getMessage());
            return null;
        }
    }

    public function addGalleryImage($files)
    {
        // التأكد من أن المدخل هو مصفوفة
        if (!is_array($files)) {
            $files = [$files];
        }

        try {
            foreach ($files as $file) {
                // التحقق من نوع الملف
                if (
                    !$file instanceof \Illuminate\Http\UploadedFile &&
                    !($file instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile)
                ) {
                    continue;
                }

                $originalName = $file->getClientOriginalName();
                $extension = $file->getClientOriginalExtension();
                $fileName = time() . '_' . uniqid() . '_' . Str::slug(pathinfo($originalName, PATHINFO_FILENAME)) . '.' . $extension;

                $media = $this
                    ->addMedia($file)
                    ->usingFileName($fileName)
                    ->toMediaCollection('product_gallery', 'items');

                // تحسين وتغيير حجم الصورة
                $size = ImageSize::MEDIUM;
                ImageService::optimize($media->getPath(), ImageQuality::HIGH->value);
                ImageService::resize($media->getPath(), $size->width(), $size->height());
            }

            return true;
        } catch (\Exception $e) {
            \Log::error('Error uploading product gallery images: ' . $e->getMessage());
            return null;
        }
    }

    public function getGalleryAttribute()
    {
        // للـ API: إرجاع مصفوفة URLs
        if (request()->wantsJson() || request()->is('api/*')) {
            return $this->getMedia('product_gallery')->map(function ($media) {
                return $media->getUrl();
            });
        }

        // للـ Filament Form: إرجاع null لأن Filament يتعامل مع Media مباشرة
        return null;
    }

    public function getImageAttribute()
    {
        return $this->getFirstMediaUrl('product');
    }

    public function categories()
    {
        return $this->belongsToMany(Category::class, config('items.table_prefix') . 'products_categories');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'parent_id');
    }

//    public function attributeValue(): BelongsToMany
//    {
//        return $this->belongsToMany(AttributeValue::class, config('items.table_prefix') . 'product_variations', 'product_id', 'attribute_value_id');
//    }

    public function variations(): HasMany
    {
        return $this->hasMany(Product::class, 'parent_id');
    }

    /*************  ✨ Windsurf Command ⭐  *************/
    /**
     * Generate a unique SKU
     *
     * @return string
     */
    /*******  650b6af4-fb5c-48f4-87f1-be182d251b22  *******/
    public static function generateUniqueSku()
    {
        do {
            $sku = 'SKU-' . strtoupper(Str::random(8));
        } while (self::where('sku', $sku)->exists());

        return $sku;
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, config('items.table_prefix') . 'products_tags' , 'product_id', 'tag_id');
    }

    public function labels(): BelongsToMany
    {
        return $this->belongsToMany(Label::class, config('items.table_prefix') . 'products_labels');
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class, 'brand_id');
    }

    public function banners(): MorphMany
    {
        return $this->morphMany(Banner::class, 'bannable');
    }

    public function attributes(): HasMany
    {
        return $this->hasMany(AttributeValue::class, 'attribute_id');
    }

    public function attributeValues(): BelongsToMany
    {
        return $this->belongsToMany(AttributeValue::class, config('items.table_prefix') . 'product_attribute_value', 'product_id', 'attribute_id');
    }

    public function addons(): BelongsToMany
    {
        return $this->belongsToMany(Addon::class, config('items.table_prefix') . 'addon_product', 'product_id', 'addon_id')
            ->withPivot([
                'id',
                'is_required',
                'min_selection',
                'max_selection',
                'max_quantity_per_option',
                'created_by'
            ])
            ->withTimestamps();
    }

    public function addonOptions()
    {
        return $this->hasMany(config('items.table_prefix') . 'product_addon_options', 'addon_product_id', 'id')
            ->withPivot([
                'id',
                'price',
                'is_default',
            ]);
    }

    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class, config('items.table_prefix') . 'branch_product', 'product_id', 'branch_id');
    }

    public function offers(): MorphMany
    {
        return $this->morphMany(Offer::class, 'offerable');
    }

    public function activeOffers(): MorphMany
    {
        return $this->offers()
            ->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('start_date')
                  ->orWhere('start_date', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('end_date')
                  ->orWhere('end_date', '>=', now());
            })
            ->orderBy('created_at', 'desc');
    }


    public function branchProducts(): HasMany
    {
        return $this->hasMany(BranchPrice::class, 'product_id');
    }

    public function scopeWithBranchPrices($query, $branchId)
    {
        $query->with(['branchProducts' => function ($q) use ($branchId) {
            $q->where('branch_id', $branchId);
        }]);
    }

    public function getPriceAttribute()
    {
        if ($this->relationLoaded('branchProducts') && $this->branchProducts->isNotEmpty()) {
            return $this->branchProducts->first()->price;
        }

        return $this->base_price;
    }

    public function getDiscountablePrice(): float
    {
        return $this->price;
    }
}
