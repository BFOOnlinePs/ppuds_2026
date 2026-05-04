<?php

namespace Modules\PPUDS\Exports;

use Generator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromGenerator;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Modules\Core\Entities\User;
use Modules\PPUDS\Entities\Survey;
use Modules\PPUDS\Entities\SurveyAnswer;
use Modules\PPUDS\Entities\SurveyQuestion;
use Modules\PPUDS\Enums\SurveyQuestionType;

class SurveySubmissionsExport implements FromGenerator, ShouldAutoSize, WithHeadings
{
    protected Collection $questions;

    public function __construct(protected Survey $survey)
    {
        $this->survey->loadMissing([
            'questions' => fn ($query) => $query->orderBy('sort_order'),
            'questions.translations',
            'questions.options' => fn ($query) => $query->orderBy('sort_order'),
            'questions.options.translations',
        ]);

        $this->questions = $this->survey->questions
            ->sortBy('sort_order')
            ->values();
    }

    public function headings(): array
    {
        return array_merge(
            [__('Submitted By')],
            $this->questions
                ->map(fn (SurveyQuestion $question): string => $this->questionHeading($question))
                ->all()
        );
    }

    public function generator(): Generator
    {
        $users = $this->submittedUsersQuery()->get();

        if ($users->isEmpty()) {
            return;
        }

        $answers = SurveyAnswer::query()
            ->where('survey_id', $this->survey->id)
            ->whereIn('submitted_by', $users->pluck('id'))
            ->with(['option.translations'])
            ->orderBy('id')
            ->get()
            ->groupBy(['submitted_by', 'survey_question_id']);

        foreach ($users as $user) {
            yield $this->rowFor($user, $answers->get($user->id, collect()));
        }
    }

    protected function submittedUsersQuery(): Builder
    {
        return User::query()
            ->with(['roles', 'studentProfile.major'])
            ->select('users.*')
            ->when($this->survey->serve_group, fn (Builder $query, string $role) => $query->role($role))
            ->when(
                $this->survey->major_id,
                fn (Builder $query, int $majorId) => $query->whereHas(
                    'studentProfile',
                    fn (Builder $profileQuery) => $profileQuery->where('major_id', $majorId)
                )
            )
            ->whereIn('users.id', SurveyAnswer::query()
                ->select('submitted_by')
                ->where('survey_id', $this->survey->id)
                ->whereNotNull('submitted_by')
                ->distinct()
            )
            ->orderBy('users.name');
    }

    protected function rowFor(User $user, Collection $answersByQuestion): array
    {
        $row = [$this->submittedPerson($user)];

        foreach ($this->questions as $question) {
            $row[] = $this->answerText($question, $answersByQuestion->get($question->id, collect()));
        }

        return $row;
    }

    protected function answerText(SurveyQuestion $question, Collection $answers): string
    {
        return match ((int) $question->type) {
            SurveyQuestionType::RADIO->value,
            SurveyQuestionType::SELECT->value => $this->singleSelectedOptionText($question, $answers),

            SurveyQuestionType::CHECKBOX->value,
            SurveyQuestionType::MULTI_SELECT->value => $this->selectedOptionsText($question, $answers),

            SurveyQuestionType::FILE->value => $this->textAnswers($answers),

            default => $this->singleTextAnswer($answers),
        };
    }

    protected function singleTextAnswer(Collection $answers): string
    {
        return (string) ($answers
            ->first(fn (SurveyAnswer $answer): bool => filled($answer->text_answer))
            ?->text_answer ?? '');
    }

    protected function textAnswers(Collection $answers): string
    {
        return $answers
            ->pluck('text_answer')
            ->filter(fn (?string $answer): bool => filled($answer))
            ->unique()
            ->implode(', ');
    }

    protected function singleSelectedOptionText(SurveyQuestion $question, Collection $answers): string
    {
        return (string) ($answers
            ->map(fn (SurveyAnswer $answer): ?string => $this->optionTextForQuestion($question, $answer))
            ->first(fn (?string $answer): bool => filled($answer)) ?? '');
    }

    protected function selectedOptionsText(SurveyQuestion $question, Collection $answers): string
    {
        return $answers
            ->map(fn (SurveyAnswer $answer): ?string => $this->optionTextForQuestion($question, $answer))
            ->filter(fn (?string $answer): bool => filled($answer))
            ->unique()
            ->implode(', ');
    }

    protected function optionTextForQuestion(SurveyQuestion $question, SurveyAnswer $answer): ?string
    {
        if ((int) $answer->option?->survey_question_id !== (int) $question->id) {
            return null;
        }

        return $answer->option?->text;
    }

    protected function submittedPerson(User $user): string
    {
        $name = trim((string) $user->name) ?: __('User').' #'.$user->id;
        $email = trim((string) $user->email);

        return $email ? "{$name} ({$email})" : $name;
    }

    protected function questionHeading(SurveyQuestion $question): string
    {
        $content = trim((string) $question->content);

        return $content !== '' ? $content : __('Question').' #'.$question->id;
    }
}
