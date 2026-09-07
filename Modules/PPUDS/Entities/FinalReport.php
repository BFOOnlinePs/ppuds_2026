<?php

namespace Modules\PPUDS\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Entities\User;
use Modules\PPUDS\Enums\FinalReportItemType;
use Modules\PPUDS\Enums\FinalReportStatus;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class FinalReport extends Model
{
    use LogsActivity;

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setTable(config('ppuds.table_prefix').'final_reports');
    }

    protected $fillable = [
        'id',
        'registration_id',
        'student_id',
        'role_description',
        'summary',
        'status',
        'submitted_at',
        'created_by',
    ];

    protected $casts = [
        'status' => FinalReportStatus::class,
        'submitted_at' => 'datetime',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly($this->getFillable())
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName(class_basename($this));
    }

    public function registration(): BelongsTo
    {
        return $this->belongsTo(Registration::class, 'registration_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(FinalReportTask::class, 'final_report_id')->orderBy('sort_order');
    }

    public function skills(): HasMany
    {
        return $this->hasMany(FinalReportSkill::class, 'final_report_id')->orderBy('sort_order');
    }

    public function items(): HasMany
    {
        return $this->hasMany(FinalReportItem::class, 'final_report_id')->orderBy('sort_order');
    }

    public function contributions(): HasMany
    {
        return $this->items()->where('type', FinalReportItemType::CONTRIBUTION);
    }

    public function difficulties(): HasMany
    {
        return $this->items()->where('type', FinalReportItemType::DIFFICULTY);
    }

    public function isSubmitted(): bool
    {
        return $this->status === FinalReportStatus::SUBMITTED;
    }

    public function isEditable(): bool
    {
        return ! $this->isSubmitted();
    }
}
