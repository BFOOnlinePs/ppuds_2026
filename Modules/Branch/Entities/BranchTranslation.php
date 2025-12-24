<?php

namespace Modules\Branch\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Modules\Items\Database\Factories\AttributeTranslationFactory;

class BranchTranslation extends Model
{
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setTable(config('branch.table_prefix') . 'branch_translations');
    }

    protected $fillable = [
        'name',
        'description',
        'address',
        'locale',
    ];
}
