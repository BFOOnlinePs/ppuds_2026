<?php

namespace Modules\Delivery\Transformers\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;

class DeliveryFeeTierResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'id'                  => $this->id,
            'delivery_pricing_id' => $this->delivery_pricing_id,
            'min_distance_km'     => $this->min_distance_km,
            'extra_charge'        => $this->extra_charge,
            'created_by'          => $this->created_by,
            'created_by_user'     => $this->whenLoaded('createdBy', function () {
                $user = $this->createdBy;
                return $user ? [
                    'id'   => $user->id,
                    'name' => $user->name ?? null,
                    'email'=> $user->email ?? null,
                ] : null;
            }),
            'created_at'          => optional($this->created_at)->toDateTimeString(),
            'updated_at'          => optional($this->updated_at)->toDateTimeString(),
            'deleted_at'          => optional($this->deleted_at)->toDateTimeString(),
        ];
    }

    /**
     * Fields that can be requested.
     *
     * @return array<int, string>
     */
    public static function allowedFields(): array
    {
        return [
            'id',
            'delivery_pricing_id',
            'min_distance_km',
            'extra_charge',
            'created_by',
            'created_at',
            'updated_at',
            'deleted_at',
        ];
    }

    /**
     * Filters allowed by QueryBuilder.
     *
     * @return array<int, \Spatie\QueryBuilder\AllowedFilter>
     */
    public static function allowedFilters(): array
    {
        return [
            AllowedFilter::exact('id'),
            AllowedFilter::exact('delivery_pricing_id'),
            AllowedFilter::exact('created_by'),
            AllowedFilter::exact('min_distance_km'),
            AllowedFilter::exact('extra_charge'),
            // Example of a searchable name if you later add a relation or translation:
            // AllowedFilter::callback('created_by_name', fn (Builder $query, $value) => $query->whereHas('createdBy', fn($q) => $q->where('name', 'like', "%{$value}%"))),
        ];
    }

    /**
     * Sorts allowed by QueryBuilder.
     *
     * @return array<int, \Spatie\QueryBuilder\AllowedSort>
     */
    public static function allowedSorts(): array
    {
        return [
            AllowedSort::field('id'),
            AllowedSort::field('min_distance_km'),
            AllowedSort::field('extra_charge'),
            AllowedSort::field('created_at'),
            AllowedSort::field('updated_at'),
        ];
    }

    /**
     * Includes allowed by QueryBuilder.
     *
     * @return array<int, string>
     */
    public static function allowedIncludes(): array
    {
        return [
            'createdBy',
        ];
    }
}
