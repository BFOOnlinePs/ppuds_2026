<?php

namespace Modules\Delivery\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Modules\Items\Database\Factories\AttributeTranslationFactory;

class DeliveryPricingTranslation extends Model
{
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setTable(config('delivery.table_prefix') . 'delivery_pricing_translations');
    }

    protected $fillable = [
        'name',
        'description',
        'locale',
    ];
}
