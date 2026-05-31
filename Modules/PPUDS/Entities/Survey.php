<?php

namespace Modules\PPUDS\Entities;

use Astrotomic\Translatable\Contracts\Translatable as TranslatableContract;
use Astrotomic\Translatable\Translatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Core\Entities\User;
use Modules\PPUDS\Enums\SemesterType;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Survey extends Model implements HasMedia, TranslatableContract
{
    use InteractsWithMedia, LogsActivity, SoftDeletes, Translatable;

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->setTable(config('ppuds.table_prefix').'surveys');
    }

    protected $fillable = [
        'serve_group',
        'major_id',
        'start_date',
        'end_date',
        'is_active',
        'semester',
        'year',
        'created_by',
    ];

    protected $casts = [
        'semester' => SemesterType::class,
        'year' => 'integer',
        'is_active' => 'boolean',
        'start_date' => 'datetime',
        'end_date' => 'datetime',
    ];

    public $translatedAttributes = [
        'title',
        'description',
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

        static::creating(function ($model) {
            if (! $model->created_by) {
                $model->created_by = auth()->id();
            }
        });
    }

    public function questions(): HasMany
    {
        return $this->hasMany(SurveyQuestion::class, 'survey_id');
    }

    public function major(): BelongsTo
    {
        return $this->belongsTo(Major::class, 'major_id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(SurveyAnswer::class, 'survey_id');
    }

    public function scopeWithSubmissionStatus(Builder $query, $userId = null)
    {
        $userId = $userId ?? auth()->id();

        return $query->withExists(['answers as is_submitted' => function ($q) use ($userId) {
            $q->where('submitted_by', $userId);
        }]);
    }

    public function hasBeenSubmittedBy($userId, ?int $studentCompanyId = null): bool
    {
        return $this->answers()
            ->where('submitted_by', $userId)
            ->when($studentCompanyId, fn ($query) => $query->where('student_company_id', $studentCompanyId))
            ->exists();
    }
}
