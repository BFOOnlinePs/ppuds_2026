<?php

namespace Modules\PPUDS\Transformers\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;

class SurveyQuestionOptionResource extends JsonResource
{

    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'content'       => $this->content,
            'sort_order'    => $this->sort_order,
        ];
    }

    public static function allowedFields(): array
    {
        return [
                'id', 'content', 'survey_id', 'type', 'is_required', 'sort_order', 'created_at'
        ];
    }

    public static function allowedFilters(): array
    {
        return [
            AllowedFilter::callback('content', fn (Builder $query, $value) => $query->whereTranslationLike('content', "%{$value}%")),
            AllowedFilter::exact('survey_id'),
            AllowedFilter::exact('type'),
            AllowedFilter::exact('is_required'),
        ];
    }

    public static function allowedSorts(): array
    {
        return [
            AllowedSort::field('id'),
            AllowedSort::field('content'),
            AllowedSort::field('sort_order'),
            AllowedSort::field('created_at'),
        ];
    }

    public static function allowedIncludes(): array
    {
        return [
            'createdBy',
            'survey',
            'options',
            'question',
        ];
    }
}
