<?php

namespace Modules\PPUDS\Entities;

use Illuminate\Database\Eloquent\Model;

class SurveyTranslation extends Model
{
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->setTable(config('ppuds.table_prefix') . 'survey_translations');
    }

    protected $fillable = [
        'title',
        'description',
        'locale',
    ];

    public $timestamps = false;
}
