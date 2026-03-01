<?php

namespace Modules\PPUDS\Entities;

use Illuminate\Database\Eloquent\Model;

class AnnouncementTranslation extends Model
{
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->setTable(config('ppuds.table_prefix') . 'announcement_translations');
    }

    protected $fillable = [
        'name',
        'content',
        'locale',
    ];

    public $timestamps = false;
}
