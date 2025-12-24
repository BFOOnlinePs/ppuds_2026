<?php

namespace Modules\Core\Entities;

use Astrotomic\Translatable\Translatable;
use Astrotomic\Translatable\Contracts\Translatable as TranslatableContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Modules\Core\Entities\User;
use Modules\Items\Entities\Product;
use Modules\Items\Enums\AttributeType;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\MediaLibrary\InteractsWithMedia;

// use Modules\Items\Database\Factories\AttributeFactory;

class Currency extends Model implements TranslatableContract
{
    use LogsActivity;
    use Translatable;

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setTable(config('core.table_prefix') . 'currencies');
    }

    protected $fillable = [
        'code',
        'symbol',
        'symbol_position',
        'decimal_places',
        'exchange_rate',
        'is_active',
        'is_default',
        'created_by'
    ];

    public $translatedAttributes = [
        'name',
        'plural_name'
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
}
