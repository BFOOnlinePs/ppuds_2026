<?php

namespace Modules\PPUDS\Exports;

use Generator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromGenerator;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Modules\Core\Entities\User;
use Modules\PPUDS\Entities\Company;
use Modules\PPUDS\Entities\StudentCompany;
use Modules\PPUDS\Entities\Survey;
use Modules\PPUDS\Entities\SurveyAnswer;
use Modules\PPUDS\Entities\SurveyQuestion;
use Modules\PPUDS\Enums\SurveyQuestionType;
use Modules\PPUDS\Settings\GeneralSettings;
use Modules\PPUDS\Support\HandlesCompanySupervisorSurveyEvaluations;

class SurveySubmissionsExport implements FromGenerator, ShouldAutoSize, WithHeadings
{
    use HandlesCompanySupervisorSurveyEvaluations;

    protected Collection $questions;

    /**
     * $majorId يأتي من فلتر التخصص في جدول التسليمات، فيخرج الملف مطابقاً
     * لما يراه المستخدم على الشاشة بدل تصدير الجميع دائماً.
     */
    public function __construct(protected Survey $survey, protected ?int $majorId = null)
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
            [__('Student Phone'), __('Student Number'), __('Current Training Place'), __('Training Place Address')],
            $this->isCompanySupervisorSurvey($this->survey)
                ? [__('Submitted By'), __('Submitted By Email'), __('Evaluated Student'), __('Email'), __('Major')]
                : [__('Submitted By'), __('Email'), __('Major')],
            $this->questions
                ->map(fn (SurveyQuestion $question): string => $this->questionHeading($question))
                ->all()
        );
    }

    public function generator(): Generator
    {
        if ($this->isCompanySupervisorSurvey($this->survey)) {
            yield from $this->companyEvaluationGenerator();

            return;
        }

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

        $currentStudentCompanies = $this->currentStudentCompaniesFor($users->pluck('id'));

        foreach ($users as $user) {
            yield $this->rowFor($user, $answers->get($user->id, collect()), $currentStudentCompanies->get($user->id));
        }
    }

    protected function companyEvaluationGenerator(): Generator
    {
        $studentCompaniesTable = (new StudentCompany)->getTable();

        $studentCompanies = $this->currentSurveyStudentCompaniesQuery($this->survey)
            ->with([
                // الطالب المحذوف حذفاً ناعماً يبقى تقييمه في الجدول، فنُظهر بياناته بدل خانات فارغة.
                'student' => fn ($query) => $query->withTrashed(),
                'student.studentProfile.major.translations',
                'company.translations',
            ])
            ->when($this->majorId, fn (Builder $query, int $majorId): Builder => $query->whereHas(
                'student.studentProfile',
                fn (Builder $profileQuery): Builder => $profileQuery->where('major_id', $majorId)
            ))
            ->whereIn("{$studentCompaniesTable}.id", SurveyAnswer::query()
                ->select('student_company_id')
                ->where('survey_id', $this->survey->id)
                ->whereNotNull('student_company_id')
                ->distinct()
            )
            ->orderBy('id')
            ->get();

        if ($studentCompanies->isEmpty()) {
            return;
        }

        $answers = SurveyAnswer::query()
            ->where('survey_id', $this->survey->id)
            ->whereIn('student_company_id', $studentCompanies->pluck('id'))
            // User يستخدم SoftDeletes، والمفتاح الأجنبي لا يُحذف إلا مع الحذف الفعلي،
            // فإجابات المشرف المحذوف ناعماً تبقى ويعود submittedBy بـ null بدون withTrashed.
            ->with(['option.translations', 'submittedBy' => fn ($query) => $query->withTrashed()])
            ->orderBy('id')
            ->get()
            ->groupBy(['student_company_id', 'survey_question_id']);

        foreach ($studentCompanies as $studentCompany) {
            yield $this->rowForStudentCompany($studentCompany, $answers->get($studentCompany->id, collect()));
        }
    }

    protected function submittedUsersQuery(): Builder
    {
        return User::query()
            ->with(['roles', 'studentProfile.major.translations'])
            ->select('users.*')
            ->when($this->survey->serve_group, fn (Builder $query, string $role) => $query->role($role))
            ->when(
                $this->survey->major_id,
                fn (Builder $query, int $majorId) => $query->whereHas(
                    'studentProfile',
                    fn (Builder $profileQuery) => $profileQuery->where('major_id', $majorId)
                )
            )
            ->when($this->majorId, fn (Builder $query, int $majorId): Builder => $query->whereHas(
                'studentProfile',
                fn (Builder $profileQuery): Builder => $profileQuery->where('major_id', $majorId)
            ))
            ->whereIn('users.id', SurveyAnswer::query()
                ->select('submitted_by')
                ->where('survey_id', $this->survey->id)
                ->whereNotNull('submitted_by')
                ->distinct()
            )
            ->orderBy('users.name');
    }

    /**
     * مكان التدريب الحالي لكل طالب ضمن الفصل والسنة المعتمدين في الإعدادات،
     * محمّل دفعة واحدة لتجنّب استعلام لكل صف. عند وجود أكثر من تسجيل يُعتمد الأحدث.
     *
     * @return Collection<int, StudentCompany>
     */
    protected function currentStudentCompaniesFor(Collection $studentIds): Collection
    {
        $settings = app(GeneralSettings::class);

        return StudentCompany::query()
            ->with('company.translations')
            ->whereIn('student_id', $studentIds)
            ->whereNotNull('company_id')
            ->whereHas('registration', fn (Builder $query): Builder => $query
                ->where('semester', $settings->semester_type->value)
                ->where('year', $settings->year))
            ->orderByDesc('id')
            ->get()
            ->unique('student_id')
            ->keyBy('student_id');
    }

    /**
     * الأعمدة الأربعة الأولى: هاتف الطالب، رقمه الجامعي، مكان تدريبه الحالي وعنوانه.
     * غير الطلبة (كالمشرفين) تبقى خاناتهم فارغة.
     */
    protected function studentLeadingColumns(?User $student, ?StudentCompany $studentCompany): array
    {
        return [
            (string) ($student?->phone ?? ''),
            (string) ($student?->studentProfile?->student_number ?? ''),
            (string) ($studentCompany?->company?->name ?? ''),
            $this->companyAddress($studentCompany?->company),
        ];
    }

    protected function companyAddress(?Company $company): string
    {
        return collect([$company?->main_location, $company?->sub_location])
            ->map(fn (?string $part): string => trim((string) $part))
            ->filter()
            ->unique()
            ->implode(' - ');
    }

    protected function rowFor(User $user, Collection $answersByQuestion, ?StudentCompany $currentStudentCompany = null): array
    {
        $row = [
            ...$this->studentLeadingColumns($user, $currentStudentCompany),
            $this->submittedPerson($user),
            (string) $user->email,
            $this->majorNameFor($user),
        ];

        foreach ($this->questions as $question) {
            $row[] = $this->answerText($question, $answersByQuestion->get($question->id, collect()));
        }

        return $row;
    }

    protected function rowForStudentCompany(StudentCompany $studentCompany, Collection $answersByQuestion): array
    {
        $firstAnswer = $answersByQuestion
            ->flatten(1)
            ->first();

        $row = [
            ...$this->studentLeadingColumns($studentCompany->student, $studentCompany),
            $firstAnswer instanceof SurveyAnswer ? $this->submittedPerson($firstAnswer->submittedBy, $firstAnswer->submitted_by) : '',
            (string) $firstAnswer?->submittedBy?->email,
            $this->studentPerson($studentCompany),
            (string) $studentCompany->student?->email,
            $this->majorNameFor($studentCompany->student),
        ];

        foreach ($this->questions as $question) {
            $row[] = $this->answerText($question, $answersByQuestion->get($question->id, collect()));
        }

        return $row;
    }

    /**
     * تخصص الطالب. المشرفون وغير الطلبة لا ملف طالب لهم، فتبقى الخانة فارغة
     * بدل أن تُسقط الصف.
     */
    protected function majorNameFor(?User $user): string
    {
        return (string) ($user?->studentProfile?->major?->name ?? '');
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

    /**
     * يقبل null كخط دفاع أخير (حساب حُذف فعلياً أو علاقة مفقودة) بدل إسقاط التصدير كاملاً.
     */
    protected function submittedPerson(?User $user, ?int $fallbackId = null): string
    {
        $id = $user?->id ?? $fallbackId;

        return trim((string) $user?->name) ?: __('User').($id ? ' #'.$id : '');
    }

    protected function studentPerson(StudentCompany $studentCompany): string
    {
        $student = $studentCompany->student;
        return trim((string) $student?->name) ?: __('Student').' #'.$studentCompany->student_id;
    }

    protected function questionHeading(SurveyQuestion $question): string
    {
        $content = trim((string) $question->content);

        return $content !== '' ? $content : __('Question').' #'.$question->id;
    }
}
