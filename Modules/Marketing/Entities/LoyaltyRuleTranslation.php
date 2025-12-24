<?php

namespace Modules\Marketing\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Modules\Items\Database\Factories\AttributeTranslationFactory;

class LoyaltyRuleTranslation extends Model
{
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setTable(config('marketing.table_prefix') . 'loyalty_rule_translations');
    }

    protected $fillable = [
        'name',
        'description',
        'locale',
    ];
}
