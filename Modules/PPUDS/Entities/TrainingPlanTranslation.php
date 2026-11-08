<?php

namespace Modules\PPUDS\Entities;

use Illuminate\Database\Eloquent\Model;

class TrainingPlanTranslation extends Model
{
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setTable(config('ppuds.table_prefix') . 'training_plan_translations');
    }

    protected $fillable = [
        'objectives',
        'tasks',
        'outcomes',
        'subjects',
        'locale',
    ];

    protected $casts = [
        'objectives' => 'array',
        'tasks' => 'array',
        'outcomes' => 'array',
        'subjects' => 'array',
    ];
}
