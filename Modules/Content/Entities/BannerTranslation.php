<?php

namespace Modules\Content\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Modules\Items\Database\Factories\AttributeTranslationFactory;

class BannerTranslation extends Model
{
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setTable(config('content.table_prefix') . 'banner_translations');
    }

    protected $fillable = [
        'name',
        'description',
        'locale',
    ];
}
