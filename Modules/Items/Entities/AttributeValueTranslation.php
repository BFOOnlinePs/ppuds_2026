<?php

namespace Modules\Items\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Modules\Items\Database\Factories\AttributeValueTranslationFactory;

class AttributeValueTranslation extends Model
{
    use HasFactory;

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setTable(config('items.table_prefix') . 'attribute_values_translations');
    }

    protected $fillable = [
        'name',
        'description',
        'locale',
        'attribute_value_id',
    ];
}
