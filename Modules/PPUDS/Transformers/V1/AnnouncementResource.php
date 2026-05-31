<?php

namespace Modules\PPUDS\Transformers\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;


/**
 * @OA\Schema(
 * schema="AnnouncementResource",
 * title="Announcement Resource",
 * description="Announcement details",
 * @OA\Xml(name="AnnouncementResource"),
 * @OA\Property(property="name", type="string", example="إعلان هام"),
 * @OA\Property(property="target_roles[]", type="array", @OA\Items(type="string"), example={"student", "company"}),
 * @OA\Property(property="content", type="string", example="تفاصيل الإعلان..."),
 * @OA\Property(property="published_at", type="string", format="date-time"),
 * @OA\Property(property="image_url", type="string", example="https://site.com/img.png")
 * )
 */
class AnnouncementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'announcement_category_id' => $this->announcement_category_id,
            'category'      => $this->whenLoaded('category', fn () => [
                'id' => $this->category?->id,
                'name' => $this->category?->name,
            ]),
            'name'          => $this->name,
            'content'       => $this->content,
            'target_roles'  => $this->target_roles,
            'filters'       => $this->filters,
            'published_at'  => $this->published_at,
            'expires_at'    => $this->expires_at,
            'is_pinned'     => $this->is_pinned,
            'image'         => $this->getFirstMediaUrl('announcement_image'),
            'created_by'    => $this->created_by,
            'created_at'    => $this->created_at,
        ];
    }

    public static function allowedFields(): array
    {
        return [
            'id',
            'announcement_category_id',
            'name',
            'content',
            'target_roles',
            'filters',
            'published_at',
            'expires_at',
            'is_pinned',
            'created_at'
        ];
    }

    public static function allowedFilters(): array
    {
        return [
            AllowedFilter::callback('name', fn(Builder $query, $value) => $query->whereTranslationLike('name', "%{$value}%")),
            AllowedFilter::callback('content', fn(Builder $query, $value) => $query->whereTranslationLike('content', "%{$value}%")),
            AllowedFilter::exact('announcement_category_id'),
            AllowedFilter::exact('is_pinned'),
            AllowedFilter::scope('active'),
            AllowedFilter::callback('target_roles', function (Builder $query, $value) {
                $roles = Arr::wrap($value);

                $query->where(function ($q) use ($roles) {
                    foreach ($roles as $role) {
                        $q->orWhereJsonContains('target_roles', $role);
                    }
                });
            })
        ];
    }

    public static function allowedSorts(): array
    {
        return [
            AllowedSort::field('id'),
            AllowedSort::field('published_at'),
            AllowedSort::field('created_at'),
            AllowedSort::field('is_pinned'),
        ];
    }

    public static function allowedIncludes(): array
    {
        return [
            'createdBy',
            'category',
        ];
    }
}
