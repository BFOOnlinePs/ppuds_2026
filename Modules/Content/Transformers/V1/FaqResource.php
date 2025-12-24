<?php

namespace Modules\Content\Transformers\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Core\Traits\Concerns\SelectsFieldsFromApi;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;

class FaqResource extends JsonResource
{
    use SelectsFieldsFromApi;

    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'question'      => $this->question,
            'answer'        => $this->answer,
            'is_active'     => $this->is_active,
            'sort_order'    => $this->sort_order,
            'created_at'    => $this->created_at ? $this->created_at->format('Y-m-d H:i:s') : null,
            'updated_at'    => $this->updated_at ? $this->updated_at->format('Y-m-d H:i:s') : null,
        ];
    }

    public static function allowedFields(): array
    {
        return ['id', 'question', 'answer', 'is_active', 'sort_order', 'created_at', 'updated_at'];
    }

    // ... بقية الدوال (allowedSorts, allowedFilters) كما هي
    public static function allowedSorts(): array
    {
        return [AllowedSort::field('id'), AllowedSort::field('question'), AllowedSort::field('answer'), AllowedSort::field('is_active'), AllowedSort::field('sort_order'), AllowedSort::field('created_at'), AllowedSort::field('updated_at')];
    }

    public static function allowedFilters(): array
    {
        return [AllowedFilter::exact('id'), AllowedFilter::exact('question'), AllowedFilter::partial('question', 'translations.question')];
    }
}
