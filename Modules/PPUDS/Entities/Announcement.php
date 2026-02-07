<?php

namespace Modules\PPUDS\Entities;

use Astrotomic\Translatable\Contracts\Translatable as TranslatableContract;
use Astrotomic\Translatable\Translatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Core\Entities\User;
use Modules\Core\Enums\UserRole;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Announcement extends Model implements TranslatableContract, HasMedia
{
    use LogsActivity, Translatable, SoftDeletes, InteractsWithMedia;

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->setTable(config('ppuds.table_prefix') . 'announcements');
    }

    protected $fillable = [
        'target_roles', // تم التعديل إلى الجمع ليطابق عمود JSON
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
        'target_roles' => 'array', // ضروري جداً لتحويل JSON إلى مصفوفة PHP
        'filters' => 'array',
        'published_at' => 'datetime',
        'expires_at' => 'datetime',
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

    public function scopeActive(Builder $query)
    {
        return $query->where('published_at', '<=', now())
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>=', now());
            });
    }

    // السكوب المصحح للتعامل مع JSON
    public function scopeForUser(Builder $query, User $user)
    {
        return $query->active()->where(function ($q) use ($user) {
            // 1. هل دور المستخدم موجود داخل مصفوفة الأدوار المستهدفة؟
            // نستخدم whereJsonContains بدلاً من where العادية
            $q->whereJsonContains('target_roles', $user->role->value);

            // 2. فلترة خاصة للطلاب (التخصص)
            if ($user->role === UserRole::STUDENT) {
                $q->where(function ($subQ) use ($user) {
                    $subQ->whereNull('filters->major_id')
                        ->orWhere('filters->major_id', $user->major_id);
                });
            }
        });
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
}
