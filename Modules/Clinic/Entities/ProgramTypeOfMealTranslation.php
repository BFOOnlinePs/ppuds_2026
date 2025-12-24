<?php

namespace Modules\Clinic\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Modules\Items\Database\Factories\AttributeTranslationFactory;

class ProgramTypeOfMealTranslation extends Model
{
    use HasFactory;

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setTable(config('clinic.table_prefix') . 'types_of_meal_translations');
    }

    protected $fillable = [
        'name',
        'description',
        'locale',
    ];
}
