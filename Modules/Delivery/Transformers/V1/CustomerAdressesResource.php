<?php

namespace Modules\Delivery\Transformers\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;

class CustomerAdressesResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                            => $this->id,
            'user_id'                       => $this->user_id,
            'user'                          => $this->whenLoaded('user'),
            'label'                         => $this->label,
            'latitude'                      => $this->latitude,
            'longitude'                     => $this->longitude,
            'contact_phone'                 => $this->contact_phone,
            'address_details'               => $this->address_details,
            'delivery_instructions'         => $this->delivery_instructions,
            'is_default'                    => $this->is_default,
        ];
    }

    public static function allowedFields(): array
    {
        return [
            'id', 'user_id', 'user', 'label', 'latitude', 'longitude', 'contact_phone', 'address_details', 'delivery_instructions', 'is_default',
        ];
    }

    public static function allowedFilters(): array
    {
        return [
            AllowedFilter::exact('user_id'),
            AllowedFilter::exact('label'),
            AllowedFilter::exact('is_default'),
            AllowedFilter::exact('latitude'),
            AllowedFilter::exact('longitude'),
            AllowedFilter::exact('contact_phone'),
            AllowedFilter::exact('address_details'),
            AllowedFilter::exact('delivery_instructions'),
        ];
    }

    public static function allowedSorts(): array
    {
        return [
            AllowedSort::field('id'),
            AllowedSort::field('created_at'),
        ];
    }

    public static function allowedIncludes(): array
    {
        return [
            'user'
        ];
    }
}
