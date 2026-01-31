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

class BranchWorkingHour extends Model implements TranslatableContract
{
    use LogsActivity;
    use Translatable;
    use softDeletes;

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setTable(config('branch.table_prefix') . 'branch_working_hours');
    }

    protected $fillable = [
        'branch_id',
        'day',
        'start_time',
        'end_time',
        'is_closed',
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

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }
}
