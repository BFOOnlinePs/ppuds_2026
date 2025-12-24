<?php

namespace Modules\Content\Entities;

use Illuminate\Database\Eloquent\Model;

class PageTranslation extends Model
{
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setTable(config('content.table_prefix') . 'page_translations');
    }

    protected $fillable = [
        'name',
        'content',
        'locale',
    ];
}
