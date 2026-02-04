<?php

namespace Modules\PPUDS\Entities;

use Illuminate\Database\Eloquent\Model;

class CourseTranslation extends Model
{
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setTable(config('ppuds.table_prefix') . 'course_translations');
    }

    protected $fillable = [
        'name',
        'description',
        'locale',
    ];
}
