<?php

namespace Modules\PPUDS\Transformers\V1;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;

/**
 * @OA\Schema(
 * schema="AnnouncementCategoryResource",
 * title="Announcement Category Resource",
 * description="Announcement category details",
 * @OA\Property(property="id", type="integer", example=1),
 * @OA\Property(property="name", type="string", example="General"),
 * @OA\Property(property="created_at", type="string", example="2026-05-31 12:00:00")
 * )
 */
class AnnouncementCategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'name'       => $this->name,
            'created_at' => $this->created_at,
        ];
    }

    public static function allowedFields(): array
    {
        return [
            'id', 'name', 'created_at'
        ];
    }

    public static function allowedFilters(): array
    {
        return [
            AllowedFilter::callback('name', fn (Builder $query, $value) => $query->whereTranslationLike('name', "%{$value}%")),
            AllowedFilter::exact('id'),
        ];
    }

    public static function allowedSorts(): array
    {
        return [
            AllowedSort::field('id'),
            AllowedSort::field('created_at'),
            AllowedSort::field('name'),
        ];
    }

    public static function allowedIncludes(): array
    {
        return [
            'createdBy',
        ];
    }
}
