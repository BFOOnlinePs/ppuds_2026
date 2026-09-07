<?php

namespace Modules\PPUDS\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinalReportTask extends Model
{
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setTable(config('ppuds.table_prefix').'final_report_tasks');
    }

    protected $fillable = [
        'id',
        'final_report_id',
        'task_name',
        'task_details',
        'work_duration',
        'notes',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function finalReport(): BelongsTo
    {
        return $this->belongsTo(FinalReport::class, 'final_report_id');
    }
}
