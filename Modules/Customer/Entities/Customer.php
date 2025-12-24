<?php

namespace Modules\Customer\Entities;

use ArPHP\I18N\Arabic;
use Astrotomic\Translatable\Translatable;
use Astrotomic\Translatable\Contracts\Translatable as TranslatableContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Intervention\Image\Encoders\PngEncoder;
use Laravolt\Avatar\Avatar;
use Modules\Clinic\Entities\ProgramCustomer;
use Modules\Clinic\Entities\Reading;
use Modules\Clinic\Entities\Response;
use Modules\Core\Entities\User;
use Modules\Core\Enums\ImageQuality;
use Modules\Core\Enums\ImageSize;
use Modules\Core\Services\ImageService;
use Modules\Customer\Enums\GenderType;
use Modules\Customer\Enums\Language;
use Modules\Customer\Enums\Status;
use Modules\GeoLocation\Entities\City;
use Modules\GeoLocation\Entities\Country;
use Modules\GeoLocation\Entities\District;
use Modules\GeoLocation\Entities\Governorate;
use Modules\Items\Enums\AttributeType;
use Modules\Subscription\Entities\Subscription;
use Modules\Subscription\Entities\SubscriptionFreeze;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

// use Modules\Items\Database\Factories\AttributeFactory;

class Customer extends Model implements HasMedia
{
    use HasFactory;
    use LogsActivity;
    use InteractsWithMedia;

    /**
     * The attributes that are mass assignable.
     */

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setTable(config('clinic.table_prefix') . 'customer_profiles');
    }

    protected $fillable = [
        'date_of_birth',
        'gender',
        'address',
        'district_id',
        'city_id',
        'governorate_id',
        'country_id',
        'status',
        'notes',
        'language',
        'created_by',
    ];

    protected $casts = [
        'gender' => GenderType::class,
        'status' => Status::class,
        'language' => Language::class,
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

    public function registerAllMediaConversions(): void
    {
        $this->addMediaCollection('customer_image')
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
        $this->clearMediaCollection('customer_image');

        try {
            $originalName = $file->getClientOriginalName();
            $extension = $file->getClientOriginalExtension();
            $fileName = time() . '_' . Str::slug(pathinfo($originalName, PATHINFO_FILENAME)) . '.' . $extension;

            $media = $this
                ->addMedia($file)
                ->usingFileName($fileName)
                ->toMediaCollection('customer_image', 'customers');

            $size = ImageSize::MEDIUM;

            ImageService::optimize($media->getPath() , ImageQuality::HIGH->value);
            ImageService::resize($media->getPath() , $size->width(), $size->height());

            return $media;
        } catch (\Exception $e) {
            \Log::error('Error uploading customer image: ' . $e->getMessage());
            return null;
        }
    }

    public function getImageAttribute()
    {
        return $this->getFirstMediaUrl('customer_image');
    }

    public function getNameAttribute()
    {
        return $this->user->name;
    }

    public function getEmailAttribute()
    {
        return $this->user->email;
    }

    public function getAvatarUrlAttribute(): string
    {
        $mediaUrl = $this->getFirstMediaUrl('avatar');
        if ($mediaUrl) {
            return $mediaUrl;
        }

        return $this->generateDefaultAvatar();
    }

    protected function getAvatarName(): string
    {
        return $this->getNameAttribute();
    }

    protected function getDefaultRandomColor(): string
    {
        $colors = [
            '#f44336', '#E91E63', '#9C27B0', '#673AB7', '#3F51B5',
            '#2196F3', '#03A9F4', '#00BCD4', '#009688', '#4CAF50',
            '#8BC34A', '#CDDC39', '#FFC107', '#FF9800', '#FF5722'
        ];

        return $colors[array_rand($colors)];
    }

    private function generateInitials(string $name): array
    {
        $name = trim($name);
        $words = explode(' ', $name);
        $initialsArray = [];
        $isArabic = (bool) preg_match('/[\p{Arabic}]/u', $name);

        // Get the first letter of the first word
        if (!empty($words[0])) {
            $initialsArray[] = mb_strtoupper(mb_substr($words[0], 0, 1));
        }

        // Get the first letter of the last word if there's more than one word
        if (count($words) > 1) {
            $initialsArray[] = mb_strtoupper(mb_substr(end($words), 0, 1));
        }

        $initials = implode('', $initialsArray);

        return ['initials' => $initials, 'is_arabic' => $isArabic];
    }

    protected function generateDefaultAvatar(): string
    {
        // 1. Get the full name and generate initials data
        $name = $this->getAvatarName();
        $initialsData = $this->generateInitials($name);
        $initials = $initialsData['initials'];
        $isArabic = $initialsData['is_arabic'];

        $processedText = $initials;

        if ($isArabic) {
            $arabic = new Arabic('Glyphs');
            $processedText = $arabic->utf8Glyphs($initials);
        }

        $avatar = new Avatar(config('laravolt.avatar'));
        $image = $avatar->create($processedText)
            ->setDimension(200, 200)
            ->getImageObject();

        return (string) $image->encode(new PngEncoder());
    }

    public function generateAvatar(): void
    {
        $pngData = $this->generateDefaultAvatar();

        $this->addMediaFromString($pngData)
            ->usingFileName("user-{$this->id}-avatar.png")
            ->toMediaCollection('customer_image', 'customers');
    }

    public function readings(): HasMany
    {
        return $this->hasMany(Reading::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function responses(): HasMany
    {
        return $this->hasMany(Response::class);
    }

    public function programCustomers(): HasMany
    {
        return $this->hasMany(ProgramCustomer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    public function governorate(): BelongsTo
    {
        return $this->belongsTo(Governorate::class);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function getCountryNameAttribute(): string
    {
        return $this->country->name;
    }

    public function getCityNameAttribute(): string
    {
        return $this->city->name;
    }

    public function getDistrictNameAttribute(): string
    {
        return $this->district->name;
    }

    public function getGovernorateNameAttribute(): string
    {
        return $this->governorate->name;
    }

    public function freezes(): HasMany
    {
        return $this->hasMany(SubscriptionFreeze::class);
    }

    protected function overallSubscriptionStatus(): \Illuminate\Database\Eloquent\Casts\Attribute
    {
        return \Illuminate\Database\Eloquent\Casts\Attribute::make(
            get: function (): string|\Modules\Subscription\Enums\Status {
                $statuses = $this->subscriptions->pluck('status');

                if ($statuses->isEmpty()) {
                    return 'لا يوجد اشتراك';
                }

                if ($statuses->contains(\Modules\Subscription\Enums\Status::ACTIVE)) {
                    return \Modules\Subscription\Enums\Status::ACTIVE;
                }

                if ($statuses->contains(\Modules\Subscription\Enums\Status::FROZEN)) {
                    return \Modules\Subscription\Enums\Status::FROZEN;
                }

                // يمكنك إضافة باقي الحالات هنا
                return \Modules\Subscription\Enums\Status::EXPIRED;
            },
        );
    }
}
