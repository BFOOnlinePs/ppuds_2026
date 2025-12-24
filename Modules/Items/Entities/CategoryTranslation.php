<?php

namespace Modules\Items\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

// use Modules\Items\Database\Factories\CategoryTranslationFactory;

class CategoryTranslation extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */

    public $timestamps = false;

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setTable(config('items.table_prefix') . 'category_translations');
    }

    protected $fillable = [
        'category_id',
        'locale',
        'name',
        'description',
    ];
}
