<?php

namespace Modules\Delivery\Transformers\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;

class DeliveryZoneResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                            => $this->id,
            'name'                          => $this->name,
            'branch_id'                     => $this->branch_id,
            'branch'                        => $this->whenLoaded('branch'),
            'zone_area'                     => $this->zone_area,
            'delivery_pricing_id'           => $this->delivery_pricing_id,
            'deliveryPricing'               => $this->whenLoaded('deliveryPricing'),
            'is_active'                     => $this->is_active,
        ];
    }

    public static function allowedFields(): array
    {
        return [
            'id', 'name',
            'zone_area', 'delivery_pricing_id', 'is_active', 'created_at',
        ];
    }

    public static function allowedFilters(): array
    {
        return [
            AllowedFilter::callback('name', fn (Builder $query, $value) => $query->whereTranslationLike('name', "%{$value}%")),
            AllowedFilter::exact('is_active'),
            AllowedFilter::exact('delivery_pricing_id'),
            AllowedFilter::exact('zone_area'),
            AllowedFilter::exact('created_at'),
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
            'branch',
            'deliveryPricing',
            'deliveryPricing.deliveryFeeTiers'
        ];
    }
}
