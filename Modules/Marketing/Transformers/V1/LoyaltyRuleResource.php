<?php

namespace Modules\Marketing\Transformers\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;

class LoyaltyRuleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'module' => $this->module,
            'action' => $this->action,
            'type' => $this->type,
            'points_rate' => $this->points_rate,
            'fixed_points' => $this->fixed_points,
            'min_amount' => $this->min_amount,
            'starts_at' => $this->starts_at,
            'ends_at' => $this->ends_at,
            'is_active' => (bool) $this->is_active,
            'created_at' => $this->created_at,
        ];
    }

    public static function allowedFields(): array
    {
        return [
            'id', 'name', 'module', 'action', 'type', 'points_rate', 'fixed_points',
            'min_amount', 'starts_at', 'ends_at', 'is_active', 'created_at',
        ];
    }

    public static function allowedFilters(): array
    {
        return [
            AllowedFilter::callback('name', fn (Builder $query, $value) => $query->whereTranslationLike('name', "%{$value}%")),
            AllowedFilter::exact('type'),
            AllowedFilter::exact('module'),
            AllowedFilter::exact('is_active'),
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
        ];
    }
}
