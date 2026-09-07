<?php

namespace Modules\PPUDS\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\PPUDS\Enums\FinalReportItemType;

class FinalReportItem extends Model
{
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setTable(config('ppuds.table_prefix').'final_report_items');
    }

    protected $fillable = [
        'id',
        'final_report_id',
        'type',
        'content',
        'sort_order',
    ];

    protected $casts = [
        'type' => FinalReportItemType::class,
        'sort_order' => 'integer',
    ];

    public function finalReport(): BelongsTo
    {
        return $this->belongsTo(FinalReport::class, 'final_report_id');
    }
}
