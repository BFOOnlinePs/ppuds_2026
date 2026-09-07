<?php

namespace Modules\PPUDS\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinalReportSkill extends Model
{
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setTable(config('ppuds.table_prefix').'final_report_skills');
    }

    protected $fillable = [
        'id',
        'final_report_id',
        'skill',
        'mastery_percentage',
        'notes',
        'sort_order',
    ];

    protected $casts = [
        'mastery_percentage' => 'integer',
        'sort_order' => 'integer',
    ];

    public function finalReport(): BelongsTo
    {
        return $this->belongsTo(FinalReport::class, 'final_report_id');
    }
}
