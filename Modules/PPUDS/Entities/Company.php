<?php

namespace Modules\PPUDS\Entities;

use ArPHP\I18N\Arabic;
use Astrotomic\Translatable\Contracts\Translatable as TranslatableContract;
use Astrotomic\Translatable\Translatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Intervention\Image\Encoders\PngEncoder;
use Laravolt\Avatar\Avatar;
use Modules\Branch\Entities\Branch;
use Modules\Core\Entities\User;
use Modules\Core\Enums\ImageQuality;
use Modules\Core\Enums\ImageSize;
use Modules\Core\Services\ImageService;
use Modules\PPUDS\Enums\CompanyStatus;
use Modules\PPUDS\Settings\GeneralSettings;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Company extends Model implements HasMedia, TranslatableContract
{
    use InteractsWithMedia;
    use LogsActivity;
    use softDeletes;
    use Translatable;

    private const DEFAULT_COVER_PHOTO_PATH = 'images/defaults/company-cover.jpeg';

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setTable(config('ppuds.table_prefix').'companies');
    }

    protected $fillable = [
        'id',
        'old_company_id',
        'website',
        'contact_person',
        'contact_info',
        'company_category_id',
        'status',
        'created_by',
    ];

    protected $casts = [
        'old_company_id' => 'integer',
        'status' => CompanyStatus::class,
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
            ->setDescriptionForEvent(fn (string $eventName) => "This model has been {$eventName} and value ")
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

            if (! empty($translationData)) {
                $model->translateOrNew($locale)->fill($translationData);
                $model->save();
            }
        });
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this
            ->addMediaConversion('logo')
            ->fit(Fit::Contain, 300, 300)
            ->nonQueued();

        $this
            ->addMediaConversion('cover_photo')
            ->fit(Fit::Contain, 1200, 600)
            ->nonQueued();
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('logo')
            ->singleFile();

        $this->addMediaCollection('cover_photo')
            ->useFallbackUrl(asset(self::DEFAULT_COVER_PHOTO_PATH))
            ->useFallbackPath(public_path(self::DEFAULT_COVER_PHOTO_PATH))
            ->singleFile();

        $this->addMediaCollection('attachments')
            ->useDisk('companies');
    }

    public function addImage($file)
    {
        if (is_array($file)) {
            $file = reset($file);
        }

        // التحقق من نوع الملف
        if (
            ! $file instanceof \Illuminate\Http\UploadedFile &&
            ! ($file instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile)
        ) {
            return null;
        }

        // مسح الصورة السابقة
        $this->clearMediaCollection('logo');

        try {
            $originalName = $file->getClientOriginalName();
            $extension = $file->getClientOriginalExtension();
            $fileName = time().'_'.Str::slug(pathinfo($originalName, PATHINFO_FILENAME)).'.'.$extension;

            $media = $this
                ->addMedia($file)
                ->usingFileName($fileName)
                ->toMediaCollection('logo', 'companies');

            $size = ImageSize::MEDIUM;

            ImageService::optimize($media->getPath(), ImageQuality::HIGH->value);
            ImageService::resize($media->getPath(), $size->width(), $size->height());

            return $media;
        } catch (\Exception $e) {
            \Log::error('Error uploading product image: '.$e->getMessage());

            return null;
        }
    }

    public function addAttachment($file, ?string $name = null): ?Media
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

        try {
            $originalName = $file->getClientOriginalName();
            $extension = $file->getClientOriginalExtension();
            $fileName = time().'_'.Str::slug(pathinfo($originalName, PATHINFO_FILENAME)).'.'.$extension;
            $mediaName = blank($name) ? pathinfo($originalName, PATHINFO_FILENAME) : $name;

            return $this
                ->addMedia($file)
                ->usingName($mediaName)
                ->usingFileName($fileName)
                ->toMediaCollection('attachments', 'companies');
        } catch (\Exception $e) {
            \Log::error('Error uploading company attachment: '.$e->getMessage());

            return null;
        }
    }

    public function getImageAttribute(): string
    {
        return $this->hasMedia('logo')
            ? $this->getFirstMediaUrl('logo')
            : $this->getDefaultLogoUrlAttribute();
    }

    public function getDefaultLogoUrlAttribute(): string
    {
        return 'data:image/png;base64,'.base64_encode($this->generateDefaultLogo());
    }

    public function generateLogo(): void
    {
        $this->addMediaFromString($this->generateDefaultLogo())
            ->usingFileName("company-{$this->id}-logo.png")
            ->toMediaCollection('logo', 'media');
    }

    protected function generateDefaultLogo(): string
    {
        $initialsData = $this->generateInitials($this->getLogoName());
        $processedText = $initialsData['initials'];

        if ($initialsData['is_arabic']) {
            $processedText = (new Arabic('Glyphs'))->utf8Glyphs($processedText);
        }

        $image = (new Avatar(config('laravolt.avatar')))
            ->create($processedText)
            ->setDimension(300, 300)
            ->setFontSize(100)
            ->getImageObject();

        return (string) $image->encode(new PngEncoder);
    }

    protected function getLogoName(): string
    {
        return $this->name ?: ($this->website ?: 'Company_'.$this->id);
    }

    private function generateInitials(string $name): array
    {
        $name = trim($name);
        $isArabic = (bool) preg_match('/[\p{Arabic}]/u', $name);
        $initials = mb_strtoupper(mb_substr($name, 0, 1));

        return ['initials' => $initials, 'is_arabic' => $isArabic];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(CompanyCategory::class, 'company_category_id');
    }

    public function studentCompanies(): HasMany
    {
        return $this->hasMany(StudentCompany::class, 'company_id');
    }

    public function currentStudentCompanies(): HasMany
    {
        $settings = app(GeneralSettings::class);

        return $this->studentCompanies()
            ->whereHas('registration', function (Builder $query) use ($settings) {
                $query->where('semester', $settings->semester_type?->value)
                    ->where('year', $settings->year);
            });
    }

    public function companySupervisors(): Collection
    {
        $branches = $this->relationLoaded('branches')
            ? $this->branches
            : $this->branches()->with('supervisors')->get();

        return $branches
            ->flatMap(function (Branch $branch) {
                return $branch->relationLoaded('supervisors')
                    ? $branch->supervisors
                    : $branch->supervisors()->get();
            })
            ->unique('id')
            ->values();
    }

    public function companySupervisorNames(): array
    {
        return $this->companySupervisors()
            ->pluck('name')
            ->filter()
            ->values()
            ->all();
    }

    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class, 'ppu_ds_branch_company')
            ->withPivot('is_main')
            ->withTimestamps();
    }

    public function students(): BelongsToMany
    {
        return $this->belongsToMany(User::class, config('ppuds.table_prefix').'company_student', 'company_id', 'student_id');
    }

    public function registrations(): BelongsToMany
    {
        return $this->belongsToMany(
            Registration::class,
            'ppu_ds_students_companies',
            'company_id',
            'registration_id'
        );
    }

    public function scopeHasCurrentStudents($query)
    {
        $settings = app(GeneralSettings::class);

        return $query->whereHas('registrations', function ($q) use ($settings) {
            $q->where('semester', $settings->semester_type?->value)
                ->where('year', $settings->year);
        });
    }

    public function scopeWithCurrentStudentsCount(Builder $query): Builder
    {
        $settings = app(GeneralSettings::class);
        $companiesTable = $this->getTable();
        $studentCompaniesTable = (new StudentCompany)->getTable();

        return $query->addSelect([
            'current_students_count' => StudentCompany::query()
                ->selectRaw("COUNT(DISTINCT {$studentCompaniesTable}.student_id)")
                ->whereColumn("{$studentCompaniesTable}.company_id", "{$companiesTable}.id")
                ->whereHas('registration', function (Builder $query) use ($settings) {
                    $query->where('semester', $settings->semester_type?->value)
                        ->where('year', $settings->year);
                }),
        ]);
    }
}
