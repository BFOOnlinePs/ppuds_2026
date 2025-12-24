<?php

namespace Modules\Clinic\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Modules\Items\Database\Factories\AttributeTranslationFactory;

class DiseaseTranslation extends Model
{
    use HasFactory;

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setTable(config('clinic.table_prefix') . 'disease_translations');
    }

    protected $fillable = [
        'name',
        'description',
        'locale',
    ];
}
