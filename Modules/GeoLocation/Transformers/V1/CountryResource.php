<?php

namespace Modules\GeoLocation\Transformers\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\QueryBuilder\AllowedFilter;

class CountryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            // --- الحقول التي أضفتها لإصلاح الكود ---
            'id'                => $this->id,
            'name'              => $this->name, // كان مفقوداً، وهو مطلوب للـ sort

            // --- الحقول التي كانت موجودة ---
            'code'              => $this->code,
            'phone_code'        => $this->phone_code,
            'currency_id'       => $this->currency_id,
            'currency'          => $this->whenLoaded('currency'),
        ];
    }

    public static function allowedFilters(): array
    {
        return [
            AllowedFilter::exact('id'),
            AllowedFilter::partial('name'), // أضفت الفلترة بالاسم
            AllowedFilter::partial('code'),
            AllowedFilter::exact('phone_code'),
            AllowedFilter::exact('currency_id'),
        ];
    }

    public static function allowedFields(): array
    {
        return [
            // --- الحقول التي أضفتها ---
            'id',
            'name',
            'currency', // كان مفقوداً، وهو مطلوب للـ include

            // --- الحقول التي كانت موجودة ---
            'code',
            'phone_code',
            'currency_id',
        ];
    }

    public static function allowedSorts(): array
    {
        return [
            'id',
            'name',
            'created_at',
        ];
    }

    public static function allowedIncludes(): array
    {
        return [
            'currency',
        ];
    }
}
