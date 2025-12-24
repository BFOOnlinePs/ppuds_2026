<?php

namespace Modules\Clinic\Entities;

use Astrotomic\Translatable\Translatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Clinic\Enums\RoomStatus;
use Modules\Core\Entities\User;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

// use Modules\Clinic\Database\Factories\DiseaseFactory;

class Disease extends Model
{
    use LogsActivity;
    use Translatable;
    use softDeletes;

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setTable(config('clinic.table_prefix') . 'diseases');
    }

    protected $fillable = [
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
}
