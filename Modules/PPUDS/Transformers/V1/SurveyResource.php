<?php

namespace Modules\PPUDS\Transformers\V1;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;

class SurveyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            'title' => $this->title,
            'description' => $this->description,
            'serve_group' => $this->serve_group,
            'major_id' => $this->major_id,
            'major' => $this->whenLoaded('major', fn () => [
                'id' => $this->major?->id,
                'name' => $this->major?->name,
            ]),
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'is_active' => $this->is_active,
            'semester' => $this->semester,
            'year' => $this->year,
            'is_submitted' => (bool) $this->is_submitted,
            'questions' => SurveyQuestionResource::collection($this->whenLoaded('questions')),

            'created_at' => $this->created_at,
        ];
    }

    public static function allowedFields(): array
    {
        return [
            'id', 'title', 'description',
            'serve_group', 'major_id', 'start_date', 'end_date', 'is_active', 'semester', 'year', 'created_at',
        ];
    }

    public static function allowedFilters(): array
    {
        return [
            AllowedFilter::callback('title', fn (Builder $query, $value) => $query->whereTranslationLike('title', "%{$value}%")),
            AllowedFilter::exact('serve_group'),
            AllowedFilter::exact('major_id'),
            AllowedFilter::exact('is_active'),
            AllowedFilter::exact('semester'),
            AllowedFilter::exact('year'),
        ];
    }

    public static function allowedSorts(): array
    {
        return [
            AllowedSort::field('id'),
            AllowedSort::field('title'),
            AllowedSort::field('created_at'),
        ];
    }

    public static function allowedIncludes(): array
    {
        return [
            'createdBy',
            'major',
            'questions',
            'questions.options',
        ];
    }
}
