<?php

namespace Modules\Subscription\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Modules\Items\Database\Factories\AttributeValueTranslationFactory;

class PlanTranslation extends Model
{
    use HasFactory;

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setTable(config('subscription.table_prefix') . 'plan_translations');
    }

    protected $fillable = [
        'name',
        'description',
        'locale',
    ];
}
