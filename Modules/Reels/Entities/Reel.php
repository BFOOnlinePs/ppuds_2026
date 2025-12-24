<?php

namespace Modules\Reels\Entities;

use Astrotomic\Translatable\Translatable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Branch\Entities\Branch;
use Modules\Core\Entities\User;
use Modules\Items\Entities\Category;
use Modules\Items\Entities\Product;
use Modules\Marketing\Enums\LoyaltyRuleType;
use Modules\Reels\Enums\ReelStatus;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Reel extends Model implements HasMedia
{
    use LogsActivity;
    use InteractsWithMedia;

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setTable(config('reels.table_prefix') . 'reels');
    }

    protected $fillable = [
        'id',
        'user_id',
        'status',
        'rejection_reason',
        'views_count',
        'is_visible',
        'sort_order',
    ];

    protected $casts = [
        'status' => ReelStatus::class,
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

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('reels_video')
            ->singleFile()
            ->useDisk('public')
            ->acceptsMimeTypes([
                'video/mp4',
                'video/quicktime',
                'video/x-m4v',
                'video/webm'
            ]);
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumbnail')
            ->width(480)
            ->height(854)
            ->extractVideoFrameAtSecond(0)
            ->performOnCollections('reels_video')
            ->nonQueued();
    }

    public function getVideoAttribute()
    {
        return $this->getFirstMediaUrl('reels_video');
    }

    public function getThumbnailAttribute()
    {
        return $this->getFirstMediaUrl('reels_video', 'thumbnail');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
