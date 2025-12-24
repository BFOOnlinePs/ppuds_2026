<?php

namespace Modules\Content\Entities;

use Illuminate\Database\Eloquent\Model;

class FaqCategoryTranslation extends Model
{
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setTable(config('content.table_prefix') . 'faq_category_translations');
    }

    protected $fillable = [
        'name',
        'locale',
    ];
}
