<?php

namespace Modules\GeoLocation\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Modules\Items\Database\Factories\AttributeTranslationFactory;

class CityTranslation extends Model
{
    use HasFactory;

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setTable(config('geolocation.table_prefix') . 'city_translations');
    }

    protected $fillable = [
        'name',
        'locale',
    ];
}
