<?php

namespace Modules\PPUDS\Transformers\V1;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\PPUDS\Enums\SurveyQuestionType;
use Modules\PPUDS\Transformers\V1\SurveyQuestionOptionResource;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;

class SurveyQuestionResource extends JsonResource
{

    public function toArray(Request $request): array
    {
        $type = SurveyQuestionType::tryFrom((int) $this->type);

        return [
            'id'                    => $this->id,

            'content'               => $this->content,
            'survey_id'             => $this->survey_id,
            'type'                  => $this->type,
            'type_label'            => $type?->getLabel(),
            'is_required'           => $this->is_required,
            'sort_order'            => $this->sort_order,
            'options'               => SurveyQuestionOptionResource::collection($this->whenLoaded('options')),
            'rating_scale'          => $type === SurveyQuestionType::RATING
                ? collect(SurveyQuestionType::ratingScaleOptions())
                    ->map(fn (string $label, int $value): array => ['value' => $value, 'label' => $label])
                    ->values()
                : null,

            'created_at'            => $this->created_at,
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
