<?php

namespace Modules\Items\Transformers\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AddonOptionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'addon_id'          => $this->addon_id,
            'name'              => $this->name, // Automatically uses the correct translation
            'description'       => $this->description,
            'price'             => (float) $this->price,
            'image'             => $this->image,
            'is_default'        => (bool) $this->is_default,
            'is_quantifiable'   => (bool) $this->is_quantifiable,
        ];
    }
}
