<?php

namespace Modules\Content\Transformers\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Content\Entities\Page; // <-- تم التغيير إلى Page
use Modules\Core\Traits\Concerns\SelectsFieldsFromApi;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;

/**
 * @OA\Schema(
 * schema="PageResource",
 * title="Page Resource (Full Details)",
 * @OA\Property(property="id", type="integer", example=1),
 * @OA\Property(property="slug", type="string", example="privacy-policy"),
 * @OA\Property(property="name", type="string", example="Privacy Policy"),
 * @OA\Property(property="content", type="string", example="<h1>Heading</h1><p>Details...</p>"),
 * @OA\Property(property="created_at", type="string", format="date-time", example="2024-01-01 10:00:00"),
 * @OA\Property(property="updated_at", type="string", format="date-time", example="2024-01-01 10:00:00")
 * )
 */
class PageResource extends JsonResource
{
    use SelectsFieldsFromApi;
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id'        => $this->id,
            'slug'      => $this->slug,
            'name'     => $this->name,
            'content'   => $this->content,
            'created_at' => $this->created_at ? $this->created_at->format('Y-m-d H:i:s') : null,
            'updated_at' => $this->updated_at ? $this->updated_at->format('Y-m-d H:i:s') : null,
        ];
    }

    /**
     * الحقول المسموح بها في QueryBuilder
     * (لجدول pages و page_translations)
     */
    public static function allowedFields(): array
    {
        return [
            'id',
            'slug',
            'created_at',
            'updated_at',
            'name',
            'content',
        ];
    }

    /**
     * الفرز المسموح به في QueryBuilder
     */
    public static function allowedSorts(): array
    {
        return [
            AllowedSort::field('id'),
            AllowedSort::field('slug'),

            AllowedSort::field('name'),
            AllowedSort::field('created_at'),
            AllowedSort::field('updated_at'),
        ];
    }

    /**
     * الفلترة المسموح بها في QueryBuilder
     */
    public static function allowedFilters(): array
    {
        return [
            AllowedFilter::exact('id'),
            AllowedFilter::exact('slug'),
            AllowedFilter::partial('name', 'translations.name'),
        ];
    }
}
