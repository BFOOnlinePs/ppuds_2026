<?php

namespace Modules\PPUDS\Livewire\Pages\Survey\Details;

use Livewire\Component;
use Modules\PPUDS\Entities\SurveyQuestion;
use Modules\PPUDS\Enums\SurveyQuestionType;

class QuestionAnswerStatisticsCharts extends Component
{
    public ?int $surveyId = null;

    public function chartWidgets(): array
    {
        if (! $this->surveyId) {
            return [];
        }

        return SurveyQuestion::query()
            ->where('survey_id', $this->surveyId)
            ->whereIn('type', $this->optionQuestionTypes())
            ->whereHas('options')
            ->orderBy('sort_order')
            ->pluck('id')
            ->map(fn ($questionId) => QuestionAnswerStatisticsChart::make([
                'surveyId' => $this->surveyId,
                'questionId' => (int) $questionId,
            ]))
            ->all();
    }

    protected function optionQuestionTypes(): array
    {
        return [
            SurveyQuestionType::RADIO->value,
            SurveyQuestionType::CHECKBOX->value,
            SurveyQuestionType::SELECT->value,
            SurveyQuestionType::MULTI_SELECT->value,
        ];
    }

    public function render()
    {
        return view('ppuds::livewire.pages.survey.details.question-answer-statistics-charts');
    }
}
