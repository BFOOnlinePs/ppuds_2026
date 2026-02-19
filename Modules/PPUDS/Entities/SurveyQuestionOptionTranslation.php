<?php

namespace Modules\PPUDS\Entities;

use Illuminate\Database\Eloquent\Model;

class SurveyQuestionOptionTranslation extends Model
{
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->setTable(config('ppuds.table_prefix') . 'survey_question_option_translations');
    }

    protected $fillable = [
        'text',
        'locale',
    ];

    public $timestamps = false;
}
