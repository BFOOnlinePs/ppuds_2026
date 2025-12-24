<?php

namespace Modules\Items\Transformers\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SelectedAddonOptionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'       => $this->id,
            'name'     => $this->name,
            'quantity' => (int) $this->pivot->quantity,
            'price'    => (float) $this->pivot->price,
            'image'    => $this->image,
        ];
    }
}
