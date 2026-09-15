<?php

namespace Modules\PPUDS\Exports;

use Generator;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromGenerator;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Modules\PPUDS\Entities\Survey;
use Modules\PPUDS\Entities\SurveyAnswer;
use Modules\PPUDS\Entities\SurveyQuestion;
use Modules\PPUDS\Enums\SurveyQuestionType;

class SurveyStatisticsExport implements FromGenerator, ShouldAutoSize, WithHeadings
{
    public function __construct(protected Survey $survey) {}

    public function headings(): array
    {
        return [
            __('Question'),
            __('Question Type'),
            __('Option'),
            __('Answers Count'),
            __('Percentage'),
        ];
    }

    public function generator(): Generator
    {
        $questions = SurveyQuestion::query()
            ->where('survey_id', $this->survey->id)
            ->whereIn('type', $this->optionQuestionTypes())
            ->whereHas('options')
            ->with([
                'translations',
                'options' => fn ($query) => $query->orderBy('sort_order'),
                'options.translations',
            ])
            ->orderBy('sort_order')
            ->get();

        foreach ($questions as $question) {
            $answerCounts = $this->answerCountsFor($question);
            $totalAnswers = (int) $answerCounts->sum();

            foreach ($question->options->sortBy('sort_order') as $option) {
                $answersCount = (int) ($answerCounts[$option->id] ?? 0);

                yield [
                    $this->questionHeading($question),
                    $this->questionType($question),
                    $this->optionLabel($option),
                    $answersCount,
                    $this->percentage($answersCount, $totalAnswers),
                ];
            }
        }
    }

    /**
     * نفس شروط مخططات الإحصائيات: تُحسب إجابات الفئة المستهدفة والتخصص
     * المستهدف فقط حتى تتطابق أرقام الملف مع ما يظهر على الشاشة.
     */
    protected function answerCountsFor(SurveyQuestion $question)
    {
        return SurveyAnswer::query()
            ->selectRaw('selected_option_id, COUNT(*) as answers_count')
            ->where('survey_id', $this->survey->id)
            ->where('survey_question_id', $question->id)
            ->whereNotNull('selected_option_id')
            ->when(
                $this->survey->serve_group,
                fn (Builder $query, string $role) => $query->whereHas('submittedBy', fn (Builder $userQuery) => $userQuery->role($role))
            )
            ->when(
                $this->survey->major_id,
                fn (Builder $query, int $majorId) => $query->whereHas('submittedBy.studentProfile', fn (Builder $profileQuery) => $profileQuery->where('major_id', $majorId))
            )
            ->groupBy('selected_option_id')
            ->pluck('answers_count', 'selected_option_id');
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

    protected function questionHeading(SurveyQuestion $question): string
    {
        $content = trim((string) $question->content);

        return $content !== '' ? $content : __('Question').' #'.$question->id;
    }

    protected function questionType(SurveyQuestion $question): string
    {
        return (string) (SurveyQuestionType::tryFrom((int) $question->type)?->getLabel() ?? '');
    }

    protected function optionLabel($option): string
    {
        $text = trim((string) $option->text);

        return $text !== '' ? $text : __('Option').' #'.$option->id;
    }

    protected function percentage(int $answersCount, int $totalAnswers): string
    {
        if ($totalAnswers <= 0) {
            return '0%';
        }

        return round(($answersCount / $totalAnswers) * 100, 2).'%';
    }
}
