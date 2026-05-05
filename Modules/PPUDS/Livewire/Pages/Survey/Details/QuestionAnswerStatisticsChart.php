<?php

namespace Modules\PPUDS\Livewire\Pages\Survey\Details;

use Filament\Widgets\ChartWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Modules\PPUDS\Entities\Survey;
use Modules\PPUDS\Entities\SurveyAnswer;
use Modules\PPUDS\Entities\SurveyQuestion;
use Modules\PPUDS\Enums\SurveyQuestionType;

class QuestionAnswerStatisticsChart extends ChartWidget
{
    protected static ?string $pollingInterval = null;

    protected static ?string $maxHeight = '220px';

    protected int | string | array $columnSpan = 1;

    public ?int $surveyId = null;

    public ?int $questionId = null;

    protected ?Survey $survey = null;

    protected ?SurveyQuestion $question = null;

    protected ?array $statistics = null;

    public function getHeading(): string
    {
        $question = $this->question();

        if (! $question) {
            return __('Question');
        }

        $heading = trim((string) $question->content) ?: __('Question').' #'.$question->id;

        return Str::limit($heading, 90);
    }

    public function getDescription(): ?string
    {
        $question = $this->question();
        $statistics = $this->statistics();

        if (! $question) {
            return null;
        }

        $type = SurveyQuestionType::tryFrom((int) $question->type)?->getLabel();

        return trim(($type ? $type.' | ' : '').__('Answers Count').': '.number_format($statistics['total_answers']));
    }

    protected function getData(): array
    {
        $statistics = $this->statistics();
        $options = collect($statistics['options']);

        return [
            'datasets' => [
                [
                    'label' => __('Answers Count'),
                    'data' => $options->pluck('answers_count')->all(),
                    'backgroundColor' => $this->chartColors($options->count()),
                    'borderWidth' => 0,
                ],
            ],
            'labels' => $options
                ->map(fn (array $option): string => Str::limit($option['label'], 35).' ('.number_format($option['answers_count']).')')
                ->all(),
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getOptions(): array
    {
        return [
            'cutout' => '62%',
            'plugins' => [
                'legend' => [
                    'position' => 'bottom',
                    'labels' => [
                        'boxWidth' => 10,
                        'usePointStyle' => true,
                    ],
                ],
            ],
        ];
    }

    protected function statistics(): array
    {
        if ($this->statistics !== null) {
            return $this->statistics;
        }

        $question = $this->question();

        if (! $question) {
            return $this->statistics = [
                'total_answers' => 0,
                'options' => [],
            ];
        }

        $answerCounts = SurveyAnswer::query()
            ->selectRaw('selected_option_id, COUNT(*) as answers_count')
            ->where('survey_id', $this->surveyId)
            ->where('survey_question_id', $question->id)
            ->whereNotNull('selected_option_id')
            ->when(
                $this->survey()?->serve_group,
                fn (Builder $query, string $role) => $query->whereHas('submittedBy', fn (Builder $userQuery) => $userQuery->role($role))
            )
            ->when(
                $this->survey()?->major_id,
                fn (Builder $query, int $majorId) => $query->whereHas('submittedBy.studentProfile', fn (Builder $profileQuery) => $profileQuery->where('major_id', $majorId))
            )
            ->groupBy('selected_option_id')
            ->pluck('answers_count', 'selected_option_id');

        $options = $question->options
            ->sortBy('sort_order')
            ->map(fn ($option): array => [
                'id' => $option->id,
                'label' => trim((string) $option->text) ?: __('Option').' #'.$option->id,
                'answers_count' => (int) ($answerCounts[$option->id] ?? 0),
            ])
            ->values()
            ->all();

        return $this->statistics = [
            'total_answers' => collect($options)->sum('answers_count'),
            'options' => $options,
        ];
    }

    protected function survey(): ?Survey
    {
        if ($this->survey !== null) {
            return $this->survey;
        }

        if (! $this->surveyId) {
            return null;
        }

        return $this->survey = Survey::query()
            ->select(['id', 'serve_group', 'major_id'])
            ->find($this->surveyId);
    }

    protected function question(): ?SurveyQuestion
    {
        if ($this->question !== null) {
            return $this->question;
        }

        if (! $this->surveyId || ! $this->questionId) {
            return null;
        }

        return $this->question = SurveyQuestion::query()
            ->where('survey_id', $this->surveyId)
            ->with([
                'translations',
                'options' => fn ($query) => $query->orderBy('sort_order'),
                'options.translations',
            ])
            ->find($this->questionId);
    }

    protected function chartColors(int $count): array
    {
        if ($count <= 0) {
            return [];
        }

        $palette = [
            '#2563eb',
            '#16a34a',
            '#f59e0b',
            '#dc2626',
            '#7c3aed',
            '#0891b2',
            '#db2777',
            '#65a30d',
            '#9333ea',
            '#ea580c',
        ];

        return collect(range(0, $count - 1))
            ->map(fn (int $index): string => $palette[$index % count($palette)])
            ->all();
    }
}
