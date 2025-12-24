<?php

namespace Modules\GeoLocation\Transformers\V1;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;

class DistrictResource extends JsonResource
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
            'city_id'         => $this->city_id,
            'latitude'        => $this->latitude,
            'longitude'       => $this->longitude,
            'type'            => $this->type,
            'city'            => $this->whenLoaded('city'),
            'governorate'     => $this->whenLoaded('governorate'),
        ];
    }

    public static function allowedFilters(): array
    {
        return [
            AllowedFilter::exact('id'),
            AllowedFilter::exact('city_id'),
            AllowedFilter::exact('latitude'),
            AllowedFilter::exact('longitude'),
            AllowedFilter::exact('type'),

            // فلتر للبحث بالاسم المترجم
            AllowedFilter::callback('name', function (Builder $query, $value) {
                $query->whereTranslationLike('name', "%{$value}%");
            }),

            // فلتر للبحث عن طريق المحافظة (من خلال جدول city)
            AllowedFilter::callback('governorate_id', function (Builder $query, $value) {
                $query->whereHas('city', function (Builder $q) use ($value) {
                    $q->where('governorate_id', $value);
                });
            }),
        ];
    }

    public static function allowedFields(): array
    {
        return [
            'id',
            'name',
            'city_id',
            'latitude',
            'longitude',
            'type',
            'city', // <-- مضافة
            'governorate', // <-- مضافة
        ];
    }

    public static function allowedSorts(): array
    {
        return [
            AllowedSort::field('id'),
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
            'governorate',
            'city'
        ];
    }
}
