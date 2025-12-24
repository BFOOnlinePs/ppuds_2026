<?php

namespace Modules\GeoLocation\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class DistrictTranslation extends Model
{
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setTable(config('geolocation.table_prefix') . 'district_translations');
    }

    protected $fillable = [
        'name',
        'locale',
    ];
}
