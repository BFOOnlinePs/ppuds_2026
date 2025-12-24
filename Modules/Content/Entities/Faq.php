<?php

namespace Modules\Content\Entities;

use Astrotomic\Translatable\Translatable;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Entities\User;
use Spatie\Activitylog\LogOptions;
use Astrotomic\Translatable\Contracts\Translatable as TranslatableContract;
use Spatie\Activitylog\Traits\LogsActivity;

class Faq extends Model implements TranslatableContract
{
    use Translatable;
    use LogsActivity;
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setTable(config('content.table_prefix') . 'faqs');
    }

    protected $fillable = [
        'id',
        'faq_category_id',
        'is_active',
        'sort_order',
        'created_by'
    ];

    public $translatedAttributes = [
        'question',
        'answer'
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

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
