<?php

namespace Modules\Delivery\Transformers\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;

class DeliveryPricingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                        => $this->id,
            'name'                      => $this->name,
            'base_fee'                  => $this->base_fee,
            'price_per_km'              => $this->price_per_km,
            'description'               => $this->description,
            'delivery_fee_tiers'        => DeliveryFeeTierResource::collection($this->whenLoaded('deliveryFeeTiers')),        ];
    }

    public static function allowedFields(): array
    {
        return [
            'id', 'name',
            'base_fee', 'price_per_km', 'is_active', 'description', 'created_at',
        ];
    }

    public static function allowedFilters(): array
    {
        return [
            AllowedFilter::callback('name', fn (Builder $query, $value) => $query->whereTranslationLike('name', "%{$value}%")),
            AllowedFilter::exact('is_active'),
            AllowedFilter::exact('base_fee'),
            AllowedFilter::exact('price_per_km'),
        ];
    }

    public static function allowedSorts(): array
    {
        return [
            AllowedSort::field('id'),
            AllowedSort::field('name'),
            AllowedSort::field('created_at'),
        ];
    }

    public static function allowedIncludes(): array
    {
        return [
            'createdBy',
            'deliveryFeeTiers',
        ];
    }
}
