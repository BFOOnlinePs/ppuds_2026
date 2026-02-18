<?php

namespace Modules\PPUDS\Transformers\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;

class SurveyAnswerResource extends JsonResource
{

    public function toArray(Request $request): array
    {
        return [
            'id'                    => $this->id,
            'survey_id'             => $this->survey_id,
            'survey_question_id'    => $this->survey_question_id,
            'text_answer'           => $this->text_answer,
            'selected_option_id'    => $this->selected_option_id,
            
            'selected_option'       => new SurveyQuestionOptionResource($this->whenLoaded('option')),
            'question'              => new SurveyQuestionResource($this->whenLoaded('question')),
            'survey'                => new SurveyResource($this->whenLoaded('survey')),
            
            'created_by'            => $this->created_by,
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
