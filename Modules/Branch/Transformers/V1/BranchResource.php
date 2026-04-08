<?php

namespace Modules\Branch\Transformers\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\PPUDS\Transformers\V1\CompanyDepartmentResource;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;

class BranchResource extends JsonResource
{
    /**
     * تحديد الحقول التي يمكن للمستخدم طلبها عبر API.
     * الحقول المترجمة مثل 'name' ستكون متاحة مباشرة على الموديل بفضل astrotomic/translatable.
     */
    public static function allowedFields(): array
    {
        return [
            'id',
            'name',
            'description',
            'address',
            'phone',
            'email',
            'city_id',
            'country_id',
            'latitude',
            'longitude',
            'opening_time',
            'closing_time',
            'status',
        ];
    }

    /**
     * تحديد الفلاتر المسموح بها.
     * نستخدم callback للحقول المترجمة لاستدعاء الـ scope المخصص في الموديل.
     */
    public static function allowedFilters(): array
    {
        return [
            AllowedFilter::exact('id'),
            AllowedFilter::exact('name'),
            AllowedFilter::exact('address'),
            AllowedFilter::exact('phone'),
            AllowedFilter::exact('email'),
            AllowedFilter::exact('city_id'),
            AllowedFilter::exact('country_id'),
            AllowedFilter::exact('latitude'),
            AllowedFilter::exact('longitude'),
            AllowedFilter::exact('opening_time'),
            AllowedFilter::exact('closing_time'),
            AllowedFilter::exact('status'),
            AllowedFilter::callback('name', fn($q, $v) => $q->whereHas('translations', fn($q) => $q->where('name', 'LIKE', "%{$v}%"))),
        ];
    }

    public static function allowedSorts(): array
    {
        return [
            AllowedSort::field('id'),
            AllowedSort::field('slug'),
            AllowedSort::field('status'),
            AllowedSort::field('sort_order'),
            AllowedSort::callback('name', function ($query, $directionOrDescending) {
                if (is_bool($directionOrDescending)) {
                    $dir = $directionOrDescending ? 'asc' : 'desc';
                } else {
                    $d = strtolower((string)$directionOrDescending);
                    $dir = in_array($d, ['asc', 'desc'], true) ? $d : 'asc';
                }
                $query->orderByTranslation('name', $dir);
            }),
        ];
    }

    public static function allowedIncludes(): array
    {
        return [
            'workingHours',
            'departments',
            'translations'
        ];
    }

    /**
     * تحويل الـ resource إلى مصفوفة.
     * من الأفضل تحديد الحقول هنا بشكل صريح.
     */
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'name'          => $this->name,
            'description'   => $this->description,
            'address'       => $this->address,
            'phone'         => $this->phone,
            'email'         => $this->email,
            'city_id'       => $this->city_id,
            'country_id'    => $this->country_id,
            'latitude'      => $this->latitude,
            'longitude'     => $this->longitude,
            'opening_time'  => $this->opening_time,
            'closing_time'  => $this->closing_time,
            'status'        => $this->status,
            'working_hours' => BranchWorkingHourResource::collection($this->whenLoaded('workingHours')),
            'departments'   => CompanyDepartmentResource::collection($this->whenLoaded('departments')),
            'translations'   => $this->whenLoaded('translations')
        ];
    }
}
