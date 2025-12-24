<?php

namespace Modules\Items\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Modules\Items\Database\Factories\ProductTranslationFactory;

class ProductTranslation extends Model
{
    use HasFactory;

    public $timestamps = false;

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setTable(config('items.table_prefix') . 'product_translations');
    }

    protected $fillable = [
        'locale',
        'name',
        'short_description',
        'description',
    ];
}
