<?php

namespace Modules\Content\Transformers\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Core\Traits\Concerns\SelectsFieldsFromApi;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;

class FaqCategoryResource extends JsonResource
{
    use SelectsFieldsFromApi;

    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'slug'          => $this->slug,
            'name'          => $this->name,
            'is_active'     => $this->is_active,
            'sort_order'    => $this->sort_order,
            // تأكد من أن FaqResource تم تصحيح اسمه أيضاً في الأسفل ليتم استدعاؤه هنا بشكل صحيح
            'faqs'          => $this->whenLoaded('faqs', function() {
                return FaqResource::collection($this->faqs);
            }),
            'created_at'    => $this->created_at ? $this->created_at->format('Y-m-d H:i:s') : null,
            'updated_at'    => $this->updated_at ? $this->updated_at->format('Y-m-d H:i:s') : null,
        ];
    }

    // ... بقية دوال allowedFields وغيرها
    public static function allowedFields(): array
    {
        return ['id', 'slug', 'name', 'is_active', 'sort_order', 'created_at', 'updated_at'];
    }

    public static function allowedSorts(): array
    {
        return [AllowedSort::field('id'), AllowedSort::field('slug'), AllowedSort::field('is_active'), AllowedSort::field('sort_order'), AllowedSort::field('created_at'), AllowedSort::field('updated_at')];
    }

    public static function allowedFilters(): array
    {
        return [AllowedFilter::exact('id'), AllowedFilter::exact('slug'), AllowedFilter::exact('is_active'), AllowedFilter::partial('name', 'translations.name')];
    }
}
