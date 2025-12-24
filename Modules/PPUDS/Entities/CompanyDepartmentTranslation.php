<?php

namespace Modules\PPUDS\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Modules\Items\Database\Factories\AttributeTranslationFactory;

class CompanyDepartmentTranslation extends Model
{
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setTable(config('ppuds.table_prefix') . 'company_department_translations');
    }

    protected $fillable = [
        'name',
        'locale',
    ];
}
