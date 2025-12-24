<?php

namespace Modules\GeoLocation\Transformers\V1;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;

class GovernorateResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'name'       => $this->name,
            'code'       => $this->code,
            'country_id' => $this->country_id,
            'country'    => $this->whenLoaded('country'),
            'cities'     => $this->whenLoaded('cities'),
        ];
    }

    public static function allowedFilters(): array
    {
        return [
            AllowedFilter::exact('id'),
            AllowedFilter::exact('country_id'),
            AllowedFilter::partial('code'),

            // فلتر للبحث بالاسم المترجم
            AllowedFilter::callback('name', function (Builder $query, $value) {
                $query->whereTranslationLike('name', "%{$value}%");
            }),
        ];
    }

    public static function allowedFields(): array
    {
        return [
            'id',
            'name',
            'code',
            'country_id',
            'country',
            'cities',
        ];
    }

    public static function allowedSorts(): array
    {
        return [
            AllowedSort::field('id'),
            AllowedSort::field('code'),
            AllowedSort::field('created_at'),

            // ترتيب حسب الاسم المترجم
            AllowedSort::callback('name', function (Builder $query, bool $descending) {
                $query->orderByTranslation('name', $descending ? 'desc' : 'asc');
            }),
        ];
    }

    public static function allowedIncludes(): array
    {
        return [
            'country',
            'cities',
        ];
    }
}
