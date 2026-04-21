<?php

namespace Modules\PPUDS\Transformers\V1;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Database\Eloquent\Builder;
use Modules\Core\Transformers\V1\UserResource;
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

            'submitted_by'          => new UserResource($this->whenLoaded('submittedBy')),

            'created_by'            => $this->created_by,
            'created_at'            => $this->created_at,
        ];
    }

    public static function allowedFields(): array
    {
        return [
                'id', 'survey_id', 'survey_question_id', 'text_answer', 'selected_option_id', 'submitted_by', 'created_by', 'created_at'
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
            AllowedSort::field('text_answer'),
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
            'submittedBy',
        ];
    }
}
