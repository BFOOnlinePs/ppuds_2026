<?php

namespace Modules\Coupon\Transformers\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;

class CouponeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'code'              => $this->code,
            'type'              => $this->type,
            'value'             => $this->value,
            'max_uses'          => $this->max_uses,
            'current_uses'      => $this->current_uses,
            'min_order_amount'  => $this->min_order_amount,
            'starts_at'         => $this->starts_at,
            'expires_at'        => $this->expires_at,
            'is_active'         => $this->is_active,
            'created_by'        => $this->created_by,
        ];
    }

    public static function allowedFields(): array
    {
        return [
            'id',
            'code',
            'type',
            'value',
            'max_uses',
            'current_uses',
            'min_order_amount',
            'starts_at',
            'expires_at',
            'is_active',
            'created_by',
        ];
    }

    public static function allowedFilters(): array
    {
        return [
            AllowedFilter::partial('code'),
            AllowedFilter::exact('type'),
            AllowedFilter::exact('is_active'),
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

        ];
    }
}
