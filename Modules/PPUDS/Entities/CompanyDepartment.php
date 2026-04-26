<?php

namespace Modules\PPUDS\Entities;

use Astrotomic\Translatable\Translatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Branch\Entities\Branch;
use Modules\Core\Entities\User;
use Modules\PPUDS\Enums\Enums\CompanyStatus;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Astrotomic\Translatable\Contracts\Translatable as TranslatableContract;


class CompanyDepartment extends Model implements TranslatableContract
{
    use LogsActivity;
    use Translatable;
    use softDeletes;

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setTable(config('ppuds.table_prefix') . 'company_departments');
    }

    protected $fillable = [
        'id',
        'created_by',
    ];

    public $translatedAttributes = [
        'name',
    ];

    public $translationForeignKey = 'department_id';

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

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class, 'ppu_ds_branch_department', 'company_department_id', 'branch_id')
            ->withPivot('user_id')
            ->withTimestamps();
    }

    public function supervisors(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'ppu_ds_branch_department', 'company_department_id', 'user_id')
            ->distinct();
    }
}
