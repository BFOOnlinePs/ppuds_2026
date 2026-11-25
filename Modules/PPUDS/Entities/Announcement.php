<?php

namespace Modules\PPUDS\Entities;

use Astrotomic\Translatable\Contracts\Translatable as TranslatableContract;
use Astrotomic\Translatable\Translatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\Core\Entities\User;
use Modules\Core\Enums\ImageQuality;
use Modules\Core\Enums\ImageSize;
use Modules\Core\Enums\UserRole;
use Modules\Core\Services\ImageService;
use Modules\Core\Settings\GeneralSettings;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Announcement extends Model implements TranslatableContract, HasMedia
{
    use LogsActivity, Translatable, SoftDeletes, InteractsWithMedia;

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->setTable(config('ppuds.table_prefix') . 'announcements');
    }

    protected $fillable = [
        'announcement_category_id',
        'target_roles',
        'filters',
        'published_at',
        'expires_at',
        'is_pinned',
        'created_by',
    ];

    public $translatedAttributes = [
        'name',
        'content'
    ];

    protected $casts = [
        'target_roles' => 'array',
        'filters' => 'array',
        'published_at' => 'datetime',
        'expires_at' => 'datetime',
        'notified_at' => 'datetime',
        'is_pinned' => 'boolean',
    ];

    public $useTranslationFallback = true;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly($this->getFillable())
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName(class_basename($this));
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function category()
    {
        return $this->belongsTo(AnnouncementCategory::class, 'announcement_category_id');
    }

    public function scopeActive(Builder $query)
    {
        return $query->where('published_at', '<=', now())
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>=', now());
            });
    }

    /**
     * Published announcements that one of the user's roles is targeted by.
     * A student sees a major-specific announcement only for their own major.
     */
    public function scopeForUser(Builder $query, User $user)
    {
        $roles = $user->getRoleNames();
        $majorId = $roles->contains(UserRole::STUDENT->value)
            ? $user->studentProfile?->major_id
            : null;

        return $query->active()->where(function (Builder $q) use ($roles, $majorId) {
            if ($roles->isEmpty()) {
                $q->whereRaw('1 = 0');

                return;
            }

            foreach ($roles as $role) {
                $q->orWhere(function (Builder $roleQuery) use ($role, $majorId) {
                    $roleQuery->whereJsonContains('target_roles', $role);

                    if ($role === UserRole::STUDENT->value) {
                        $roleQuery->where(function (Builder $majorQuery) use ($majorId) {
                            $majorQuery->whereNull('filters->major_id')
                                ->when($majorId, fn (Builder $q) => $q->orWhere('filters->major_id', $majorId));
                        });
                    }
                });
            }
        });
    }

    /** Published announcements whose notification has not gone out yet. */
    public function scopeAwaitingNotification(Builder $query)
    {
        return $query->active()->whereNull('notified_at');
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

        static::creating(function ($model) {
            if (!$model->created_by) {
                $model->created_by = auth()->id();
            }
        });
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this
            ->addMediaConversion('announcement_image')
            ->fit(Fit::Contain, 300, 300)
            ->nonQueued();
    }

    public function addImage($file)
    {
        if (is_array($file)) {
            $file = reset($file);
        }

        if (
            !$file instanceof \Illuminate\Http\UploadedFile &&
            !($file instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile)
        ) {
            return null;
        }

        $this->clearMediaCollection('announcement_image');

        try {
            $originalName = $file->getClientOriginalName();
            $extension = $file->guessExtension() ?: 'bin';
            $fileName = time() . '_' . Str::slug(pathinfo($originalName, PATHINFO_FILENAME)) . '.' . $extension;

            $media = $this
                ->addMedia($file)
                ->usingFileName($fileName)
                ->toMediaCollection('announcement_image', 'announcements');

            $size = ImageSize::MEDIUM;

            ImageService::optimize($media->getPath(), ImageQuality::HIGH->value);
            ImageService::resize($media->getPath(), $size->width(), $size->height());

            return $media;
        } catch (\Exception $e) {
            Log::error('Error uploading product image: ' . $e->getMessage());
            return null;
        }
    }


    public function getImageAttribute()
    {
        return $this->getFirstMediaUrl('announcement_image') ?: app(GeneralSettings::class)->site_logo_url;
    }
}
