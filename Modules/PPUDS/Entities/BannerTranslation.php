<?php

namespace Modules\PPUDS\Entities;

use Illuminate\Database\Eloquent\Model;

class BannerTranslation extends Model
{
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->setTable(config('ppuds.table_prefix') . 'banner_translations');
    }

    protected $fillable = [
        'url',
        'locale',
    ];
}
