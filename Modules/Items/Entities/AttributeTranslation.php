<?php

namespace Modules\Items\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Modules\Items\Database\Factories\AttributeTranslationFactory;

class AttributeTranslation extends Model
{
    use HasFactory;

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setTable(config('items.table_prefix') . 'attribute_translations');
    }

    protected $fillable = [
        'name',
        'description',
        'locale',
        'attribute_id',
    ];
}
