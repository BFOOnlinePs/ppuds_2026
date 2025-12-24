<?php

namespace Modules\Items\Transformers\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'quantity'          => (int) $this->quantity,
            'product_id'        => $this->product_id,

            'original_price'    => (float) $this->original_price,
            'price'             => (float) $this->price,
            'has_offer'         => $this->original_price > $this->price,

            'total_price'       => (float) $this->total_price,
            'notes'             => $this->notes,
            'product_name'      => $this->whenLoaded('product', fn() => $this->product->name),
            'product_sku'       => $this->whenLoaded('product', fn() => $this->product->sku),
            'image'             => $this->whenLoaded('product', fn() => $this->product->image),
            'selected_addons' => SelectedAddonOptionResource::collection($this->whenLoaded('addonOptions')),
        ];
    }
}
