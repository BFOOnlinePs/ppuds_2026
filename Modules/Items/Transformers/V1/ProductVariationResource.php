<?php

namespace Modules\Items\Transformers\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductVariationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, // This is the actual product_id for the variation
            'sku' => $this->sku,
            'price' => $this->sale_price, // Use sale_price for variations
            'stock_quantity' => $this->stock_quantity,
            'image' => $this->image,

            'combination' => $this->whenLoaded('attributeValues', function () {
                return $this->attributeValues->map(fn ($value) => [
                    'attribute_id'   => $value->attribute->id,
                    'attribute_name' => $value->attribute->name, // اسم الخاصية: "اللون"
                    'value_id'       => $value->id,
                    'value_name'     => $value->name, //  قيمة الخاصية: "أحمر"
                ]);
            }),
        ];
    }
}
