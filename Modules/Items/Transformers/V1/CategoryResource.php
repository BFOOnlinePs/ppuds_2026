<?php

namespace Modules\Items\Transformers\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;

class CategoryResource extends JsonResource
{
    /**
     * تحديد الحقول التي يمكن للمستخدم طلبها عبر API.
     * الحقول المترجمة مثل 'name' ستكون متاحة مباشرة على الموديل بفضل astrotomic/translatable.
     */
    public static function allowedFields(): array
    {
        return [
            'id',
            'slug',
            'status',
            'sort_order',
            'parent_id',
            'created_at',
            'updated_at',
            'name',
            'description',
            'translations',
            'parent_id',
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
            AllowedFilter::exact('slug'),
            AllowedFilter::exact('status'),
            AllowedFilter::exact('parent_id'),
            AllowedFilter::callback('name', fn ($q, $v) => $q->whereHas('translations', fn ($q) => $q->where('name', 'LIKE', "%{$v}%"))),
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
                    $dir = in_array($d, ['asc','desc'], true) ? $d : 'asc';
                }
                $query->orderByTranslation('name', $dir);
            }),
        ];
    }

    /**
     * تحويل الـ resource إلى مصفوفة.
     * من الأفضل تحديد الحقول هنا بشكل صريح.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'slug' => $this->slug,
            'status' => $this->status,
            'order_by' => $this->order_by,
            'parent_id' => $this->parent_id,
            'image' => $this->image,
            'translations' => $this->whenLoaded('translations')
        ];
    }
}
