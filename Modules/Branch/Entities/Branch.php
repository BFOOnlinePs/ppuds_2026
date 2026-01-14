<?php

namespace Modules\Branch\Entities;

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
use Modules\Branch\Enums\BranchStatus;
use Modules\Clinic\Enums\RoomStatus;
use Modules\Core\Entities\User;
use Modules\Core\Enums\ImageQuality;
use Modules\Core\Enums\ImageSize;
use Modules\Core\Services\ImageService;
use Modules\Customer\Enums\GenderType;
use Modules\Customer\Enums\Language;
use Modules\Customer\Enums\Status;
use Modules\GeoLocation\Entities\City;
use Modules\GeoLocation\Entities\Country;
use Modules\Items\Enums\AttributeType;
use Modules\PPUDS\Entities\Company;
use Modules\PPUDS\Entities\CompanyDepartment;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Illuminate\Database\Eloquent\SoftDeletes;

// use Modules\Items\Database\Factories\AttributeFactory;

class Branch extends Model implements TranslatableContract
{
    use LogsActivity;
    use Translatable;
    use softDeletes;

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setTable(config('branch.table_prefix') . 'branches');
    }

    protected $fillable = [
        'phone',
        'email',
        'city_id',
        'country_id',
        'latitude',
        'longitude',
        'opening_time',
        'closing_time',
        'status',
        'created_by',
    ];

    protected $casts = [
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'status' => BranchStatus::class,
    ];

    public $translatedAttributes = [
        'name',
        'description',
        'address',
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

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class, 'city_id');
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'country_id');
    }

    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class, 'ppu_ds_branch_company')
            ->withPivot('is_main')
            ->withTimestamps();
    }

    public function departments(): HasMany
    {
        return $this->hasMany(CompanyDepartment::class);
    }
}
