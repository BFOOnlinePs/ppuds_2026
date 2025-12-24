<?php

namespace Modules\Clinic\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Modules\Items\Database\Factories\AttributeTranslationFactory;

class FoodCategoryTranslation extends Model
{
    use HasFactory;

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setTable(config('clinic.table_prefix') . 'food_category_translations');
    }

    protected $fillable = [
        'name',
        'description',
        'locale',
    ];
}
