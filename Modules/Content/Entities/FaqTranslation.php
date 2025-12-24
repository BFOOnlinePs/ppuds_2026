<?php

namespace Modules\Content\Entities;

use Illuminate\Database\Eloquent\Model;

class FaqTranslation extends Model
{
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setTable(config('content.table_prefix') . 'faq_translations');
    }

    protected $fillable = [
        'question',
        'answer',
        'locale',
    ];
}
