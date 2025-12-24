<?php

namespace Modules\GeoLocation\Transformers\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\QueryBuilder\AllowedFilter;

class CityResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'name'            => $this->name,
            'governorate_id'  => $this->governorate_id,
            'latitude'        => $this->latitude,
            'longitude'       => $this->longitude,
            'population'      => $this->population,
            'type'            => $this->type,
            'is_capital'      => (bool) $this->is_capital,
            'capital_type'    => $this->capital_type,
            'governorate'     => $this->whenLoaded('governorate'),
        ];
    }

    public static function allowedFilters(): array
    {
        return [
            AllowedFilter::exact('id'),
            AllowedFilter::partial('name'), // غيرت 'exact' إلى 'partial' للبحث الجزئي، يمكنك إعادتها
            AllowedFilter::exact('governorate_id'),
            AllowedFilter::exact('latitude'),
            AllowedFilter::exact('longitude'),
            AllowedFilter::exact('population'),
            AllowedFilter::exact('type'),
            AllowedFilter::exact('is_capital'),
            AllowedFilter::exact('capital_type'),
        ];
    }

    public static function allowedFields(): array
    {
        return [
            'id',
            'name',
            'governorate_id',
            'latitude',
            'longitude',
            'population',
            'type',
            'is_capital',
            'capital_type',
        ];
    }

    public static function allowedSorts(): array
    {
        return [
            'id',
            'name',
            'population',
            'created_at',
        ];
    }

    public static function allowedIncludes(): array
    {
        return [
            'governorate',
        ];
    }
}
